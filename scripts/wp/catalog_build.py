#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""catalog_build.py — 正規目録 lib1.html〜lib73.html を catalog/episodes.jsonl へ正規化する。

進行台帳 docs/wp-implementation-tasks.md のタスク 1.1 の本実装。
仕様は scripts/workflows/wp-survey-2026-08-30.json の catalog 節 (fields / parse_strategy)。

  - エントリ = `<TABLE BORDER=1 …>` 1 つ。必ず 4 行、1 行目は 6 セル。
    HTML は 1 行に長大に詰まっているので行単位パースは禁止 (常に findall)。
  - 冪等キー = source_path + source_anchor。episode_id = source_path の `/` を `__` に
    置換し、anchor があれば `@<anchor>` を付けたもの。
  - mailto はこの段階で捨てる (WP の DB にメールアドレスを構造的に入れないため)。
    --selftest が「捨てたはずのアドレスが出力に残っていないこと」をロジックで検査する。

使い方:
    python3 scripts/wp/catalog_build.py                 # 生成 + 自己検査 + レポート
    python3 scripts/wp/catalog_build.py --check         # 何も書かずに検査だけ
    python3 scripts/wp/catalog_build.py --no-provenance # git 走査を省く (高速)

旧目録 lib01〜09 (corpus=legacy) はタスク 1.2 の担当でありここには未実装。
"""
import argparse
import collections
import html
import json
import os
import re
import subprocess
import sys
import urllib.parse
import unicodedata

# --------------------------------------------------------------------------- 定数

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ORIG_BASE = 'http://ts.novels.jp/'
# アネックス = 現行 GitHub Pages ミラー (設計 v1.1 で恒久併存が決定)。
# リポジトリは takano32/ts-novels、カスタムドメイン (CNAME) なし。
DEFAULT_ANNEX_BASE = 'https://takano32.github.io/ts-novels/'
YAYS_PREFIX = '~yays/library/'          # 2000-02 年の旧世代ツリー (初出版)
EXPECTED_ENTRIES = 2887                 # 受け入れ条件 (survey 実測)

# --------------------------------------------------------------------------- 正規表現
# すべて大文字小文字無視 + DOTALL。単引用符 href・壊れた入れ子 <A> に耐えるフラット走査。

RE_TABLE = re.compile(r'<TABLE\s+BORDER=1.*?</TABLE>', re.S | re.I)
RE_TR = re.compile(r'<TR[^>]*>(.*?)</TR>', re.S | re.I)
RE_TD = re.compile(r'<TD[^>]*>(.*?)</TD>', re.S | re.I)
RE_TAG = re.compile(r'<[^>]+>')
RE_BR = re.compile(r'<br\s*/?>', re.I)
RE_BOLD = re.compile(r'<B>(.*?)</B>', re.S | re.I)
# href は "…" / '…' / 裸 の 3 形をまとめて拾う
RE_ANCHOR = re.compile(
    r'<a\s+[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>', re.S | re.I)
RE_MAILTO = re.compile(
    r'<a\s+[^>]*?href\s*=\s*["\']?mailto:([^"\'>\s]*)["\']?[^>]*>(.*?)</a>', re.S | re.I)
RE_ILLUST = re.compile(r'<B>(.*?)</B>\s*[(（]\s*<B>(.*?)</B>\s*さん\s*[)）]', re.S | re.I)
RE_DATE = re.compile(r'^(\d{4})/(\d{1,2})/(\d{1,2})(?:[(（]([月火水木金土日])[)）])?$')
RE_SIZE = re.compile(r'^(\d+)\s?KB(?:\s*/\s*(\d+)FILES)?$', re.I)
RE_KANSOU = re.compile(r'~ts/kansou/bbs@log_([A-Za-z0-9_\-]+)\.cgi', re.I)
# 推薦者名に 【】 は入らない。`.+?` のままだと直前の 【あらすじ】 から食い始めてしまう。
RE_OSUSUME_HEAD = re.compile(r'【([^【】]+?)\s*さんのオススメ作品】\s*</B>\s*(.*?)(?=<B>\s*【|\Z)', re.S | re.I)
RE_OSUSUME_AUTHOR = re.compile(r'[(（]\s*(.*?)\s*さん作\s*[)）]', re.S)
RE_NAV_LABEL = re.compile(r'^【(.+)】$', re.S)
RE_SPLIT = re.compile(r'[\s　]+')
# 行 2・3 のマーカーは <B> で囲まれ、行 4 のマーカーは囲まれていない。
# 値の切り出しを止める位置もそれに合わせる (行 3 の値末尾にある 【…】 ナビリンクを
# マーカーと誤認して切り落とさないため)。
STOP_BOLD = re.compile(r'<B>\s*【', re.I)
STOP_PLAIN = re.compile(r'【')
# mailto から拾ったのではない「素のアドレス表記」。`index@08201937.html` 型の
# ファイル名や `bbs@log_johdan.cgi` を誤検知しないよう、拡張子で終わる形を除外する。
RE_BARE_MAIL = re.compile(
    r'[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.'
    r'(?!s?html?\b|cgi\b|jpe?g\b|gif\b|png\b|txt\b|js\b)[A-Za-z]{2,}')

MARKERS = {
    'arasuji': '【あらすじ】',
    'comment': '【コメント】',
    'suisen': '【推薦文】',
    'genre': '【ジャンル】',
    'type': '【種別】',
    'keywords': '【キーワード】',
    'zokusei': '【属性】',          # 旧目録 lib07-09 のみ (1.2 用)
    'comment_legacy': '【作者様コメント】',
}


class ParseError(Exception):
    """1 エントリの構造が仕様から外れた。受け入れ条件「パース失敗 0」の検出点。"""


# --------------------------------------------------------------------------- 小道具

def strip_tags(s):
    return RE_TAG.sub('', s)


def text_of(fragment, keep_breaks=True):
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


def anchors(fragment):
    """フラットな <a> 走査。壊れた入れ子 <A> があっても落ちない。"""
    out = []
    for m in RE_ANCHOR.finditer(fragment or ''):
        href = m.group(1) or m.group(2) or m.group(3) or ''
        href = html.unescape(href.strip())
        label = text_of(m.group(4), keep_breaks=False)
        out.append({'href': href, 'label': label, 'span': m.span()})
    return out


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


def drop_nav_anchors(fragment):
    """ラベルが 【…】 のアンカー (話ナビ) を表示テキストから外す。リンク自体は
    nav_links に別途構造化して持つので、本文欄には残さない。"""
    out, last = [], 0
    for m in RE_ANCHOR.finditer(fragment or ''):
        label = text_of(m.group(4), keep_breaks=False)
        if label and RE_NAV_LABEL.match(label):
            out.append(fragment[last:m.start()])
            last = m.end()
    out.append(fragment[last:])
    return ''.join(out)


def scrub_mailto(fragment):
    """mailto 由来の値をエントリ HTML から根こそぎ落とす (普遍ルール 4)。

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

    out = re.sub(r'href\s*=\s*["\']?mailto:([^"\'>\s]*)["\']?', rep_attr, out, flags=re.I)

    def rep_bare(m):
        dropped.append(m.group(0))
        return ''

    out = RE_BARE_MAIL.sub(rep_bare, out)
    return out, dropped


