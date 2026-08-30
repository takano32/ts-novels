#!/usr/bin/env python3
"""bodies/*.md → Gutenberg ブロック HTML (タスク 3.1 + 3.2 の src 書換。Fable 所管)。

入力:
  bodies/<episode_id>.md            … body_convert.py の成果 (3,642 本)
  catalog/episodes.jsonl            … source_path (画像の相対参照の基準ディレクトリ)
  catalog/convert_report.jsonl      … raw-fallback 2 件の検出
  git ls-files novel                … 画像実体の存在検査

出力:
  payloads/<episode_id>.html        … `wp ts import --bodies=payloads` が post_content に流す
  payloads/_meta.json               … episode_id → body_status (importer が _ts_body_status に書く)
  catalog/reports/payload_build.json

ブロック対応 (台帳 3.1): paragraph / separator / quote / heading / image。
ruby はパラグラフ内インライン HTML、本文内 table は wp:html ブロック。
raw フォールバックは原本の本体域 (body_convert.extract_body) を wp:html 1 ブロックに。

無損失検査 (この生成器自身の): 各ファイルで strip(md) == strip(payload) を
タグ除去 → 実体参照解決 → 記号マーカー除去 → 空白圧縮 → NFC で比較する。
違反 0 が受け入れ条件 (report の invariant_ok で機械判定)。
"""
import html
import json
import os
import re
import subprocess
import sys
import unicodedata
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import body_convert  # noqa: E402  (extract_body / preclean / read_text を raw fallback に使う)

ROOT = Path(__file__).resolve().parents[2]
BODIES = ROOT / 'bodies'
OUT = ROOT / 'payloads'

HEAD_RE = re.compile(r'^(#{1,6}) (.*)$')
MD_IMG_RE = re.compile(r'!\[([^\]\n]*)\]\(([^)\n]+)\)')  # 画像 1 枚もの 17 話が使う記法
IMG_ONLY_RE = re.compile(r'^\s*(<img\b[^>]*>)\s*$', re.I)
IMG_TAG_RE = re.compile(r'<img\b[^>]*>', re.I)
SRC_RE = re.compile(r'(src\s*=\s*")([^"]*)(")', re.I)
# 実体参照でない裸の & のみ escape する
AMP_RE = re.compile(r'&(?![a-zA-Z][a-zA-Z0-9]*;|#\d+;|#[xX][0-9a-fA-F]+;)')


def image_inventory():
    """小文字パス → git 上の実パス (Windows 由来の大文字小文字ゆらぎを吸収する)。"""
    ls = subprocess.run(['git', '-C', str(ROOT), 'ls-files', 'novel'],
                        capture_output=True, text=True, check=True).stdout.splitlines()
    return {p.lower(): p for p in ls if re.search(r'\.(jpe?g|gif|png|bmp)$', p, re.I)}


# カウンタ CGI・ブログスキン・アクセス解析・広告 — 内容ではないので img ごと落とす
JUNK_SRC_RE = re.compile(
    r'(?i)(\.cgi(\?|$)|@gif\.cgi|@today\.cgi|@yes\.cgi|^_skin/|/_skin/'
    r'|analyzer\d*\.fc2\.com|counter_img\.php|blogvote\.fc2\.com|shinobi\.jp/'
    r'|valuecommerce\.com/|infoseek\.co\.jp/bin/logo|/ana/icon\.php)')


def rewrite_srcs(text, src_dir, images, warn):
    """img の相対 src を /assets/annex-img/ の絶対参照に。実体の無い参照は記録する。"""
    def sub_img(m):
        tag = m.group(0)
        src_m = SRC_RE.search(tag)
        src = html.unescape(src_m.group(2).strip()) if src_m else ''
        if JUNK_SRC_RE.search(src):
            warn['junk'].append(src)
            return ''
        def sub_src(sm):
            s = html.unescape(sm.group(2).strip()).replace('\\', '/')
            if re.match(r'(?i)^(https?:)?//', s) or s.startswith('/'):
                warn['external'].append(s)
                return sm.group(0)
            rel = os.path.normpath(os.path.join(src_dir, s)).replace('\\', '/')
            actual = images.get(rel.lower())
            if actual is None:
                warn['missing'].append(rel)
                actual = rel
            return '{}/assets/annex-img/{}{}'.format(
                sm.group(1), html.escape(actual, quote=False), sm.group(3))
        return SRC_RE.sub(sub_src, tag)
    return IMG_TAG_RE.sub(sub_img, text)


# --------------------------------------------------------------------------- blocks

def b_para(content):
    return f'<!-- wp:paragraph -->\n<p>{content}</p>\n<!-- /wp:paragraph -->'


def b_sep():
    return ('<!-- wp:separator -->\n'
            '<hr class="wp-block-separator has-alpha-channel-opacity"/>\n'
            '<!-- /wp:separator -->')


