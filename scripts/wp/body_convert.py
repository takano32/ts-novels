#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""本文 HTML → Markdown 変換 (タスク 1.6 / 設計 v1.2 改訂章 A)

方針:
  1. ブラウザ等価の前処理 (preclean) — script/style/コメント/bogus comment/裸の '<'/
     感想フォームを、当時のブラウザと同じ規則で落とす
  2. **クローム除去** — サイト共通の定型ナビ (冒頭「戻る」〜2 個目の <hr>、末尾の
     「感想はこちらに」フッタ〜「戻る」) を切り落とし、**本文領域**を確定する。
     ここで落とした文字列は report に記録し、後から監査できるようにする
  3. **本文領域は無損失証明で完全保護** — タグ・空白・山括弧を除き NFC 正規化した
     文字列が原本の本文領域と完全一致した話だけ Markdown を正典とする。
     一致しなければ raw フォールバック (原本 HTML のまま WordPress に載せる)

出力:
  bodies/<episode_id>.md            証明に通った話の本文 (Markdown)
  catalog/convert_report.jsonl      全話の判定 (status / mode / 証明結果 / 除去したクローム)
  catalog/reports/body_convert.json サマリ

使い方:
  python3 scripts/wp/body_convert.py                 # 全話を変換
  python3 scripts/wp/body_convert.py --limit 200     # 先頭 200 話だけ
  python3 scripts/wp/body_convert.py --id <episode_id>  # 1 話だけ (デバッグ用)
  python3 scripts/wp/body_convert.py --check         # 出力を再検証 (書き込まない)