# --------------------------------------------------------------------------- 回収経路 (provenance)

# git のコミット件名 → 回収経路。上から順に最初に当たったものを採る。
ROUTE_RULES = [
    ('megalodon', ('ウェブ魚拓', '魚拓'), 'ウェブ魚拓'),
    ('commoncrawl', ('CommonCrawl',), 'CommonCrawl'),
    ('narou', ('なろう',), '作者本人のなろうアカウント'),
    ('pixiv', ('pixiv',), '作者本人の pixiv 再掲'),
    ('wayback', ('Wayback',), 'Internet Archive Wayback Machine'),
    ('live-site', ("author's live", 'live site', 'live relocated', 'relocated site',
                   'author sites', 'author site', '作者ブログ', 'still-live'), '作者の現存サイトから直接回収'),
    ('yays-gapfill', ('yays copies', 'Gap-fill', 'gapfill'), '旧世代ツリー (~yays) の同名ファイルで補完'),
    ('alias', ('alias',), '同内容ファイルの別名複製'),
]


def classify_route(subject):
    hits = []
    for key, needles, label in ROUTE_RULES:
        if any(n.lower() in subject.lower() for n in needles):
            hits.append((key, label))
    if not hits:
        return 'other', 'その他 (コミット件名から分類できず)', False
    key, label = hits[0]
    return key, label, len(hits) > 1


