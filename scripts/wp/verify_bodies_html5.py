#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""bodies/*.md の無損失性を html5lib で検算する (body_convert.py の第二実装による突き合わせ)

なぜ要るか:
  body_convert.py は正規表現で HTML を解釈し、無損失証明も同じ正規表現で書かれている。
  「両側を同じ思い込みで間違えている」可能性が残る。そこで **html5lib**
  (HTML5 のパース仕様どおりに解釈する = 当時のブラウザと同じ木を作る) を
  完全に独立した第二実装として使い、変換結果を検算する。書き直しではなく差分検出。

比較のしかた:
  原本 HTML を html5lib でパース → <body> 配下の可視テキスト (script/style/comment 除外) →
  「空白と山括弧を除き NFC 正規化」した文字列 ORIG を作る。
  bodies/<id>.md からも同じ規則で CONV を作る。

  ただし body_convert.py は無損失証明を通したあとで
    (a) クローム除去 strip_chrome() — 冒頭/末尾の定型ナビ帯 (report の chrome_removed)
    (b) 定型ナビ行 trim_nav_lines() — (report の nav_trimmed)
  を落としている。落とした断片は report に**切り詰めて**記録されており、
  完全な復元はできない。そこで足し戻すのではなく **CONV が ORIG の連続部分文字列か**
  を判定する。これは (a)(b) が冒頭と末尾からしか削らないことから等価であり、
  かつ「本文の内部で 1 文字でも失われた/増えた」を必ず捕まえる。
  加えて、はみ出した前置き prefix と後置き suffix を取り出して、
  それが本当にナビ定型だけか (=地の文を巻き込んでいないか) を検査する。

出力:
  catalog/reports/verify_html5.json   件数サマリ + 不一致の全一覧

使い方:
  .venv/bin/python scripts/wp/verify_bodies_html5.py --limit 300   # まず 300 話
  .venv/bin/python scripts/wp/verify_bodies_html5.py               # 全数
  .venv/bin/python scripts/wp/verify_bodies_html5.py --id <episode_id>