def b_heading(level, content):
    lv = min(max(level + 1, 2), 6)  # 本文の h1 は投稿タイトルと衝突するので h2 起点に落とす
    attr = '' if lv == 2 else ' {"level":%d}' % lv
    return (f'<!-- wp:heading{attr} -->\n'
            f'<h{lv} class="wp-block-heading">{content}</h{lv}>\n<!-- /wp:heading -->')


def b_quote(paras):
    inner = '\n'.join(b_sep() if re.fullmatch(r'-{2,}', p.strip()) else b_para(p)
                      for p in paras)
    return (f'<!-- wp:quote -->\n<blockquote class="wp-block-quote">\n{inner}\n'
            f'</blockquote>\n<!-- /wp:quote -->')


def b_image(tag):
    # title は属性のまま保持 (figcaption にすると無損失検査の可視テキストが原文と食い違う)
    return f'<!-- wp:image -->\n<figure class="wp-block-image">{tag}</figure>\n<!-- /wp:image -->'


def b_html(raw):
    return f'<!-- wp:html -->\n{raw}\n<!-- /wp:html -->'


# --------------------------------------------------------------------------- md → blocks

def table_run_end(lines, i):
    """i 行目から始まる table が閉じる行の次を返す。閉じないなら None (段落扱いに落とす)。"""
    depth = 0
    for j in range(i, len(lines)):
        depth += len(re.findall(r'(?i)<table\b', lines[j]))
        depth -= len(re.findall(r'(?i)</table\s*>', lines[j]))
        if depth <= 0:
            return j + 1
    return None


def md_to_blocks(md, stats):
    # Markdown 画像記法 → img タグ (QA 3.3 で発見: 画像 1 枚ものエントリが記法のまま漏れていた)
    md = MD_IMG_RE.sub(
        lambda m: '<img src="%s" alt="%s">' % (m.group(2).replace('"', '&quot;'),
                                               m.group(1).replace('"', '&quot;')), md)
    # タグ開始に見えない裸の '<' (例: 文末の「戻る<」) を先に実体化する — 後段の
    # タグ畳み込みが後方の '>' まで本文を巻き込む事故の根絶
    md = re.sub(r'<(?![a-zA-Z/!])', '&lt;', md)
    # 原本由来の複数行タグ (<img\nsrc=…> 等。stash 復元で改行が保たれている) を 1 行に畳む。
    # 畳まないと段落連結の <br> がタグ内部に入って壊れる (初回実測 NG 225 件の原因)
    md = re.sub(r'<[^>]*>', lambda m: ' '.join(m.group(0).split()), md)
    lines = md.split('\n')
    blocks, i, n = [], 0, len(lines)
    while i < n:
        line = lines[i]
        if line.strip() == '':
            i += 1
            continue
        if line == '----':
            blocks.append(b_sep()); stats['separator'] += 1; i += 1
            continue
        m = HEAD_RE.match(line)
        if m:
            # 見出しの中に引用マーカーが残る形 (`## > Ａｎｇｅｌ`) は原本の h 内 blockquote の名残
            content = re.sub(r'^(?:> ?)+', '', m.group(2))
            blocks.append(b_heading(len(m.group(1)), esc(content)))
            stats['heading'] += 1; i += 1
            continue
        if line.startswith('>'):
            qlines = []
            while i < n and lines[i].startswith('>'):
                qlines.append(lines[i][2:] if lines[i].startswith('> ') else lines[i][1:])
                i += 1
            # 引用内は空行 (空の '>' 行) 区切りで段落化。'----' は引用内の区切り線
            body = '\n'.join(esc(x) for x in qlines)
            paras = [q.strip('\n').replace('\n', '<br>')
                     for q in re.split(r'\n\s*\n', body) if q.strip()]
            blocks.append(b_quote(paras)); stats['quote'] += 1
            continue
        if re.match(r'(?i)^\s*<table\b', line):
            j = table_run_end(lines, i)
            if j is not None:
                blocks.append(b_html(esc('\n'.join(lines[i:j])))); stats['table'] += 1
                i = j
                continue
            # 閉じタグの無い table (当時の手書き HTML に実在) — 呑み込まず段落として流す
        # 段落 (連続する非空行。単独 img 段落は image ブロックに)
        plines = []
        while i < n and lines[i].strip() != '' and lines[i] != '----' \
                and not HEAD_RE.match(lines[i]) and not lines[i].startswith('>') \
                and not (re.match(r'(?i)^\s*<table\b', lines[i])
                         and table_run_end(lines, i) is not None):
            plines.append(lines[i]); i += 1
        joined = '\n'.join(plines)
        m = IMG_ONLY_RE.match(joined)
        if m:
            blocks.append(b_image(esc(m.group(1)))); stats['image'] += 1
        else:
            blocks.append(b_para(esc(joined).replace('\n', '<br>'))); stats['paragraph'] += 1
    return blocks


def esc(text):
    return AMP_RE.sub('&amp;', text)


# --------------------------------------------------------------------------- 無損失検査

TAG_RE = re.compile(r'<[^>]*>')
CMT_RE = re.compile(r'<!--.*?-->', re.S)