def build_provenance_map(root):
    """git 履歴からパスごとの初回追加コミットを引く。

    ※ 進行台帳 1.1 は provenance の出所を `collinfo.json` と書いているが、実物は
      CommonCrawl のコレクション一覧 (127 件) でありファイル単位の来歴情報を一切持たない。
      ファイルがどの経路で入ったかを実際に記録しているのは git 履歴 (回収コミットの件名と日付)
      なので、そちらを出所とする。差異は QA レポートにも記録する。
    """
    cmd = ['git', '-C', root, 'log', '--reverse', '--no-renames',
           '--diff-filter=A', '--name-only', '--format=__C__%H|%at|%s']
    out = subprocess.run(cmd, capture_output=True, text=True, check=True).stdout
    prov, commit = {}, None
    for line in out.split('\n'):
        if line.startswith('__C__'):
            h, ts, subj = line[5:].split('|', 2)
            key, label, mixed = classify_route(subj)
            commit = {'commit': h[:12], 'acquired_at': _date(ts), 'commit_subject': subj,
                      'route': key, 'route_label': label, 'route_mixed': mixed}
        elif line and commit is not None:
            prov.setdefault(line, commit)
    return prov


def _date(epoch):
    import datetime
    return datetime.datetime.fromtimestamp(int(epoch), datetime.timezone.utc).strftime('%Y-%m-%d')


def provenance_for(path, prov_map):
    rec = prov_map.get(path)
    if rec is None:
        return None
    out = dict(rec)
    out['method'] = 'git-history'
    if out['route'] in ('wayback', 'megalodon', 'commoncrawl'):
        # 個別キャプチャの timestamp は記録されていないため、照会 URL の形で残す
        out['snapshot_query_url'] = 'https://web.archive.org/web/2*/' + ORIG_BASE + url_path(path)
    return out


# --------------------------------------------------------------------------- エントリ 1 件

def parse_author_cell(cell):
    """作者セル (mailto 除去済み)。4 形しかない: mailto±/Homepage±。

    元の形は `<B><A href="mailto:…">NAME</a></B> さん (<A …>Homepage</a>)` で、
    mailto を落とすと `<B><A href="#">NAME</a></B>` になるため <B> の中身が常に名前。
    """
    b = RE_BOLD.search(cell)
    name = text_of(b.group(1), keep_breaks=False) if b else None
    if name:
        name = re.sub(r'\s*さん\s*$', '', name).strip()
    if not name:
        raise ParseError('author name not found: %r' % cell[:120])
    homepage = None
    for a in anchors(cell):
        href = a['href']
        if not href or href.startswith('#'):
            continue
        if homepage is None or a['label'] == 'Homepage':
            homepage = href
    return name, homepage


def parse_illust_cell(cell):
    """画師セル。'(イラストなし)' か '画(NAMEさん)'。8 件は名前に <a> を含む。"""
    if 'イラストなし' in cell or 'イラスト無し' in cell:
        return [], None, None
    m = RE_ILLUST.search(cell)
    if not m:
        raise ParseError('illustration cell unparsed: %r' % cell[:120])
    inner = m.group(2)
    url = None
    for a in anchors(inner):
        url = a['href']
        if a['label'] == 'Homepage':
            # 「巴（<a>Homepage</a>）」形 — 名前から Homepage の括弧ごと落とす
            inner = inner[:a['span'][0]] + '\x00' + inner[a['span'][1]:]
            inner = re.sub(r'[（(]\s*\x00\s*[)）]', '', inner).replace('\x00', '')
    raw = text_of(inner, keep_breaks=False)
    names = [n.strip() for n in re.split(r'[、,＆&]', raw or '') if n.strip()]
    return names, url, raw