"""
import os, re, sys, json, unicodedata, argparse, collections, warnings
from concurrent.futures import ProcessPoolExecutor

warnings.filterwarnings('ignore')

try:
    from bs4 import BeautifulSoup
except ImportError:
    sys.exit('beautifulsoup4 / html5lib が要る:  .venv/bin/pip install beautifulsoup4 html5lib\n'
             '(make venv でも入る)')

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
EPISODES = os.path.join(ROOT, 'catalog', 'episodes.jsonl')
CONVERT_REPORT = os.path.join(ROOT, 'catalog', 'convert_report.jsonl')
BODIES = os.path.join(ROOT, 'bodies')
OUT = os.path.join(ROOT, 'catalog', 'reports', 'verify_html5.json')

# 原本が HTML ではない話は突き合わせの対象外 (比較すべき原本 HTML が無い)
SKIP_MODES = {'plain-text', 'image-work'}
# 前置き/後置きが「ナビ定型だけ」と言い切れる上限。body_convert の chrome_is_safe と同じ 200 字
CHROME_BUDGET = 200

# ---------------------------------------------------------------- 共通

def read_text(path):
    """ミラーは UTF-8 変換済みだが .txt などに当時のままのものが残る"""
    data = open(path, 'rb').read()
    for enc in ('utf-8', 'cp932', 'euc_jp'):
        try:
            return data.decode(enc)
        except UnicodeDecodeError:
            continue
    return data.decode('cp932', 'replace')


def normalize(s):
    """比較キー = 空白と山括弧を除き NFC 正規化。body_convert の normalize_chars と同じ規則。
    ただしタグ除去はここではしない (html5lib が既に木にしている)。"""
    s = ''.join(ch for ch in s if not ch.isspace() and ch not in '<>')
    return unicodedata.normalize('NFC', s)


# ---------------------------------------------------------------- 原本側 (html5lib)

DROP_TAGS = ('script', 'style', 'template')
BLOCK_TAGS = ['br', 'hr', 'p', 'div', 'tr', 'li', 'dt', 'dd', 'center', 'table',
              'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre']


RAWTEXT_WRAP_RE = re.compile(r'(?i)</?(?:noframes|iframe|noembed|xmp|frameset|frame)\b[^>]*>')


def html5_text(markup, fragment=False, drop_forms=False, drop_noscript=True,
               unwrap_rawtext=False):
    """html5lib で解釈し、当時のブラウザに見えていたテキストを返す。
    <br>/<p>/<div> 等は改行として扱う (どうせ正規化で空白は落ちるが、
    文脈表示と行単位の目視のために入れておく)。

    drop_noscript: html5lib は「スクリプト無効」として解釈するので <noscript> の
      中身が木に残る。当時のブラウザ (JS 有効) には見えなかったので既定で落とす。
    drop_forms: body_convert は感想フォームをクロームとして落とす。その差を切り分ける。
    unwrap_rawtext: HTML5 では <noframes>/<iframe>/<noembed>/<xmp> の中身は
      **生テキスト**なので、代替内容のマークアップがそのまま文字として出てくる。
      当時のブラウザ (frame/iframe 対応) はその代替内容自体を表示しなかった。
      body_convert は普通のマークアップとして解釈しタグを落とすので、その差を切り分ける。"""
    if unwrap_rawtext:
        markup = RAWTEXT_WRAP_RE.sub('', markup)
    soup = BeautifulSoup(markup, 'html5lib')
    root = soup.body if (soup.body is not None and not fragment) else soup
    if root is None:
        root = soup
    drop = list(DROP_TAGS) + (['noscript'] if drop_noscript else [])
    for t in root.find_all(drop):
        t.decompose()
    if drop_forms:
        for t in root.find_all('form'):
            t.decompose()
    for t in root.find_all(BLOCK_TAGS):
        t.insert_before('\n')
        t.insert_after('\n')
    # get_text() は既定で Comment / Script / Stylesheet を含めない (型が完全一致でないため)
    return root.get_text()


# ---------------------------------------------------------------- 変換側 (Markdown)

QUOTE_RE = re.compile(r'^(?:>\s?)+')                # <blockquote> 由来の引用記号 (SENT['Q'])
SEP_LINE_RE = re.compile(r'^\s*----\s*$')          # <hr> 由来の区切り (SENT['SEP'])
HEAD_RE = re.compile(r'^(#{1,6})(?:\s|$)')          # <h1..6> 由来の見出し記号
# body_convert が Markdown に**実タグとして**残すのは stash した 3 種だけ
# (to_markdown の table_sub / <ruby>…</ruby> / <img>)。それ以外の '<…>' は
# 原本で &lt; だった地の文なので、タグとして解釈してはいけない
KEPT_BLOCK_RE = re.compile(r'(?is)<table[^>]*>.*?</table>|<ruby[^>]*>.*?</ruby>|<img[^>]*>')
# 温存タグを除いたあとに残る「タグに見える地の文」= Markdown を描画したとき消える危険
TAGLIKE_RE = re.compile(r'</?[A-Za-z][^>]*>')


def md_text(md):
    """bodies/*.md → 可視テキスト。Markdown 化で足された記号だけを外し、
    温存されたインライン HTML (ruby/img/本物の表) は html5lib に解かせる。

    記号の判定は引用記号 '> ' を剥がしてから行う ('> ----' のような入れ子がある)。
    '>' 自体は正規化で落ちるので、剥がした結果を書き戻す必要はない。"""
    lines = []
    stripped = collections.Counter()
    for l in md.split('\n'):
        body = QUOTE_RE.sub('', l)
        if SEP_LINE_RE.match(body):
            stripped['sep'] += 1
            continue
        m = HEAD_RE.match(body)
        if m:
            stripped['heading'] += 1
            l = body[m.end():]
        lines.append(l)
    text = '\n'.join(lines)
    if '<' in text:
        def unwrap(m):
            stripped['kept_html'] += 1
            return html5_text(m.group(0), fragment=True)
        text = KEPT_BLOCK_RE.sub(unwrap, text)
        n = len(TAGLIKE_RE.findall(text))
        if n:
            # 原本では &lt;…&gt; だった地の文が、Markdown では生の '<…>' になっている。
            # 比較上は文字として残す (原本側も文字として見えている) が、
            # WordPress で描画するとタグとして消えるので数えて報告する
            stripped['taglike_text'] += n
    return text, stripped


# ---------------------------------------------------------------- ナビ判定

# 地の文の徴候。ナビ・題名・作者名にはまず現れない
PROSE_RE = re.compile(r'[。！？…]|」[^」]*「')


def looks_like_chrome(fragment):
    """はみ出した断片が「ナビ・題名・定型だけ」に見えるか。
    長すぎる (body_convert 自身の chrome_is_safe と同じ 200 字) か、
    文末記号を含む = 地の文を巻き込んだ疑い → False"""
    if not fragment:
        return True
    if len(fragment) > CHROME_BUDGET:
        return False
    return not PROSE_RE.search(fragment)


# ---------------------------------------------------------------- 1 話ぶん

def verify(job):
    eid, src, mode, anchor, chrome_removed, nav_trimmed = job
    r = {'episode_id': eid, 'source_path': src, 'mode': mode}
    mdpath = os.path.join(BODIES, eid + '.md')
    if not os.path.isfile(mdpath):
        r.update(result='no-md')
        return r
    full = os.path.join(ROOT, src)
    if not os.path.isfile(full):
        r.update(result='no-source')
        return r

    try:
        raw = read_text(full)
        md = open(mdpath, encoding='utf-8').read()
        conv_text, md_stripped = md_text(md)
        conv = normalize(conv_text)
        # 解釈差を切り分けるための梯子。順に試し、**はみ出しが最小**のものを採る。
        #   strict    … JS 有効のブラウザが見た姿 (noscript は隠れる)
        #   noscript  … body_convert は noscript の中身を残すので、その差の切り分け
        #   *-no-form … body_convert は <form> (感想フォーム) をクロームとして落とす
        #   rawtext*  … <iframe>/<noframes> の代替内容 (HTML5 では生テキスト) の差
        variants = [('strict', dict()),
                    ('noscript', dict(drop_noscript=False)),
                    ('no-form', dict(drop_forms=True)),
                    ('noscript+no-form', dict(drop_noscript=False, drop_forms=True)),
                    ('rawtext', dict(unwrap_rawtext=True)),
                    ('rawtext+all', dict(unwrap_rawtext=True, drop_noscript=False,
                                         drop_forms=True))]
    except Exception as e:
        r.update(result='error', reason=f'{type(e).__name__}: {e}')
        return r

    r['conv_chars'] = len(conv)
    if md_stripped:
        r['md_markers'] = dict(md_stripped)
    if not conv:
        # 画像だけの作品 (漫画・イラスト) — 比較すべき可視テキストがそもそも無い。
        # 原本側もクローム以外の文字を持たないことだけ確かめる
        orig = normalize(html5_text(raw))
        r['orig_chars'] = len(orig)
        r.update(result='match-empty' if looks_like_chrome(orig) else 'empty-md-suspect')
        if r['result'] != 'match-empty':
            r['head_text'] = orig[:240]
        return r

    best = None
    try:
        for name, kw in variants:
            orig = normalize(html5_text(raw, **kw))
            i = orig.find(conv)
            if i < 0:
                if best is None:
                    best = (name, orig, -1, 1 << 40)
                continue
            waste = len(orig) - len(conv)            # はみ出した前置き + 後置きの合計
            if best is None or best[2] < 0 or waste < best[3]:
                best = (name, orig, i, waste)
            if waste == 0 or looks_like_chrome(orig[:i]) and looks_like_chrome(orig[i + len(conv):]):
                break
    except Exception as e:
        r.update(result='error', reason=f'{type(e).__name__}: {e}')
        return r

    name, orig, i, _ = best
    r['orig_chars'] = len(orig)
    r['variant'] = name
    if i >= 0:
        prefix = orig[:i]
        suffix = orig[i + len(conv):]
        r['dropped_head'] = len(prefix)
        r['dropped_tail'] = len(suffix)
        head_ok = looks_like_chrome(prefix)
        tail_ok = looks_like_chrome(suffix)
        if anchor:
            # 1 ファイルに複数話が同居する層 (source_anchor)。前後にはみ出すのは
            # 同じファイルの**別の話**なので、クローム判定の対象にならない
            r['result'] = 'match-anchor'
        elif head_ok and tail_ok:
            r['result'] = 'match'
        else:
            r['result'] = 'match-but-chrome-suspect'
            if not head_ok:
                r['head_text'] = prefix[:240]
            if not tail_ok:
                r['tail_text'] = suffix[:240]
            r['recorded_chrome'] = chrome_removed
            r['recorded_nav'] = nav_trimmed
        return r

    # 不一致 — 最初に食い違う位置を出す
    j = 0
    n = min(len(orig), len(conv))
    while j < n and orig[j] == conv[j]:
        j += 1
    r.update(result='mismatch', diverge_at=j,
             orig_ctx=orig[max(0, j - 40):j + 40],
             conv_ctx=conv[max(0, j - 40):j + 40],
             recorded_chrome=chrome_removed, recorded_nav=nav_trimmed)
    return r


VARIANT_KW = {'strict': {}, 'noscript': dict(drop_noscript=False),
              'no-form': dict(drop_forms=True),
              'noscript+no-form': dict(drop_noscript=False, drop_forms=True),
              'rawtext': dict(unwrap_rawtext=True),
              'rawtext+all': dict(unwrap_rawtext=True, drop_noscript=False, drop_forms=True)}


def explain(job, variant='strict'):
    """1 話の食い違いを difflib で全部並べる (body_convert を直す人向け)"""
    import difflib
    eid, src, mode, anchor = job[0], job[1], job[2], job[3]
    raw = read_text(os.path.join(ROOT, src))
    orig = normalize(html5_text(raw, **VARIANT_KW.get(variant, {})))
    conv_text, markers = md_text(open(os.path.join(BODIES, eid + '.md'), encoding='utf-8').read())
    conv = normalize(conv_text)
    print(f'{eid}  mode={mode} anchor={anchor} variant={variant}')
    print(f'  原本(html5lib)={len(orig)} 変換(md)={len(conv)}  md 記号={dict(markers)}')
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, orig, conv,
                                                       autojunk=False).get_opcodes():
        if tag == 'equal':
            continue
        print(f'  {tag:7s} 原本-{i2-i1} 変換+{j2-j1}')
        if i2 > i1:
            print('    原本のみ:', repr(orig[i1:i2][:200]))
        if j2 > j1:
            print('    変換のみ:', repr(conv[j1:j2][:200]))


# ---------------------------------------------------------------- 実行

def load_jobs(args):
    reports = {}
    for l in open(CONVERT_REPORT, encoding='utf-8'):
        d = json.loads(l)
        reports[d['episode_id']] = d
    jobs = []
    for l in open(EPISODES, encoding='utf-8'):
        ep = json.loads(l)
        eid = ep['episode_id']
        d = reports.get(eid)
        if not d or d.get('status') != 'md':
            continue
        mode = d.get('mode')
        if mode in SKIP_MODES:
            continue
        src = ep.get('source_path') or ''
        if not src or src.lower().endswith('.txt') or src.startswith(('http://', 'https://')):
            continue
        if args.id and eid != args.id:
            continue
        jobs.append((eid, src, mode, ep.get('source_anchor'),
                     [c.get('text', '') for c in (d.get('chrome_removed') or [])],
                     [c.get('text', '') for c in (d.get('nav_trimmed') or [])]))
    if args.limit:
        jobs = jobs[:args.limit]
    return jobs


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int, help='先頭 N 話だけ')
    ap.add_argument('--id', help='1 話だけ (デバッグ用)')
    ap.add_argument('--jobs', type=int, default=max(1, (os.cpu_count() or 4) - 1))
    ap.add_argument('--out', default=OUT)
    ap.add_argument('--explain', metavar='EPISODE_ID',
                    help='その 1 話の食い違いを全部並べる (レポートは書かない)')
    ap.add_argument('--variant', default='strict', choices=sorted(VARIANT_KW),
                    help='--explain で使う原本側の解釈 (既定 strict)')
    args = ap.parse_args()

    if args.explain:
        args.id = args.explain
        jobs = load_jobs(args)
        if not jobs:
            sys.exit(f'{args.explain} は検算対象ではない (bodies/*.md が無いか対象外の層)')
        explain(jobs[0], args.variant)
        return

    jobs = load_jobs(args)
    print(f'検算対象 {len(jobs)} 話 (jobs={args.jobs})', file=sys.stderr)

    rows = []
    if args.jobs <= 1 or len(jobs) < 8:
        for j in jobs:
            rows.append(verify(j))
    else:
        with ProcessPoolExecutor(max_workers=args.jobs) as ex:
            for n, r in enumerate(ex.map(verify, jobs, chunksize=8), 1):
                rows.append(r)
                if n % 250 == 0:
                    print(f'  {n}/{len(jobs)}', file=sys.stderr)

    counts = collections.Counter(r['result'] for r in rows)
    ok = ('match', 'match-empty', 'match-anchor')
    bad = [r for r in rows if r['result'] not in ok]
    bad.sort(key=lambda r: (r['result'], r['episode_id']))
    summary = {
        'checked': len(rows),
        'result': dict(counts),
        'match_rate_pct': round(sum(counts[k] for k in ok) / len(rows) * 100, 3) if rows else 0.0,
        'variant': dict(collections.Counter(r.get('variant') for r in rows if r.get('variant'))),
        'dropped_head_max': max([r.get('dropped_head', 0) for r in rows] or [0]),
        'dropped_tail_max': max([r.get('dropped_tail', 0) for r in rows] or [0]),
        # 原本で &lt;…&gt; だった地の文が md では生の '<…>' になっている話。
        # 比較は通るが WordPress で描画するとタグとして消える
        'taglike_text_episodes': sorted(
            (r['episode_id'], r['md_markers']['taglike_text']) for r in rows
            if r.get('md_markers', {}).get('taglike_text')),
        'findings': bad,
    }
    summary['taglike_text_count'] = len(summary['taglike_text_episodes'])
    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, 'w', encoding='utf-8') as f:
        json.dump(summary, f, ensure_ascii=False, indent=1)
    print(json.dumps({k: v for k, v in summary.items()
                      if k not in ('findings', 'taglike_text_episodes')},
                     ensure_ascii=False, indent=1))
    for r in bad[:15]:
        print(' ', r['result'], r['episode_id'], '|',
              str(r.get('reason') or r.get('orig_ctx') or r.get('head_text')
                  or r.get('tail_text') or '')[:100])


if __name__ == '__main__':
    main()