"""
import os, re, sys, json, html, unicodedata, argparse, collections

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
EPISODES = os.path.join(ROOT, 'catalog', 'episodes.jsonl')
BODIES = os.path.join(ROOT, 'bodies')
REPORT = os.path.join(ROOT, 'catalog', 'convert_report.jsonl')
SUMMARY = os.path.join(ROOT, 'catalog', 'reports', 'body_convert.json')

# ---------------------------------------------------------------- 前処理

TAG_RE = re.compile(r'<[^>]+>')
SCRIPT_RE = re.compile(r'<script.*?</script>|<style.*?</style>|<!--.*?-->', re.S | re.I)
BOGUS_RE = re.compile(r'<![^>]*>', re.S)               # <! …> = ブラウザは不可視 (没稿隠しに使われている)
FORM_RE = re.compile(r'<form.*?</form>', re.S | re.I)  # 感想フォーム = クローム
BODY_RE = re.compile(r'<body[^>]*>(.*?)</body>', re.S | re.I)
# 変換後の Markdown に意図的に残す HTML (ルビ・画像・本物の表)
KEPT_TAG_RE = re.compile(
    r'</?(?:ruby|rb|rt|rp|rtc|img|table|thead|tbody|tfoot|tr|td|th|caption|col|colgroup|'
    r'a|b|i|u|s|em|strong|font|span|small|big|sub|sup|tt|code|pre|strike|blink|marquee|'
    r'br|hr|p|div|center|nobr|wbr|ul|ol|li|dl|dt|dd|h[1-6]|blockquote|basefont|abbr|acronym)'
    r'\b[^>]*>', re.I)

def preclean(body):
    """ブラウザ等価の前処理。原本側と変換側に同一に適用する"""
    body = SCRIPT_RE.sub('', body)
    body = BOGUS_RE.sub('', body)
    body = FORM_RE.sub('', body)
    body = re.sub(r'<(?![a-zA-Z/!])', '&lt;', body)     # 裸の '<' はテキスト
    return body

HEAD_RE = re.compile(r'(?is)^.*?</head\s*>')
BODY_OPEN_RE = re.compile(r'(?is)<body[^>]*>')
BODY_CLOSE_RE = re.compile(r'(?is)</body\s*>')

def extract_body(raw):
    """本文領域(<body> の中身)を取り出す。

    **順序が重要**: script/style/コメントを落としてから <body> を切る。逆にすると
    `<style>` が head/body 境界をまたぐ FrontPage 出力で、開始タグだけ head 側に残って
    CSS が本文に紛れ込む。また `</body>` を欠く文書 (29 話実在) でファイル全体に
    フォールバックすると `<title>` が本文の先頭に混入する — これは原本側も同じ誤りを
    するため**無損失証明では検出できない**(html5lib による外部検算で発覚)。"""
    src = preclean(raw)
    mo = BODY_OPEN_RE.search(src)
    if mo:
        rest = src[mo.end():]
        mc = BODY_CLOSE_RE.search(rest)
        region = rest[:mc.start()] if mc else rest
    else:
        mh = HEAD_RE.match(src)          # <body> が無い文書は </head> 以降を本文とみなす
        region = src[mh.end():] if mh else src
    # 末尾の欠けたタグ (`…</p` で終わる文書が実在) は '>' が無く TAG_RE で消えないので落とす
    region = re.sub(r'<[^>]*$', '', region)
    return region

def normalize_chars(s, already_unescaped=False):
    """無損失証明の比較キー = 「ブラウザに見えていた文字列」。

    **順序が重要**: タグ除去 → 実体参照の復元 の順に行う。逆にすると、本文中の裸の
    `<` (例: `ち<ゃんと` — preclean で `&lt;` にしてある) を復元したあとタグと誤認し、
    次の `>` までを丸ごと食って原本側だけ文字が消える (実際に 20 話で発生した)。
    実体参照を解くのは 1 回だけ。変換側は変換中に既に解いてあるので二重に解かない。"""
    if already_unescaped:
        # 変換側に残る HTML は「温存した断片」だけ。汎用のタグ除去を当てると、
        # 本文中の <右図> や <短編小説のページへ> (山括弧で囲んだ地の文。原本では
        # 実体参照か裸の '<') までタグと誤認して食う。実際に 100 話近くで起きた。
        s = KEPT_TAG_RE.sub('', s)
    else:
        s = TAG_RE.sub('', s)
        s = html.unescape(s)
    s = ''.join(ch for ch in s if not ch.isspace() and ch not in '<>')
    return unicodedata.normalize('NFC', s)

# ---------------------------------------------------------------- クローム除去

HR_RE = re.compile(r'(?i)<hr[^>]*>')
BACKLINK_RE = re.compile(r'(?is)<a[^>]*>[^<]{0,12}(戻る|もどる|BACK|back|目次|Index)[^<]{0,12}</a>')
KANSOU_FOOTER_RE = re.compile(
    r'(?is)(?:□|■|＊|\*|〓)\s*感想|感想\s*(?:は)?\s*こちら|kansou\.cgi|noteky\.cgi')

def strip_chrome(body):
    """サイト共通の定型ナビを落として本文領域を返す。
    戻り値 (本文領域, 落とした断片のリスト)。**保守的に**: 判定が付かないときは落とさない。"""
    removed = []
    hrs = [m.span() for m in HR_RE.finditer(body)]
    start, end = 0, len(body)

    # 冒頭: 「戻る」リンクが最初の 400 文字以内にあり、2 個目の <hr> までがヘッダ定型
    head = body[:1200]
    if BACKLINK_RE.search(head) and len(hrs) >= 2:
        cand = hrs[1][1]
        # ヘッダ定型は短い (題名+作者程度)。長すぎるなら本文を切る危険があるのでやめる
        if cand < 3000:
            removed.append(('head', body[:cand]))
            start = cand

    # 末尾: 最後の「戻る」リンク以降、または感想フッタ以降
    tail_from = None
    tail_zone_start = max(start, len(body) - 3000)
    tail_zone = body[tail_zone_start:]
    mk = KANSOU_FOOTER_RE.search(tail_zone)
    if mk:
        # 感想フッタの直前の <hr> があればそこから、なければフッタ自体から
        cut = tail_zone_start + mk.start()
        prev_hr = [e for s, e in hrs if e <= cut and e > start]
        tail_from = (prev_hr[-1] if prev_hr and cut - prev_hr[-1] < 600 else cut)
    else:
        mb = None
        for mb in BACKLINK_RE.finditer(tail_zone):
            pass
        if mb:
            cut = tail_zone_start + mb.start()
            prev_hr = [e for s, e in hrs if e <= cut and e > start]
            tail_from = (prev_hr[-1] if prev_hr and cut - prev_hr[-1] < 600 else cut)
    if tail_from is not None and tail_from > start:
        removed.append(('tail', body[tail_from:]))
        end = tail_from

    return body[start:end], removed

NAV_TOKEN = (r'戻る|もどる|返る|トップ(?:ページ)?|ホーム|目次|インデックス|index|Index|INDEX|top|TOP|'
             r'back|BACK|home|HOME|次(?:の話|話|へ|ページ)?|前(?:の話|話|へ|ページ)?|続き|'
             r'シリーズ(?:タイトル)?(?:一覧)?|本棚|玄関|書庫|文庫|少年少女文庫|作品一覧|一覧|'
             r'短編小説のページ|小説(?:の)?ページ|このページ|ページ|感想(?:は)?(?:こちら|どうぞ)?|'
             r'prev|next|PREV|NEXT|Prev|Next|ＮＥＸＴ|ＰＲＥＶ|前頁|次頁|表紙|扉|'
             r'感想掲示板|掲示板|ご意見|ご感想|メール|mail|MAIL|BBS|bbs|へ|に|を|の|と')
NAV_LINE_RE = re.compile(
    r'^(?:\s|　|[>＞<＜\[\]（）\(\)|｜/／・･,、。\-—―~〜*＊□■◆●○▲△▼▽☆★:：!！?？]|'
    + NAV_TOKEN + r')+$')

def trim_nav_lines(md):
    """**不変量の検証を通したあとで**、先頭・末尾の定型ナビ行だけを落とす。
    落とした行は記録して監査できるようにする(本文を削っていないかの確認用)。"""
    lines = md.split('\n')
    trimmed = []
    nav_word = re.compile(NAV_TOKEN)
    def is_nav(l):
        s = l.strip()
        # 記号だけの行 (本文中の「。」1 文字など) を落とさないよう、ナビ語を必須にする
        return (bool(s) and len(s) <= 40 and NAV_LINE_RE.match(s) is not None
                and nav_word.search(s) is not None)

    while lines:
        s = lines[0].strip()
        if not s:
            lines.pop(0)
        elif is_nav(s):
            trimmed.append(('head', s)); lines.pop(0)
        else:
            break
    while lines:
        s = lines[-1].strip()
        if not s or s == SENT['SEP']:
            lines.pop()
        elif is_nav(s):
            trimmed.append(('tail', s)); lines.pop()
        else:
            break
    return '\n'.join(lines).strip() + '\n', trimmed

CHROME_SAFE_RE = re.compile(
    r'^(?:[\s　]|<[^>]*>|&nbsp;|戻る|もどる|BACK|back|目次|Index|INDEX|TOP|top|ホーム|'
    r'感想|はこちら|こちら|に|を|どうぞ|□|■|＊|\*|〓|・|\||／|/|-|—|―|＞|>|＜|<|\[|\]|（|）|\(|\)|'
    r'このページ|作品|一覧|少年少女文庫|文庫|[0-9A-Za-z_.:?=&%#~/-])*$')

def chrome_is_safe(fragment):
    """落としたクロームが「ナビ・定型だけ」か (=本文を巻き込んでいないか) の検査"""
    text = html.unescape(TAG_RE.sub('', fragment))
    text = re.sub(r'\s+', '', text)
    if len(text) > 200:          # 200 文字を超えるなら本文混入を疑う
        return False
    return bool(CHROME_SAFE_RE.match(text))

# ---------------------------------------------------------------- 変換

PUNCT_END = tuple('。！？」』…―‐-!?.）)]々〉》』】♪☆★ 　')
SENT = {'SEP': '----', 'Q': '> '}

def table_kind(tbl):
    """本物の表 (仕様表など) か、レイアウト目的の table か"""
    cells = re.findall(r'(?is)<t[dh][^>]*>(.*?)</t[dh]>', tbl)
    if len(cells) >= 6:
        avg = sum(len(re.sub(r'\s', '', TAG_RE.sub('', c))) for c in cells) / max(1, len(cells))
        if avg < 24:
            return 'data'
    return 'layout'

def to_markdown(region):
    """本文領域 → (markdown, plain, mode, notes)。plain は証明用 (装飾センチネル抜き)"""
    notes = collections.Counter()
    keep = []                                   # インライン HTML として温存する断片
    def stash(m):
        # 温存断片は戻す時点では unescape 済みでなければならない。本文側の unescape は
        # 再フローの前に済ませるため、断片はここで解いておく (両側の対称性のため)
        keep.append(html.unescape(m.group(0)))
        return f'\x00{len(keep)-1}\x00'

    txt = region
    # 本物の表・ルビ・画像はインライン HTML のまま温存
    def table_sub(m):
        if table_kind(m.group(0)) == 'data':
            notes['table_kept'] += 1
            return stash(m)
        notes['table_unwrapped'] += 1
        inner = re.sub(r'(?is)</t[dhr]\s*>', '\n', m.group(0))
        return '\n' + TAG_RE.sub('', inner) + '\n'
    txt = re.sub(r'(?is)<table[^>]*>.*?</table>', table_sub, txt)
    # `</ryby>` のような閉じタグの誤記が実在するので許容する (許容しないと
    # 断片が閉じられず、マークアップが本文に露出して不変量違反になる)
    txt = re.sub(r'(?is)<ruby\b.*?</r[uy]by\s*>|<img[^>]*>', stash, txt)

    # ブロック要素 → 改行/段落
    txt = re.sub(r'(?i)</p\s*>', '\n\n', txt)
    txt = re.sub(r'(?i)<p[^>]*>', '\n\n', txt)
    txt = re.sub(r'(?i)<br[^>]*>', '\n', txt)
    txt = re.sub(r'(?i)<hr[^>]*>', '\n\n\x01SEP\x01\n\n', txt)
    txt = re.sub(r'(?is)<blockquote[^>]*>(.*?)</blockquote>',
                 lambda m: '\n\n' + '\n'.join('\x01Q\x01' + l for l in m.group(1).strip().split('\n')) + '\n\n', txt)
    txt = re.sub(r'(?is)<h([1-6])[^>]*>(.*?)</h\1>',
                 lambda m: f'\n\n\x01H{m.group(1)}\x01' + TAG_RE.sub('', m.group(2)).strip() + '\n\n', txt)
    txt = re.sub(r'(?is)</?(div|center|td|tr|th|table|li|dt|dd|ul|ol|dl)[^>]*>', '\n', txt)

    txt = TAG_RE.sub('', txt)
    txt = html.unescape(txt)          # 実体参照を解くのは 1 回だけ (normalize_chars と対称)
    # 温存断片 (ruby/img/本物の表) はプレースホルダのまま先へ送る。ここで戻すと、
    # 下の物理折返し再フローが行を連結する際にタグ内部で結合してしまい
    # (`<img\nSRC=…>` → `<imgSRC=…>`)、マークアップが本文に露出する

    lines = [l.rstrip() for l in txt.split('\n')]
    content = [l for l in lines if l.strip()]
    if not content:
        return None, None, 'empty', notes

    # 物理折返し (固定桁での強制改行) の検出と再フロー
    nonpunct = sum(1 for l in content if 15 <= len(l.strip()) <= 60 and not l.strip().endswith(PUNCT_END))
    hardwrap = len(content) > 30 and nonpunct / len(content) > 0.45
    if hardwrap:
        out, buf = [], ''
        for l in lines:
            ls = l.strip()
            if not ls:
                if buf: out.append(buf); buf = ''
                out.append('')
            elif buf and not buf.endswith(PUNCT_END) and not ls.startswith(('　', '「', '『', '（', '＜', '"', "'")):
                buf += ls
            else:
                if buf: out.append(buf)
                buf = ls
        if buf: out.append(buf)
        lines = out
        notes['hardwrap_reflowed'] += 1

    paras, buf = [], []
    for l in lines:
        if l.strip():
            buf.append(l)
        else:
            if buf: paras.append('\n'.join(buf)); buf = []
    if buf: paras.append('\n'.join(buf))
    plain = '\n\n'.join(paras)

    def restore(s):
        return re.sub(r'\x00(\d+)\x00', lambda m: keep[int(m.group(1))], s)

    # 表示用 md: 地の文に残る裸の '<' '>' を再エスケープしてから温存断片を戻す。
    # (`<mailto>` `<m(__)m>` のような山括弧付きの地の文が、WordPress でタグとして
    #  解釈されて消えるのを防ぐ。温存断片は本物の HTML なのでエスケープ対象外)
    md = plain.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    md = restore(md)
    plain = restore(plain)            # 証明用は素のまま (normalize_chars が山括弧を落とす)

    md = md.replace('\x01SEP\x01', SENT['SEP']).replace('\x01Q\x01', SENT['Q'])
    md = re.sub(r'\x01H([1-6])\x01', lambda m: '#' * int(m.group(1)) + ' ', md)
    md = re.sub(r'\n{3,}', '\n\n', md).strip() + '\n'
    plain = re.sub(r'\x01(?:SEP|Q|H[1-6])\x01', '', plain)

    mode = 'hardwrap' if hardwrap else ('table' if notes['table_kept'] else 'br-para')
    return md, plain, mode, notes

# ---------------------------------------------------------------- 話ごとの処理

IMG_EXT = ('.jpg', '.jpeg', '.gif', '.png', '.bmp')

def read_text(path):
    """ミラーは UTF-8 変換済みだが、変換対象外だった .txt に当時のままのものが残る"""
    data = open(path, 'rb').read()
    for enc in ('utf-8', 'cp932', 'euc_jp'):
        try:
            return data.decode(enc), enc
        except UnicodeDecodeError:
            continue
    return data.decode('cp932', 'replace'), 'cp932-replace'

def convert_episode(ep):
    """1 話を変換して結果 dict を返す (書き込みはしない)"""
    eid = ep['episode_id']
    sp = ep.get('source_path') or ''
    res = {'episode_id': eid, 'source_path': sp, 'corpus': ep.get('corpus')}

    # (1) 外部再掲などプレーンテキスト由来 — reposts/ に本文がある
    rp = ep.get('body_text_path') or ep.get('repost_text_path')
    if rp:
        p = os.path.join(ROOT, rp)
        if os.path.isfile(p):
            text = open(p, encoding='utf-8').read().strip()
            paras = [b.strip() for b in re.split(r'\n\s*\n', text) if b.strip()]
            md = '\n\n'.join(paras) + '\n'
            res.update(status='md', mode='plain-text', invariant='n/a-plaintext',
                       md=md, chars=len(normalize_chars(md, already_unescaped=True)))
            return res
    if not sp:
        res.update(status='no-source', reason='source_path なし')
        return res

    full = os.path.join(ROOT, sp)
    # (2) 画像作品 — 本文は figure 1 枚
    if sp.lower().endswith(IMG_EXT):
        if os.path.isfile(full):
            res.update(status='md', mode='image-work', invariant='n/a-image',
                       md=f'![{ep.get("title","")}]({os.path.basename(sp)})\n')
        else:
            res.update(status='no-source', reason='画像ファイルが未回収')
        return res
    # (3) 外部 URL スタブ
    if sp.startswith(('http://', 'https://')):
        res.update(status='stub', mode='external-link', reason='外部 URL の掲載')
        return res
    if not os.path.isfile(full):
        res.update(status='no-source', reason='原本ファイルが未回収')
        return res

    raw, enc = read_text(full)
    # (3b) プレーンテキストの収蔵物 (.txt) — HTML ではないので段落分割だけ
    if sp.lower().endswith('.txt'):
        paras = [b.strip() for b in re.split(r'\n\s*\n', raw.strip()) if b.strip()]
        md = '\n\n'.join(paras) + '\n'
        res.update(status='md', mode='plain-text', invariant='ok', source_encoding=enc,
                   md=md, chars=len(normalize_chars(md, already_unescaped=True)))
        return res
    body = extract_body(raw)

    # (4) アンカー分割 (1 ファイルに複数話が同居)
    anchor = ep.get('source_anchor')
    if anchor:
        body, ok = split_by_anchor(body, anchor)
        if not ok:
            res.update(status='raw-fallback', mode='anchor-split-failed',
                       reason=f'アンカー {anchor} の区間を特定できない')
            return res

    region, removed = strip_chrome(body)
    unsafe = [k for k, frag in removed if not chrome_is_safe(frag)]
    if unsafe:                                   # 本文を巻き込む恐れ → 除去せずやり直す
        region, removed = body, []

    md, plain, mode, notes = to_markdown(region)
    if md is None:
        res.update(status='raw-fallback', mode='empty', reason='本文が空')
        return res

    ok = normalize_chars(region) == normalize_chars(plain, already_unescaped=True)
    res.update(mode=mode, notes={k: v for k, v in notes.items()},
               chrome_removed=[{'where': k, 'text': html.unescape(TAG_RE.sub('', f))[:80].strip()}
                               for k, f in removed],
               chars=len(normalize_chars(region)))
    if ok:
        md, navtrim = trim_nav_lines(md)
        res.update(status='md', invariant='ok', md=md,
                   nav_trimmed=[{'where': w, 'text': t[:60]} for w, t in navtrim])
    else:
        a, b = normalize_chars(region), normalize_chars(plain, already_unescaped=True)
        i = 0
        while i < min(len(a), len(b)) and a[i] == b[i]:
            i += 1
        res.update(status='raw-fallback', invariant='mismatch',
                   reason=f'不変量違反 orig={len(a)} conv={len(b)} 分岐={i}',
                   diverge_orig=a[max(0, i-20):i+40], diverge_conv=b[max(0, i-20):i+40])
    return res

def split_by_anchor(body, anchor):
    """<a name="X"> から次の <a name> までを切り出す"""
    names = [(m.start(), m.group(1)) for m in
             re.finditer(r'(?is)<a[^>]*\sname\s*=\s*["\']?([^"\'>\s]+)', body)]
    for i, (pos, nm) in enumerate(names):
        if nm.lower() == anchor.lower().lstrip('#'):
            end = names[i+1][0] if i + 1 < len(names) else len(body)
            return body[pos:end], True
    return body, False

# ---------------------------------------------------------------- 実行

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int)
    ap.add_argument('--id')
    ap.add_argument('--check', action='store_true', help='書き込まずに判定だけ行う')
    args = ap.parse_args()

    eps = [json.loads(l) for l in open(EPISODES, encoding='utf-8')]
    if args.id:
        eps = [e for e in eps if e['episode_id'] == args.id]
    if args.limit:
        eps = eps[:args.limit]

    if not args.check:
        os.makedirs(BODIES, exist_ok=True)
        os.makedirs(os.path.dirname(SUMMARY), exist_ok=True)

    stats = collections.Counter()
    modes = collections.Counter()
    rows = []
    for ep in eps:
        try:
            r = convert_episode(ep)
        except Exception as e:
            r = {'episode_id': ep['episode_id'], 'source_path': ep.get('source_path'),
                 'status': 'raw-fallback', 'mode': 'exception', 'reason': f'{type(e).__name__}: {e}'}
        stats[r['status']] += 1
        modes[r.get('mode', '-')] += 1
        md = r.pop('md', None)
        if md is not None and not args.check:
            out = os.path.join(BODIES, r['episode_id'] + '.md')
            with open(out, 'w', encoding='utf-8') as f:
                f.write(md)
        rows.append(r)

    if not args.check:
        with open(REPORT, 'w', encoding='utf-8') as f:
            for r in rows:
                f.write(json.dumps(r, ensure_ascii=False) + '\n')

    convertible = stats['md'] + stats['raw-fallback']
    rate = stats['md'] / convertible * 100 if convertible else 0.0
    summary = {
        'episodes': len(eps),
        'status': dict(stats),
        'modes': dict(modes),
        'convertible': convertible,
        'md_rate_pct': round(rate, 2),
        'acceptance': {'>=85% of convertible': rate >= 85.0,
                       'invariant violations among md': 0},
    }
    if not args.check:
        json.dump(summary, open(SUMMARY, 'w'), ensure_ascii=False, indent=1)
    print(json.dumps(summary, ensure_ascii=False, indent=1))
    # 不合格の代表例
    bad = [r for r in rows if r['status'] == 'raw-fallback'][:8]
    for r in bad:
        print('  FALLBACK', r.get('mode'), r.get('source_path'), '|', str(r.get('reason'))[:90])

if __name__ == '__main__':
    main()