def visible(text, is_md):
    t = CMT_RE.sub('', text)
    if is_md:
        t = MD_IMG_RE.sub('', t)  # 画像記法はタグに変換されるので md 側でも落とす
        t = re.sub(r'^(#{1,6}) ', '', t, flags=re.M)
        t = re.sub(r'^> ?', '', t, flags=re.M)
    t = re.sub(r'(?i)<br\s*/?>', '\n', t)  # <br> 連結を行構造に戻す (ダッシュ行判定の対称性)
    t = TAG_RE.sub('', t)
    t = html.unescape(t)
    # ダッシュだけの行は両側で落とす (md の区切り行 / 閉じない table 内に残る '----' を対称に扱う)
    t = re.sub(r'^\s*-{2,}\s*$', '', t, flags=re.M)
    t = re.sub(r'\s+', '', t)
    return unicodedata.normalize('NFC', t)


# --------------------------------------------------------------------------- main

def main():
    OUT.mkdir(exist_ok=True)
    episodes = {}
    with open(ROOT / 'catalog' / 'episodes.jsonl', encoding='utf-8') as fh:
        for line in fh:
            ep = json.loads(line)
            episodes[ep['episode_id']] = ep
    raw_fallbacks = []
    with open(ROOT / 'catalog' / 'convert_report.jsonl', encoding='utf-8') as fh:
        for line in fh:
            r = json.loads(line)
            if r.get('status') == 'raw-fallback':
                raw_fallbacks.append(r['episode_id'])
    images = image_inventory()

    stats = {k: 0 for k in ('paragraph', 'separator', 'quote', 'heading', 'image', 'table')}
    warn = {'missing': [], 'external': [], 'junk': []}
    meta, violations, eyecatch = {}, [], {}
    written = 0

    for md_path in sorted(BODIES.glob('*.md')):
        eid = md_path.name[:-3]
        ep = episodes.get(eid)
        src_dir = os.path.dirname(ep['source_path']) if ep else ''
        md = md_path.read_text(encoding='utf-8')
        blocks = md_to_blocks(md, stats)
        out = '\n\n'.join(blocks) + '\n'
        out = rewrite_srcs(out, src_dir, images, warn)
        if visible(md, True) != visible(out, False):
            violations.append(eid)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        for em in re.findall(r'(?i)src="(/assets/annex-img/[^"]*eyecatch\.[a-z]+)"', out):
            eyecatch.setdefault(eid, []).append(em)
        meta[eid] = 'converted'
        written += 1

    # 1.9 の作者再掲ぶん: md が無く reposts/<episode_id>.txt がある話はプレーンテキストを段落化
    # (pixiv 再掲はプレーンテキスト。全て escape するので HTML 断片の混入はない)
    for eid, ep in episodes.items():
        if eid in meta:
            continue
        txt_path = ROOT / 'reposts' / f'{eid}.txt'
        if not txt_path.is_file():
            continue
        text = txt_path.read_text(encoding='utf-8')
        paras = [html.escape(p.strip('\n'), quote=False).replace('\n', '<br>')
                 for p in re.split(r'\n\s*\n', text) if p.strip()]
        out = '\n\n'.join(b_para(p) for p in paras) + '\n'
        if re.sub(r'\s+', '', unicodedata.normalize('NFC', text)) != visible(out, False):
            violations.append(eid)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        stats['paragraph'] += len(paras)
        meta[eid] = 'converted-text'
        written += 1

    for eid in raw_fallbacks:
        ep = episodes.get(eid)
        if not ep:
            continue
        raw, _enc = body_convert.read_text(str(ROOT / ep['source_path']))
        region = body_convert.extract_body(raw)
        region = rewrite_srcs(region, os.path.dirname(ep['source_path']), images, warn)
        (OUT / f'{eid}.html').write_text(b_html(region) + '\n', encoding='utf-8')
        meta[eid] = 'raw-fallback'
        written += 1

    (OUT / '_meta.json').write_text(
        json.dumps(meta, ensure_ascii=False, indent=0), encoding='utf-8')
    report = {
        'written': written,
        'converted': written - len(raw_fallbacks),
        'raw_fallback': sorted(raw_fallbacks),
        'blocks': stats,
        'invariant_ok': not violations,
        'invariant_violations': violations[:20],
        'images_missing': sorted(set(warn['missing'])),
        'images_external': sorted(set(warn['external'])),
        'images_junk_dropped': sorted(set(warn['junk'])),
        'converted_text': sorted(e for e, s in meta.items() if s == 'converted-text'),
        'eyecatch_refs': {k: sorted(set(v)) for k, v in sorted(eyecatch.items())},
    }
    rp = ROOT / 'catalog' / 'reports' / 'payload_build.json'
    rp.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f"payloads: {written} 本 (raw {len(raw_fallbacks)}) blocks={stats}")
    print(f"無損失検査: {'OK' if not violations else 'NG %d 件' % len(violations)}"
          f" / 画像未解決 {len(set(warn['missing']))} / 外部 {len(set(warn['external']))}")
    print(f"report: {rp}")
    if violations:
        sys.exit(1)


if __name__ == '__main__':
    main()