def parse_osusume(cell):
    """読者オススメ欄。壊れた入れ子 <A> があるためフラット走査で拾う。"""
    m = RE_OSUSUME_HEAD.search(cell)
    if not m:
        return None
    recommender = text_of(m.group(1), keep_breaks=False)
    body = m.group(2)
    links = anchors(body)
    refs = []
    for i, a in enumerate(links):
        tail = body[a['span'][1]:links[i + 1]['span'][0] if i + 1 < len(links) else len(body)]
        am = RE_OSUSUME_AUTHOR.search(strip_tags(tail))
        title = (a['label'] or '').strip('「」『』')
        refs.append({'href': a['href'], 'title': title or None,
                     'author': (html.unescape(am.group(1)).strip() or None) if am else None,
                     'author_inherited': False})
    # 壊れた入れ子 <A>(『緋色の風１<a>／２</a>』形) では作者注記が最後のリンクの後ろに
    # 1 つだけ付く。注記は直前の連なり全体に掛かるので、後ろから埋め戻す。
    carry = None
    for r in reversed(refs):
        if r['author']:
            carry = r['author']
        elif carry:
            r['author'], r['author_inherited'] = carry, True
    return {'recommender': recommender, 'refs': refs,
            'text': text_of(body) if not refs else None}


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


def parse_entry(table, page, ordinal):
    # 最初に mailto を根こそぎ落とす。以降のパースはアドレスの無い HTML だけを見る。
    table, dropped = scrub_mailto(table)
    rows = RE_TR.findall(table)
    if len(rows) != 4:
        raise ParseError('%s#%d: expected 4 rows, got %d' % (page, ordinal, len(rows)))
    r1 = RE_TD.findall(rows[0])
    if len(r1) != 6:
        raise ParseError('%s#%d: expected 6 cells in row 1, got %d' % (page, ordinal, len(r1)))
    cells = [RE_TD.findall(r) for r in rows[1:]]
    for i, c in enumerate(cells):
        if len(c) != 1:
            raise ParseError('%s#%d: row %d has %d cells (expected 1)' % (page, ordinal, i + 2, len(c)))
    row2, row3, row4 = (c[0] for c in cells)

    # --- 行 1
    title_links = anchors(r1[0])
    if not title_links:
        raise ParseError('%s#%d: no title link' % (page, ordinal))
    href = title_links[0]['href']
    title = title_links[0]['label']
    if not title:
        raise ParseError('%s#%d: empty title' % (page, ordinal))

    author, homepage = parse_author_cell(r1[1])
    illustrators, illust_url, illust_raw = parse_illust_cell(r1[2])

    date_raw = text_of(r1[3], keep_breaks=False)
    dm = RE_DATE.match(date_raw or '')
    if not dm:
        raise ParseError('%s#%d: date %r' % (page, ordinal, date_raw))
    date_iso = '%04d-%02d-%02d' % (int(dm.group(1)), int(dm.group(2)), int(dm.group(3)))

    size_raw = text_of(r1[4], keep_breaks=False)
    sm = RE_SIZE.match(size_raw or '')
    if not sm:
        raise ParseError('%s#%d: size %r' % (page, ordinal, size_raw))

    km = RE_KANSOU.search(r1[5])
    if not km:
        raise ParseError('%s#%d: kansou link %r' % (page, ordinal, r1[5][:80]))

    # --- 行 2〜4 (マーカー切り)
    try:
        arasuji_html = marker_value(row2, MARKERS['arasuji'])
        comment_html = marker_value(row2, MARKERS['comment'])
    except KeyError as e:
        raise ParseError('%s#%d: missing marker %s' % (page, ordinal, e))
    osusume = parse_osusume(row2)
    try:
        suisen_html = marker_value(row3, MARKERS['suisen'])
    except KeyError as e:
        raise ParseError('%s#%d: missing marker %s' % (page, ordinal, e))
    try:
        genre_html = marker_value(row4, MARKERS['genre'], STOP_PLAIN)
        type_html = marker_value(row4, MARKERS['type'], STOP_PLAIN)
        kw_html = marker_value(row4, MARKERS['keywords'], STOP_PLAIN)
    except KeyError as e:
        raise ParseError('%s#%d: missing marker %s' % (page, ordinal, e))

    genre, genre_raw = split_tokens(text_of(genre_html, keep_breaks=False))
    typ, type_raw = split_tokens(text_of(type_html, keep_breaks=False))
    kw, kw_raw = split_tokens(text_of(kw_html, keep_breaks=False))

    nav = nav_links_of(suisen_html) + nav_links_of(arasuji_html) + nav_links_of(comment_html)
    inline = plain_links_of(arasuji_html) + plain_links_of(comment_html)

    return {
        'title': title,
        'dropped_mailto': sorted(set(dropped)),
        '_suisen_full_empty': not text_of(suisen_html),
        'href': href,
        'author': author,
        'homepage': homepage,
        'illustrator': illustrators,
        'illustrator_url': illust_url,
        'illustrator_raw': illust_raw,
        'date_raw': date_raw,
        'date': date_iso,
        'weekday': dm.group(4),
        'size_kb': int(sm.group(1)),
        'files_n': int(sm.group(2)) if sm.group(2) else None,
        'kansou_slug': km.group(1),
        # 表示用の本文欄からは 【…】 ナビリンクを外す (nav_links に構造化して持つ)
        'arasuji': text_of(drop_nav_anchors(arasuji_html)),
        'comment': text_of(drop_nav_anchors(comment_html)),
        'suisen': text_of(drop_nav_anchors(suisen_html)),
        'osusume': osusume,
        'nav_links': nav,
        'inline_links': inline,
        'genre': genre, 'genre_raw': genre_raw,
        'type': typ, 'type_raw': type_raw,
        'keywords': kw, 'keywords_raw': kw_raw,
        'zokusei': [],                      # 旧目録 (1.2) のみ使う欄
        'catalog_ref': '%s#%d' % (page, ordinal),
    }


