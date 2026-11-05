# -*- coding: utf-8 -*-
"""kana_index.py — 作家別五十音目録 (`lib-index-*.html`) の解釈 (T45.2.16)。

題名 → 作者の第二情報源として、作者の同定 (tslib/authors.py) と目録外収蔵 (tslib/uncatalogued.py)
の 2 段が使う。段のモジュールどうしが import し合わないよう、ここに出した。
表示名の突き合わせ鍵 norm_name も、読み (yomi) の鍵に使うのでここに置く。
"""
import os
import re
import unicodedata
from typing import NamedTuple

from tslib.fileio import read_text
from tslib.htmltext import RE_TAG, RE_ANCHOR, href_of, inline_text

RE_H2 = re.compile(r'<H2>\s*-?\s*(.*?)\s*-?\s*</H2>', re.I)
RE_ROW = re.compile(r'<TR[^>]*>\s*<TD[^>]*>(.*?)</TD>\s*<TD[^>]*>(.*?)</TD>\s*</TR>', re.S | re.I)
INDEX_PAGES = ['lib-index-%s.html' % s for s in
               ('aa', 'ka', 'sa', 'ta', 'na', 'ha', 'ma', 'ya', 'ra', 'wa', 'en', 'etc')]
INDEX_PAGES_OLD = ['lib-index-%d.html' % i for i in range(1, 5)]


def norm_name(name):
    """表示名の突き合わせ鍵。NFKC + 空白除去 + casefold。"""
    if not name:
        return ''
    s = unicodedata.normalize('NFKC', name)
    s = re.sub(r'[\s　]+', '', s)
    return s.casefold()


# --------------------------------------------------------------------------- lib-index

def parse_index_old_html(page, src):
    """lib-index-1〜4.html (旧世代版) は列の並びが逆で、作者セルが rowspan で
    複数行にまたがる: `<td rowspan=N><b>作者</b></td><td><a>題名</a></td>` +
    続く N-1 行は題名セルだけ。作者を持ち越しながら読む。"""
    rows, author, remain = [], None, 0
    for m in re.finditer(r'<tr[^>]*>(.*?)</tr>', src, re.S | re.I):
        cells = re.findall(r'<td([^>]*)>(.*?)</td>', m.group(1), re.S | re.I)
        if not cells:
            continue
        if len(cells) >= 2:
            attrs, body = cells[0]
            name = inline_text(body)
            if name:
                author = name
                span = re.search(r'rowspan\s*=\s*"?(\d+)', attrs, re.I)
                remain = (int(span.group(1)) if span else 1) - 1
            title_cell = cells[1][1]
        else:
            if remain <= 0:
                continue
            remain -= 1
            title_cell = cells[0][1]
        links = RE_ANCHOR.findall(title_cell)
        if not links or not author:
            continue
        href = href_of(links[0])
        title = inline_text(links[0][3])
        if not href or not title:
            continue
        rows.append({'page': page, 'path': href, 'title': title,
                     'author': author, 'homepage': None, 'kana': None})
    return rows


def parse_index_html(page, src, yomi):
    """lib-index-aa〜etc.html (現行版) の 1 ページ → rows。yomi (表示名 → 所属行) に追記する。"""
    rows = []
    heads = [(m.start(), inline_text(m.group(1))) for m in RE_H2.finditer(src)]
    for m in RE_ROW.finditer(src):
        links = RE_ANCHOR.findall(m.group(1))
        if not links:
            continue
        href = href_of(links[0])
        title = inline_text(links[0][3])
        cell = m.group(2)
        homepage = None
        for a in RE_ANCHOR.finditer(cell):
            url = href_of(a.groups())
            if url.lower().startswith('mailto:'):
                continue
            if url:
                homepage = url
        author = inline_text(re.sub(r'\(\s*homepage\s*\)', '', RE_TAG.sub('|', cell), flags=re.I)
                         .replace('|', ' '))
        if not author or not title:
            continue
        kana = None
        for pos, name in heads:
            if pos < m.start():
                kana = name
        rows.append({'page': page, 'path': href, 'title': title,
                     'author': author, 'homepage': homepage, 'kana': kana})
        if kana:
            yomi.setdefault(norm_name(author), kana)
    return rows


class KanaIndex(NamedTuple):
    """parse_index の戻り値。タプルとして (rows, yomi_of) に展開できる。"""
    rows: list           # [{path, title, author, homepage, kana, page}]
    yomi_of: dict        # 表示名 → 所属行 (あ行/A/その他)


def parse_index(root):
    """作家別五十音目録 → KanaIndex (rows, yomi_of_name)。

    rows = [{path, title, author, homepage, kana, page}] — 題名→作者の第二情報源
    (T7.8 の目録外収蔵でも使う)。yomi_of_name は表示名 → 所属行 (あ行/A/その他)。
    """
    rows, yomi = [], {}
    for page in INDEX_PAGES_OLD:
        path = os.path.join(root, page)
        if os.path.exists(path):
            rows.extend(parse_index_old_html(page, read_text(path)))
    for page in INDEX_PAGES:
        path = os.path.join(root, page)
        if os.path.exists(path):
            rows.extend(parse_index_html(page, read_text(path), yomi))
    return KanaIndex(rows, yomi)
