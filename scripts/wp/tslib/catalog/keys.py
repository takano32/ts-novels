# -*- coding: utf-8 -*-
"""keys.py — 目録の href から source_path・episode_id・URL を導く規則 (冪等キー)。"""
import re
import urllib.parse

from tslib.htmltext import RE_SCHEME
from tslib.paths import ORIG_BASE


def split_href(href):
    """目録の href → (source_path, source_anchor, source_kind)。

    source_kind:
      local    … リポジトリ相対パス (原サイトの URL 空間そのまま)
      external … 当時から外部サイトを指していた 6 件。原 URL を保つため
                 `external/<host><path>` という合成パスを与える (episode_id 用の安定キー)
    """
    anchor = None
    if '#' in href:
        href, anchor = href.split('#', 1)
        anchor = anchor or None
    if RE_SCHEME.match(href):
        m = re.match(r'^[a-zA-Z][a-zA-Z0-9+.\-]*://([^/]+)(/.*)?$', href)
        host, path = m.group(1), (m.group(2) or '/')
        return 'external/' + host + path, anchor, 'external'
    return re.sub(r'^(?:\./)+', '', href), anchor, 'local'


def episode_id_of(source_path, anchor, disambiguator=None):
    """episode_id = source_path の `/` を `__` に置換し、anchor があれば `@<anchor>`。

    目録には **同じファイルを指す別エントリ**が実在する (シリーズタイトルページに
    各話がリンクしている「生命戦隊トランスギャルズ」型・改訂版の再掲型)。
    そのため path+anchor だけでは一意にならず、衝突したグループは全員に
    `+<掲載日 YYYYMMDD>` を付けて区別する (どれか 1 つだけに付けると、
    エントリの増減で他の id が動くため、グループ全員に付ける)。
    """
    eid = source_path.replace('/', '__')
    if anchor:
        eid += '@' + anchor
    if disambiguator:
        eid += '+' + disambiguator
    return eid


def url_path(path):
    """パスを URL に埋める形にする。`Winter sea.htm` の空白等を percent-encode し、
    `~` `@` `&` (実在するファイル名) はそのまま残す。"""
    return '/'.join(urllib.parse.quote(seg, safe="!$&'()*+,;=:@~") for seg in path.split('/'))


def entry_type_of(source_path, kind):
    if source_path.lower().endswith(('.jpg', '.jpeg', '.gif', '.png')):
        return 'image'
    if kind == 'external':
        return 'external'
    return 'html'


def orig_url_of(source_path, anchor, kind):
    """旧サイトの URL。external は合成パス `external/<host>/…` を http:// に戻す。"""
    url = (ORIG_BASE + url_path(source_path) if kind == 'local'
           else re.sub(r'^external/', 'http://', url_path(source_path)))
    if anchor:
        url += '#' + anchor
    return url


def annex_url_of(annex_base, source_path, anchor=None):
    """原本アーカイブ上の URL (ファイルが実在するときだけ呼ぶ)。"""
    return annex_base + url_path(source_path) + ('#' + anchor if anchor else '')