# --------------------------------------------------------------------------- パス正規化

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
    if re.match(r'^[a-zA-Z][a-zA-Z0-9+.\-]*://', href):
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


# --------------------------------------------------------------------------- 組み立て

def build(root, annex_base, use_provenance=True):
    pages = ['lib%d.html' % i for i in range(1, 74)]
    prov_map = build_provenance_map(root) if use_provenance else {}
    parsed, failures = [], []
    for page in pages:
        path = os.path.join(root, page)
        with open(path, encoding='utf-8') as fh:
            src = fh.read()
        tables = RE_TABLE.findall(src)
        for i, table in enumerate(tables, 1):
            try:
                e = parse_entry(table, page, i)
            except ParseError as exc:
                failures.append(str(exc))
                continue
            e['_key'] = split_href(e.pop('href'))
            parsed.append(e)

    # 同じ (source_path, anchor) を指すエントリのグループを先に把握する
    groups = collections.Counter(e['_key'][:2] for e in parsed)

    records = []
    for e in parsed:
        source_path, anchor, kind = e['_key']
        shared = groups[(source_path, anchor)] > 1
        eid = episode_id_of(source_path, anchor,
                            e['date'].replace('-', '') if shared else None)
        exists = kind == 'local' and os.path.exists(os.path.join(root, source_path))
        yays_path = YAYS_PREFIX + source_path
        rec = collections.OrderedDict()
        rec['episode_id'] = eid
        rec['corpus'] = 'honkan'
        rec['source_path'] = source_path
        rec['source_anchor'] = anchor
        rec['source_kind'] = kind
        rec['source_exists'] = exists
        rec['source_shared_by'] = groups[(source_path, anchor)]
        rec['entry_type'] = entry_type_of(source_path, kind)
        rec['title'] = e['title']
        rec['author'] = e['author']
        rec['homepage'] = e['homepage']
        rec['illustrator'] = e['illustrator']
        rec['illustrator_url'] = e['illustrator_url']
        rec['illustrator_raw'] = e['illustrator_raw']
        rec['date'] = e['date']
        rec['date_raw'] = e['date_raw']
        rec['weekday'] = e['weekday']
        rec['size_kb'] = e['size_kb']
        rec['files_n'] = e['files_n']
        rec['kansou_slug'] = e['kansou_slug']
        rec['kansou_annex_url'] = annex_base + '~ts/kansou/bbs@log_%s.cgi' % e['kansou_slug']
        rec['arasuji'] = e['arasuji']
        rec['comment'] = e['comment']
        rec['osusume'] = e['osusume']
        rec['suisen'] = e['suisen']
        rec['nav_links'] = e['nav_links']
        rec['inline_links'] = e['inline_links']
        rec['genre'] = e['genre']
        rec['genre_raw'] = e['genre_raw']
        rec['type'] = e['type']
        rec['type_raw'] = e['type_raw']
        rec['keywords'] = e['keywords']
        rec['keywords_raw'] = e['keywords_raw']
        rec['zokusei'] = e['zokusei']
        rec['catalog_ref'] = e['catalog_ref']
        rec['orig_url'] = (ORIG_BASE + url_path(source_path) if kind == 'local'
                           else re.sub(r'^external/', 'http://', url_path(source_path)))
        if anchor:
            rec['orig_url'] += '#' + anchor
        rec['annex_url'] = (annex_base + url_path(source_path) + ('#' + anchor if anchor else '')
                            if exists else None)
        rec['annex_yays_url'] = (annex_base + url_path(yays_path)
                                 if os.path.exists(os.path.join(root, yays_path)) else None)
        rec['provenance'] = provenance_for(source_path, prov_map) if exists else None
        rec['_dropped_mailto'] = e['dropped_mailto']       # 自己検査用。出力前に取り除く
        rec['_suisen_full_empty'] = e['_suisen_full_empty']
        records.append(rec)
    return records, failures


