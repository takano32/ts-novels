# -*- coding: utf-8 -*-
"""htmltext.py — HTML 断片の小道具 (タグ除去・アンカー走査・表示テキスト化)。

原本の HTML は 1 行に長大に詰まっていて壊れた入れ子もあるので、木を作らずフラットに
正規表現で走査する (常に findall / finditer。行単位パースは禁止)。

表示テキスト化は 3 つの流儀がある。段ごとに期待する形が違うので別名で持つ:
  cell_text    目録のセル用。<BR> を改行に落とし、行ごとに strip (catalog_build 由来)
  inline_text  タグを消して空白を 1 つに畳む (authors_build 由来)
  spaced_text  タグを空白に置き換えてから畳む (work_builder 由来。隣接セルの語が結合しない)
"""
import html
import re

RE_TAG = re.compile(r'<[^>]+>')
RE_BR = re.compile(r'<br\s*/?>', re.I)
RE_COMMENT = re.compile(r'<!--.*?-->', re.S)
# href は "…" / '…' / 裸 の 3 形をまとめて拾う
RE_ANCHOR = re.compile(
    r'<a\s+[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>', re.S | re.I)
RE_TR = re.compile(r'<TR[^>]*>(.*?)</TR>', re.S | re.I)
RE_TD = re.compile(r'<TD[^>]*>(.*?)</TD>', re.S | re.I)
RE_SCHEME = re.compile(r'^[a-zA-Z][a-zA-Z0-9+.\-]*://')


def strip_tags(s):
    return RE_TAG.sub('', s)


def href_of(groups):
    """RE_ANCHOR の findall の 1 件 (4 組) から href を取り出す (実体参照を解いて前後の空白を落とす)。"""
    return html.unescape((groups[0] or groups[1] or groups[2] or '').strip())


def cell_text(fragment, keep_breaks=True):
    """HTML 断片 → 表示テキスト。<BR> は改行に落とす。空なら None。"""
    if fragment is None:
        return None
    s = RE_BR.sub('\n' if keep_breaks else ' ', fragment)
    s = strip_tags(s)
    s = html.unescape(s)
    s = s.replace('\r\n', '\n').replace('\r', '\n')
    s = '\n'.join(line.strip() for line in s.split('\n')).strip()
    s = s.strip('　').strip()
    return s or None


def inline_text(fragment):
    """タグを消し、空白を 1 つに畳む。空なら None。"""
    if fragment is None:
        return None
    s = RE_TAG.sub('', fragment)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip() or None


def spaced_text(fragment):
    """タグを空白に置き換えてから畳む。空なら None。"""
    if fragment is None:
        return None
    s = html.unescape(RE_TAG.sub(' ', fragment))
    return re.sub(r'\s+', ' ', s).strip() or None


def anchors(fragment):
    """フラットな <a> 走査。壊れた入れ子 <A> があっても落ちない。
    戻り値 [{'href', 'label', 'span'}]。label は cell_text(keep_breaks=False)。"""
    out = []
    for m in RE_ANCHOR.finditer(fragment or ''):
        href = m.group(1) or m.group(2) or m.group(3) or ''
        href = html.unescape(href.strip())
        label = cell_text(m.group(4), keep_breaks=False)
        out.append({'href': href, 'label': label, 'span': m.span()})
    return out


def is_absolute_url(href):
    return bool(RE_SCHEME.match(href or ''))


def norm_path(href):
    """リンク先をリポジトリ相対パスに正規化する。アンカーと先頭の ./ を落とし、
    外部 URL なら None、空なら None。"""
    href = html.unescape((href or '').strip()).split('#')[0]
    href = re.sub(r'^(?:\./)+', '', href)
    if is_absolute_url(href):
        return None
    return href or None
