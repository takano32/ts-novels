# -*- coding: utf-8 -*-
"""verify_html5.py — bodies/*.md の無損失性を html5lib で検算するロジック (body_convert の第二実装)。

**独立検算の決まり (specs/S12.md)**: このモジュールは本体の変換系 (tslib/bodies.py・
tslib/payloads.py・tslib/htmltext.py) を import しない。同じ思い込みで両側を間違えないよう、
原本の解釈は html5lib (HTML5 のパース仕様どおり) に任せる。共有してよいのは読み書き
(tslib/fileio.py・tslib/paths.py・tslib/gitinfo.py) だけ。

比較のしかたは scripts/wp/verify_bodies_html5.py の先頭に書いてある。入出力・並列実行・表示は
そのスクリプトで、ここは 1 話の検算 (verify) とその部品だけ。

beautifulsoup4 / html5lib が無ければ BeautifulSoup は None (読み込みでは落とさない。
スクリプトは無ければ案内を出して止まり、試験は飛ばす)。
"""
import collections
import os
import re
import unicodedata

from tslib.fileio import read_text_guess

try:
    from bs4 import BeautifulSoup
except ImportError:                                       # pragma: no cover
    BeautifulSoup = None

# 前置き/後置きが「ナビ定型だけ」と言い切れる上限。body_convert の chrome_is_safe と同じ 200 字
CHROME_BUDGET = 200


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
    """1 話の検算。job = (root, bodies, episode_id, source_path, mode, source_anchor,
    chrome_removed の文字列の列, nav_trimmed の文字列の列)。ProcessPoolExecutor に渡すので
    モジュールの最上位に置き、置き場 (root と bodies/) も job に含める。"""
    root, bodies, eid, src, mode, anchor, chrome_removed, nav_trimmed = job
    r = {'episode_id': eid, 'source_path': src, 'mode': mode}
    mdpath = os.path.join(bodies, eid + '.md')
    if not os.path.isfile(mdpath):
        r.update(result='no-md')
        return r
    full = os.path.join(root, src)
    if not os.path.isfile(full):
        r.update(result='no-source')
        return r

    try:
        raw, _ = read_text_guess(full)
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