# --------------------------------------------------------------------------- 自己検査

def selftest(records, failures):
    """受け入れ条件をロジックで検査する。戻り値 (ok, [(name, ok, detail)])。"""
    results = []

    def check(name, ok, detail=''):
        results.append((name, bool(ok), detail))

    check('エントリ数 == %d' % EXPECTED_ENTRIES, len(records) == EXPECTED_ENTRIES,
          '実測 %d 件' % len(records))
    check('パース失敗 0', not failures,
          '失敗 %d 件%s' % (len(failures), (': ' + failures[0]) if failures else ''))

    ids = [r['episode_id'] for r in records]
    dup = [k for k, v in collections.Counter(ids).items() if v > 1]
    check('episode_id の重複 0', not dup, '重複 %d 件 %s' % (len(dup), dup[:5]))

    # --- mailto 由来の値の残存検査 (受け入れ条件)
    # 「捨てたはずのアドレス」を全部集め、出力 JSONL の全文に 1 つも出てこないことを確かめる。
    # ファイル名の @ (index@08201937.html) や homepage の URL を誤検知しないよう、
    # 検査対象は「mailto: リンクから実際に抽出した文字列」そのものに限定する。
    addresses = set()
    for r in records:
        for a in r.get('_dropped_mailto') or []:
            if a:
                addresses.add(a)
    payload = '\n'.join(json.dumps(strip_private(r), ensure_ascii=False) for r in records)
    residue = sorted(a for a in addresses if a in payload)
    check('mailto から抽出したアドレスの残存 0 (%d 件を検査)' % len(addresses),
          not residue, '残存 %s' % residue[:5])
    check("出力に 'mailto:' が現れない", 'mailto:' not in payload,
          '出現位置あり' if 'mailto:' in payload else '')
    # 「アドレスらしき文字列」が新たに現れていないことも見る。ただし
    # `index@08201937.html` 型のファイル名や `bbs@log_<id>.cgi` を誤検知しないよう、
    # 拡張子で終わる形は RE_BARE_MAIL 側で除外してある。
    bare = sorted(set(RE_BARE_MAIL.findall(payload)))
    check('出力にアドレス形の文字列が無い', not bare, '検出 %s' % bare[:5])
    # 注: メールのローカルパート単独 (kagerou6 等) は感想板 slug と一致することがあり
    # (板 id はアドレスから採られた例が多い)、これを残存とみなすと誤検知になる。
    # 板 id は .cgi のファイル名由来であって mailto 由来ではないので検査対象にしない。

    # --- 仕様の実測値との突き合わせ (survey の fields 節)
    def rate(pred):
        return sum(1 for r in records if pred(r))
    check('homepage 件数 == 918 (=771+147)', rate(lambda r: r['homepage']) == 918,
          '実測 %d' % rate(lambda r: r['homepage']))
    check('画師あり == 477', rate(lambda r: r['illustrator']) == 477,
          '実測 %d' % rate(lambda r: r['illustrator']))
    check('オススメあり == 765', rate(lambda r: r['osusume']) == 765,
          '実測 %d' % rate(lambda r: r['osusume']))
    check('特殊エントリ (画像/外部/anchor) == 28',
          rate(lambda r: r['entry_type'] != 'html' or r['source_anchor']
               or r['source_path'].startswith('special/')) == 28,
          '実測 %d' % rate(lambda r: r['entry_type'] != 'html' or r['source_anchor']
                           or r['source_path'].startswith('special/')))
    check('あらすじ空 == 182', rate(lambda r: not r['arasuji']) == 182,
          '実測 %d' % rate(lambda r: not r['arasuji']))
    check('コメント空 == 290', rate(lambda r: not r['comment']) == 290,
          '実測 %d' % rate(lambda r: not r['comment']))
    # 推薦文の「空」は survey の定義 (欄の生値が空) で数える。表示用の r['suisen'] は
    # 末尾の 【…】 ナビリンクを外した値なので、ナビだけの欄が空扱いになり数が合わない。
    check('推薦文空 == 147', rate(lambda r: r['_suisen_full_empty']) == 147,
          '実測 %d' % rate(lambda r: r['_suisen_full_empty']))
    check('ジャンル空 == 238', rate(lambda r: not r['genre']) == 238,
          '実測 %d' % rate(lambda r: not r['genre']))
    check('種別空 == 326', rate(lambda r: not r['type']) == 326,
          '実測 %d' % rate(lambda r: not r['type']))
    check('キーワード空 == 437', rate(lambda r: not r['keywords']) == 437,
          '実測 %d' % rate(lambda r: not r['keywords']))
    check('感想板 slug の異なり == 305',
          len({r['kansou_slug'] for r in records}) == 305,
          '実測 %d' % len({r['kansou_slug'] for r in records}))
    for field, expected in (('genre_raw', 244), ('type_raw', 187), ('keywords_raw', 1083)):
        n = len({t for r in records for t in r[field]})
        check('%s の異なり == %d (NFKC 前)' % (field, expected), n == expected, '実測 %d' % n)

    # 必須フィールドが 1 件も欠けていないこと
    for field in ('episode_id', 'source_path', 'title', 'author', 'date', 'date_raw',
                  'size_kb', 'kansou_slug', 'orig_url', 'catalog_ref'):
        missing = sum(1 for r in records if r.get(field) in (None, '', []))
        check('必須欄 %s に欠落なし' % field, missing == 0, '欠落 %d 件' % missing)

    return all(ok for _, ok, _ in results), results


