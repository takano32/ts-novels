# -*- coding: utf-8 -*-
"""honkan.py — 正規目録 lib1〜73 (corpus=honkan) のエントリ 1 件のパース (T7.1、specs/S171.md)。

  - honkan のエントリ = `<TABLE BORDER=1 …>` 1 つ。必ず 4 行、1 行目は 6 セル。
    HTML は 1 行に長大に詰まっているので行単位パースは禁止 (常に findall)。
  - mailto はこの段階で捨てる (WP の DB にメールアドレスを構造的に入れないため)。
"""
import html
import re

from tslib.catalog.fragments import (
    RE_TR, RE_TD, RE_BOLD, MARKERS, STOP_PLAIN, ParseError,
    text_of, anchors, strip_tags, split_tokens, marker_value, body_text,
    nav_links_of, plain_links_of)
from tslib.catalog.mailto import scrub_mailto

HONKAN_PAGES = ['lib%d.html' % i for i in range(1, 74)]
EXPECTED_ENTRIES = 2887                 # 受け入れ条件 (survey 実測)

RE_TABLE = re.compile(r'<TABLE\s+BORDER=1.*?</TABLE>', re.S | re.I)
RE_ILLUST = re.compile(r'<B>(.*?)</B>\s*[(（]\s*<B>(.*?)</B>\s*さん\s*[)）]', re.S | re.I)
RE_DATE = re.compile(r'^(\d{4})/(\d{1,2})/(\d{1,2})(?:[(（]([月火水木金土日])[)）])?$')
RE_SIZE = re.compile(r'^(\d+)\s?KB(?:\s*/\s*(\d+)FILES)?$', re.I)
RE_KANSOU = re.compile(r'~ts/kansou/bbs@log_([A-Za-z0-9_\-]+)\.cgi', re.I)
# 推薦者名に 【】 は入らない。`.+?` のままだと直前の 【あらすじ】 から食い始めてしまう。
RE_OSUSUME_HEAD = re.compile(r'【([^【】]+?)\s*さんのオススメ作品】\s*</B>\s*(.*?)(?=<B>\s*【|\Z)', re.S | re.I)
RE_OSUSUME_AUTHOR = re.compile(r'[(（]\s*(.*?)\s*さん作\s*[)）]', re.S)
RE_TAKEDOWN = re.compile(
    r'(?:作者[であるの]*)?([^、。「」<>\n]{1,24}?)さんの(?:ご)?(?:要望|申し出|希望)により[^。]*?'
    r'(公開停止|掲載停止|公開を停止|掲載を停止)[^。]*?(?:（([^）]*)）)?')


def takedown_notice(cell_html):
    """行 3 の生 HTML から「作者の要望により公開停止」の告知を拾う。

    marker_value は太字マーカーで値を切ってしまうので、告知が推薦文の後ろにあると
    落ちる (実測 1 件)。ここは切る前のセル全体を見るため取りこぼさない。
    戻り値: {'author': 申し出た人, 'kind': 停止の語, 'date': 記載日} または None
    """
    if cell_html is None:
        return None
    text = text_of(cell_html)
    m = RE_TAKEDOWN.search(text)
    if not m:
        return None
    return {'author': m.group(1).strip(), 'kind': m.group(2), 'date_raw': (m.group(3) or '').strip()}


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
    # 複数画師は 、 や ＆ 区切り。区切られた側の名前には敬称が残るので落とす
    # (原表記は illustrator_raw に保持)
    names = [re.sub(r'\s*(?:さん|様)\s*$', '', n).strip()
             for n in re.split(r'[、,＆&]', raw or '') if n.strip()]
    return [n for n in names if n], url, raw


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


def parse_entry(table, page, ordinal):
    """`<TABLE BORDER=1>` 1 つ → エントリの dict。構造が仕様から外れていれば ParseError。"""
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
    # 作者からの公開停止の申し出 (当時の運営が掲載を止めた印)。marker_value は
    # 太字マーカーで値を切るので、告知を落とさないよう **行 3 の生 HTML 全体**を見る
    takedown = takedown_notice(row3)

    return {
        'title': title,
        'takedown_notice': takedown,
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
        'arasuji': body_text(arasuji_html),
        'comment': body_text(comment_html),
        'suisen': body_text(suisen_html),
        'osusume': osusume,
        'nav_links': nav,
        'inline_links': inline,
        'genre': genre, 'genre_raw': genre_raw,
        'type': typ, 'type_raw': type_raw,
        'keywords': kw, 'keywords_raw': kw_raw,
        'zokusei': [],                      # 旧目録 (T7.2) のみ使う欄
        'catalog_ref': '%s#%d' % (page, ordinal),
    }


def parse_page(page, src):
    """1 ページ分の `<TABLE BORDER=1>` を全部パースする。戻り値 (entries, failures)。"""
    entries, failures = [], []
    for i, table in enumerate(RE_TABLE.findall(src), 1):
        try:
            entries.append(parse_entry(table, page, i))
        except ParseError as exc:
            failures.append(str(exc))
    return entries, failures
