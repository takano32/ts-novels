# -*- coding: utf-8 -*-
"""fragments.py — 目録エントリの HTML 断片を扱う小道具。

すべて大文字小文字無視 + DOTALL。単引用符 href・壊れた入れ子 <A> に耐えるフラット走査。
"""
import re
import unicodedata

from tslib.htmltext import (RE_TAG, RE_BR, RE_ANCHOR, RE_TR, RE_TD, RE_COMMENT,  # noqa: F401
                            strip_tags, cell_text, anchors)

text_of = cell_text          # 目録のセル用の表示テキスト化 (従来名)
RE_COMMENT_HTML = RE_COMMENT

RE_BOLD = re.compile(r'<B>(.*?)</B>', re.S | re.I)
RE_NAV_LABEL = re.compile(r'^【(.+)】$', re.S)
RE_SPLIT = re.compile(r'[\s　]+')
# 行 2・3 のマーカーは <B> で囲まれ、行 4 のマーカーは囲まれていない。
# 値の切り出しを止める位置もそれに合わせる (行 3 の値末尾にある 【…】 ナビリンクを
# マーカーと誤認して切り落とさないため)。
STOP_BOLD = re.compile(r'<B>\s*【', re.I)
STOP_PLAIN = re.compile(r'【')

MARKERS = {
    'arasuji': '【あらすじ】',
    'comment': '【コメント】',
    'suisen': '【推薦文】',
    'genre': '【ジャンル】',
    'type': '【種別】',
    'keywords': '【キーワード】',
    'zokusei': '【属性】',          # 旧目録 lib07-09 のみ (T7.2 用)
    'comment_legacy': '【作者様コメント】',
}


class ParseError(Exception):
    """1 エントリの構造が仕様から外れた。受け入れ条件「パース失敗 0」の検出点。"""


def split_tokens(value):
    """多値欄を ASCII 空白 + U+3000 で分割。NFKC 正規化前後の両方を返す。"""
    if not value:
        return [], []
    raw = [t for t in RE_SPLIT.split(value.replace('\n', ' ')) if t]
    norm = []
    for t in raw:
        n = unicodedata.normalize('NFKC', t).strip()
        if n:
            norm.append(n)
    return norm, raw


def marker_value(cell, marker, stop=STOP_BOLD):
    """`【マーカー】 値` を次のマーカーの直前まで切り出す。マーカー不在なら KeyError。"""
    i = cell.find(marker)
    if i < 0:
        raise KeyError(marker)
    rest = cell[i + len(marker):]
    # マーカー直後の `</B>` は値ではない
    m = re.match(r'\s*</B>', rest, re.I)
    if m:
        rest = rest[m.end():]
    s = stop.search(rest)
    return rest[:s.start()] if s else rest


def marker_values(cell, keys, stop=STOP_BOLD):
    """複数のマーカーをまとめて切り出す。無いものは None (旧目録の寛容なパース用)。"""
    out = {}
    for key, marker in keys:
        try:
            out[key] = marker_value(cell, marker, stop)
        except KeyError:
            out[key] = None
    return out


def drop_nav_anchors(fragment):
    """ラベルが 【…】 のアンカー (話ナビ) を表示テキストから外す。リンク自体は
    nav_links に別途構造化して持つので、本文欄には残さない。"""
    out, last = [], 0
    for m in RE_ANCHOR.finditer(fragment or ''):
        label = cell_text(m.group(4), keep_breaks=False)
        if label and RE_NAV_LABEL.match(label):
            out.append(fragment[last:m.start()])
            last = m.end()
    out.append(fragment[last:])
    return ''.join(out)


def nav_links_of(cell):
    """【第N話はこちら】形のナビリンク。ラベルが 【】 で囲まれたものだけを採る。"""
    out = []
    for a in anchors(cell):
        m = RE_NAV_LABEL.match(a['label'] or '')
        if m:
            out.append({'href': a['href'], 'label': m.group(1)})
    return out


def plain_links_of(cell):
    """【】 で囲まれていない本文内リンク (旧世代ページのあらすじ内リンク等)。"""
    out = []
    for a in anchors(cell):
        if not RE_NAV_LABEL.match(a['label'] or ''):
            out.append({'href': a['href'], 'label': a['label']})
    return out


def body_text(fragment):
    """表示用の本文欄: 【…】 ナビリンクを外してから表示テキストに。空なら None。"""
    return cell_text(drop_nav_anchors(fragment)) if fragment else None