def strip_private(rec):
    return collections.OrderedDict((k, v) for k, v in rec.items() if not k.startswith('_'))


# --------------------------------------------------------------------------- レポート

def report_of(records, failures, results):
    prov = [r['provenance'] for r in records]
    covered = [p for p in prov if p]
    routes = collections.Counter(p['route'] for p in covered)
    mixed = sum(1 for p in covered if p.get('route_mixed'))
    years = collections.Counter(r['date'][:4] for r in records)
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/catalog_build.py'),
        ('corpus', 'honkan (lib1-73)'),
        ('entries', len(records)),
        ('parse_failures', len(failures)),
        ('parse_failure_detail', failures[:20]),
        ('unique_episode_ids', len({r['episode_id'] for r in records})),
        ('unique_source_paths', len({r['source_path'] for r in records})),
        ('source_files_present', sum(1 for r in records if r['source_exists'])),
        ('source_files_missing', sum(1 for r in records if not r['source_exists'])),
        ('entry_types', dict(collections.Counter(r['entry_type'] for r in records))),
        ('source_kinds', dict(collections.Counter(r['source_kind'] for r in records))),
        ('shared_source_entries', sum(1 for r in records if r['source_shared_by'] > 1)),
        ('shared_source_paths', len({r['source_path'] for r in records if r['source_shared_by'] > 1})),
        ('anchored_entries', sum(1 for r in records if r['source_anchor'])),
        ('authors_distinct_display', len({r['author'] for r in records})),
        ('kansou_slugs_distinct', len({r['kansou_slug'] for r in records})),
        ('homepage_present', sum(1 for r in records if r['homepage'])),
        ('illustrator_present', sum(1 for r in records if r['illustrator'])),
        ('osusume_entries', sum(1 for r in records if r['osusume'])),
        ('osusume_refs', sum(len(r['osusume']['refs']) for r in records if r['osusume'])),
        ('osusume_refs_with_author',
         sum(1 for r in records if r['osusume'] for x in r['osusume']['refs'] if x['author'])),
        ('osusume_refs_author_inherited',
         sum(1 for r in records if r['osusume'] for x in r['osusume']['refs'] if x['author_inherited'])),
        ('nav_links_total', sum(len(r['nav_links']) for r in records)),
        ('nav_links_entries', sum(1 for r in records if r['nav_links'])),
        ('inline_links_total', sum(len(r['inline_links']) for r in records)),
        ('genre_tokens_distinct', len({t for r in records for t in r['genre']})),
        ('genre_tokens_distinct_raw', len({t for r in records for t in r['genre_raw']})),
        ('type_tokens_distinct', len({t for r in records for t in r['type']})),
        ('type_tokens_distinct_raw', len({t for r in records for t in r['type_raw']})),
        ('keyword_tokens_distinct', len({t for r in records for t in r['keywords']})),
        ('keyword_tokens_distinct_raw', len({t for r in records for t in r['keywords_raw']})),
        ('missing_by_year', dict(sorted(collections.Counter(
            r['date'][:4] for r in records if not r['source_exists']).items()))),
        ('missing_by_entry_type', dict(collections.Counter(
            r['entry_type'] for r in records if not r['source_exists']))),
        ('annex_url_present', sum(1 for r in records if r['annex_url'])),
        ('annex_yays_url_present', sum(1 for r in records if r['annex_yays_url'])),
        ('mailto_dropped_distinct', len({a for r in records for a in r['_dropped_mailto']})),
        ('mailto_dropped_entries', sum(1 for r in records if r['_dropped_mailto'])),
        ('provenance_covered', len(covered)),
        ('provenance_coverage_pct', round(100.0 * len(covered) / len(records), 2) if records else 0),
        ('provenance_routes', dict(routes)),
        ('provenance_route_mixed', mixed),
        ('provenance_source', 'git-history (collinfo.json は CC コレクション一覧であり来歴情報を持たない)'),
        ('year_histogram', dict(sorted(years.items()))),
        ('selftest', [{'name': n, 'ok': ok, 'detail': d} for n, ok, d in results]),
        ('selftest_ok', all(ok for _, ok, _ in results)),
    ])


