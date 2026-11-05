# -*- coding: utf-8 -*-
"""mailto.py — 目録エントリから mailto 由来の値を根こそぎ落とす。"""
import html
import re

from tslib.htmltext import strip_tags
from tslib.mail import RE_BARE_MAIL

RE_MAILTO = re.compile(
    r'<a\s+[^>]*?href\s*=\s*["\']?mailto:([^"\'>\s]*)["\']?[^>]*>(.*?)</a>', re.S | re.I)
RE_MAILTO_ATTR = re.compile(r'href\s*=\s*["\']?mailto:([^"\'>\s]*)["\']?', re.I)


def scrub_mailto(fragment):
    """mailto 由来の値をエントリ HTML から根こそぎ落とす (S10 のメールアドレスを DB に入れない決まり)。

    戻り値 (scrubbed_html, dropped_addresses)。
      - `<a href="mailto:X">LABEL</a>` は LABEL だけを残す。LABEL 自体がアドレスなら消す
      - 閉じ忘れ等で残った `href="mailto:…"` 属性も潰す
      - 素のアドレス表記も落とす (RE_BARE_MAIL。ファイル名の @ は除外済み)
    """
    dropped = []

    def rep(m):
        addr = html.unescape(m.group(1) or '').strip()
        if addr:
            dropped.append(addr)
        label = m.group(2)
        plain = strip_tags(html.unescape(label)).strip()
        if not plain or RE_BARE_MAIL.fullmatch(plain):
            if plain:
                dropped.append(plain)
            return ''
        return label

    out = RE_MAILTO.sub(rep, fragment)

    def rep_attr(m):
        addr = html.unescape(m.group(1) or '').strip()
        if addr:
            dropped.append(addr)
        return 'href="#"'

    out = RE_MAILTO_ATTR.sub(rep_attr, out)

    def rep_bare(m):
        dropped.append(m.group(0))
        return ''

    out = RE_BARE_MAIL.sub(rep_bare, out)
    return out, dropped