def print_summary(rep):
    print('entries=%d parse_failures=%d unique_ids=%d' %
          (rep['entries'], rep['parse_failures'], rep['unique_episode_ids']))
    print('source files: present=%d missing=%d  types=%s' %
          (rep['source_files_present'], rep['source_files_missing'], rep['entry_types']))
    print('annex_url=%d annex_yays_url=%d' % (rep['annex_url_present'], rep['annex_yays_url_present']))
    print('mailto dropped: %d distinct / %d entries' %
          (rep['mailto_dropped_distinct'], rep['mailto_dropped_entries']))
    print('provenance coverage=%.2f%% (%d/%d) routes=%s mixed=%d' %
          (rep['provenance_coverage_pct'], rep['provenance_covered'], rep['entries'],
           rep['provenance_routes'], rep['provenance_route_mixed']))
    print('--- selftest')
    for t in rep['selftest']:
        print('  [%s] %s %s' % ('OK ' if t['ok'] else 'NG ', t['name'], t['detail']))


# --------------------------------------------------------------------------- main

def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--out', default=None, help='既定 <root>/catalog/episodes.jsonl')
    ap.add_argument('--report', default=None, help='既定 <root>/catalog/reports/catalog_build.json')
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--no-provenance', action='store_true', help='git 走査を省く')
    ap.add_argument('--check', action='store_true', help='ファイルを書かず検査のみ')
    args = ap.parse_args(argv)

    root = os.path.abspath(args.root)
    annex_base = args.annex_base if args.annex_base.endswith('/') else args.annex_base + '/'
    records, failures = build(root, annex_base, use_provenance=not args.no_provenance)
    ok, results = selftest(records, failures)
    rep = report_of(records, failures, results)
    print_summary(rep)

    if not args.check:
        out = args.out or os.path.join(root, 'catalog', 'episodes.jsonl')
        rpt = args.report or os.path.join(root, 'catalog', 'reports', 'catalog_build.json')
        os.makedirs(os.path.dirname(out), exist_ok=True)
        os.makedirs(os.path.dirname(rpt), exist_ok=True)
        with open(out, 'w', encoding='utf-8') as fh:
            for r in records:
                fh.write(json.dumps(strip_private(r), ensure_ascii=False) + '\n')
        with open(rpt, 'w', encoding='utf-8') as fh:
            json.dump(rep, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        print('wrote %s (%d lines) and %s' % (os.path.relpath(out, root), len(records),
                                              os.path.relpath(rpt, root)))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
