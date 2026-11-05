# -*- coding: utf-8 -*-
"""legacy.py — 旧目録 lib01〜09 (corpus=legacy) のパース (T7.2、specs/S172.md)。

legacy は 2 形式: lib01〜06 = 非テーブル (`NNKB(updated M/D)<b><a …>［題］</a> 作・NAME</b><br>`)、
lib07〜09 = 移行期テーブル (【属性】欄・2 桁年・`***` 欠損値・per-work の noteky 感想リンク)。
"""
import collections
import datetime
import html
import re

from tslib.catalog.fragments import (
    RE_TR, RE_TD, RE_BOLD, MARKERS, STOP_PLAIN, RE_COMMENT_HTML, ParseError,
    text_of, anchors, strip_tags, split_tokens, marker_values, body_text,
    nav_links_of, plain_links_of)
from tslib.catalog.regular import RE_TABLE, RE_SIZE
from tslib.catalog.mailto import scrub_mailto

LEGACY_FLAT_PAGES = ['lib%02d.html' % i for i in range(1, 7)]    # 非テーブル形式 (1997.11-1999.9)
LEGACY_TABLE_PAGES = ['lib%02d.html' % i for i in range(7, 10)]  # 移行期テーブル (1999.9-2000.2)
EXPECTED_LEGACY_BLOCKS = 252 + 84       # survey 実測 (lib01-06 の 252 は HTML コメント内も含む)

# lib01-06: `44KB(updated 4/5)<b><a href="…">［題名］</a>　作・NAME</b><br>` が 1 エントリの頭。
# KB も (updated …) も欠ける形 (CG エントリ) があるので両方 optional。
RE_LEG_HEAD = re.compile(
    r'(?:(\d+)\s?KB)?\s*'
    r'(?:\(\s*(?:updated|last\s*modified)\s*([^)]*?)\s*\))?\s*'
    r'<b>\s*<a\s+[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>(.*?)</b>\s*<br>',
    re.S | re.I)
RE_LEG_RANGE = re.compile(r'旧作品\s*</font>\s*[(（]\s*([\d.]+)\s*-\s*([\d.]*)\s*[)）]', re.I)
RE_LEG_MD = re.compile(r'^(\d{1,2})/(\d{1,2})$')
RE_LEG_YMD = re.compile(r'^(\d{2})/(\d{1,2})/(\d{1,2})$')
RE_LEG_YMD4 = re.compile(r'^(\d{4})/(\d{1,2})/(\d{1,2})$')
# 役割ラベル。`作 / 画・X` は先に `作画` へ畳んでから分割する。
LEG_ROLES = ('作画', '作', '画', '訳', '編', '原案', '原作', 'メカ画', '挿画', '構成')
RE_LEG_ROLE = re.compile(r'^(%s)\s*[・:：]\s*(.*)$' % '|'.join(LEG_ROLES), re.S)
RE_LEG_ROLE_SPLIT = re.compile(r'\s*[／/]\s*(?=(?:%s)\s*[・:：])' % '|'.join(LEG_ROLES))
RE_LEG_SAKUGA = re.compile(r'(作|画)\s*[／/]\s*(画|作)')
RE_LEG_BODY_LABEL = re.compile(
    r'(%s|あらすじ|作者様コメント|作者様のお言葉|作者様のことば|作者コメント|管理人より|予告|オビ)\s*[：:]'
    % '|'.join(LEG_ROLES + ('作\\s*[／/]\\s*画',)))
RE_LEG_TITLE_BRACKET = re.compile(r'^［(.*)］$', re.S)
RE_LEG_NOTEKY = re.compile(
    r'~ezpe/cgi-bin/noteky/noteky@c_noteread_f_(\d+)_id_([A-Za-z0-9]+)_ff_on\.cgi', re.I)
RE_LEG_ILLUST = re.compile(r'(?:挿画|画|イラスト)\s*[：:・]\s*(.*?)\s*(?:さん)?\s*[)）]?$', re.S)
RE_LEG_PLACEHOLDER = re.compile(r'^\*+$')
COMMENT_KEYS = ('作者様コメント', '作者様のお言葉', '作者様のことば', '作者コメント')


def leg_clean_name(s):
    """旧目録の氏名表記から敬称・全角空白の前後余白を落とす。"""
    if s is None:
        return None
    s = strip_tags(html.unescape(s))
    s = re.sub(r'\s*(?:さん|様)\s*$', '', s.strip())
    s = s.strip().strip('　').strip()
    return s or None


def leg_parse_credit(raw):
    """`作・A / 画・B` 形のクレジット → (author, illustrators[], credit_raw, homepage)。

    旧目録は作者欄が独立していない (見出し行の末尾か本文行の `作：` に混ざる)。
    `作 / 画・NAME` は 1 人が両方を担当した表記なので先に `作画` に畳んでから分割する。
    `作：NAME (<a>WWW</a>)` 形のホームページリンクは URL を取り出して名前から外す。
    """
    if not raw:
        return None, [], None, None
    homepage = None
    for a in anchors(raw):
        if a['href'] and not a['href'].startswith('#'):
            homepage = a['href']
    raw = re.sub(r'[（(]\s*<a\b.*?</a>\s*[)）]', '', raw, flags=re.S | re.I)
    text = strip_tags(html.unescape(raw)).strip()
    text = text.strip('　 ').strip()
    if not text:
        return None, [], None, homepage
    folded = RE_LEG_SAKUGA.sub('作画', text)
    segs = [s for s in RE_LEG_ROLE_SPLIT.split(folded) if s.strip()]
    author, illus, roles = None, [], []
    for seg in segs:
        m = RE_LEG_ROLE.match(seg.strip())
        if not m:
            # 役割ラベルが無い断片 (`作・A B` の B 等) は先頭の役割に属す
            continue
        role, names = m.group(1), m.group(2)
        roles.append(role)
        parts = [leg_clean_name(p) for p in re.split(r'[、,＆&]', names)]
        parts = [p for p in parts if p]
        if not parts:
            continue
        if role in ('作', '作画', '訳', '編', '原作') and author is None:
            author = leg_clean_name(names)          # 連名は原表記のまま 1 名として扱う
        if role in ('画', '作画', 'メカ画', '挿画'):
            illus.extend(parts)
    if author is None and illus:
        author = illus[0]                            # 画のみのクレジット (CG エントリ)
    return author, illus, text, homepage


def _range_distance(ymd, start, end):
    """(y, m, d) が [start, end] からどれだけ離れているか (日数)。範囲内なら 0。"""
    try:
        d = datetime.date(*ymd)
        a, b = datetime.date(*start), datetime.date(*end)
    except ValueError:
        return 10 ** 6
    if d < a:
        return (a - d).days
    if d > b:
        return (d - b).days
    return 0


def leg_page_range(src):
    """`旧作品(1997.11.4 - 1998.4.5)` → ((1997,11,4), (1998,4,5))。末尾欠落は None。"""
    m = RE_LEG_RANGE.search(src)
    if not m:
        return None, None

    def p(s):
        parts = [int(x) for x in s.split('.') if x.isdigit()]
        return tuple(parts) if len(parts) == 3 else None
    return p(m.group(1)), p(m.group(2) or '')


def leg_resolve_year(mo, da, start, end, year):
    """月日しか無い `(updated M/D)` の年を、ページの収録期間から決める。
    戻り値 (year, precision)。precision は range-derived / range-ambiguous / range-clamped。

    ページの収録期間は必ず 12 ヶ月未満なので、(月,日) を範囲に入れる年は
    高々 1 つに決まる。ページ内が新しい順に並んでいない箇所 (末尾のパロディ
    作品群など) があるため、単調減少の仮定ではなく範囲で決める。
    """
    years = list(range(start[0], end[0] + 1)) if start else [year]
    cands = [y for y in years if start <= (y, mo, da) <= end] if start else []
    if len(cands) == 1:
        return cands[0], 'range-derived'
    if cands:
        return cands[0], 'range-ambiguous'
    # 収録期間の外に出る日付 (公開後にファイルを更新した数件) は、
    # 期間に最も近い年に寄せる。単調減少の仮定より安全。
    return min(years, key=lambda y: _range_distance((y, mo, da), start, end)), 'range-clamped'


def leg_split_labels(body):
    """本文断片を `ラベル：値` の並びに分解する。戻り値 ([(label, value_html)], 残り)。

    `<b>あらすじ：</b>値<br>` と `<b>あらすじ：値</b><br>` の両形があるので、
    ラベル直後が `</b>` ならそれを食べて `<br>` まで、そうでなければ `</b>` か `<br>` まで。
    """
    items, spans = [], []
    for m in RE_LEG_BODY_LABEL.finditer(body):
        rest = body[m.end():]
        closed = re.match(r'\s*</b>', rest, re.I)
        offset = closed.end() if closed else 0
        tail = rest[offset:]
        stops = [t.start() for t in (re.search(r'<br\s*/?>', tail, re.I),) if t]
        if not closed:
            e = re.search(r'</b>', tail, re.I)
            if e:
                stops.append(e.start())
        nxt = RE_LEG_BODY_LABEL.search(tail)
        if nxt:
            stops.append(nxt.start())
        cut = min(stops) if stops else len(tail)
        items.append((re.sub(r'\s', '', m.group(1)), tail[:cut]))
        # `<b>` を巻き込んで消さないよう、ラベル開始の直前の `<b>` も除去範囲に含める
        lead = body.rfind('<b>', 0, m.start())
        begin = lead if lead >= 0 and not body[lead + 3:m.start()].strip() else m.start()
        spans.append((begin, m.end() + offset + cut))
    remain, last = [], 0
    for a, b in spans:
        if a >= last:
            remain.append(body[last:a])
            last = b
    remain.append(body[last:])
    return items, ''.join(remain)


def parse_legacy_flat_page(page, src, failures):
    """lib01〜06 (非テーブル形式) を 1 ページ分パースする。

    日付欄は `(updated M/D)` と年が無いので、ページ見出しの収録期間
    (`旧作品(1997.11.4 - 1998.4.5)`) と「ページ内は新しい順」という性質から年を復元する。
    """
    start, end = leg_page_range(src)
    if not end:
        failures.append('%s: page range not found' % page)
        return []
    comment_spans = [m.span() for m in RE_COMMENT_HTML.finditer(src)]
    body_start = 0
    hm = re.search(r'Page\.\s*\d+', src)
    if hm:
        body_start = hm.end()
    heads = [m for m in RE_LEG_HEAD.finditer(src) if m.start() >= body_start]
    out = []
    year = end[0]
    for i, m in enumerate(heads):
        stop = heads[i + 1].start() if i + 1 < len(heads) else len(src)
        body = src[m.end():stop]
        for cut in ('<hr', '<!--', '-->'):
            j = body.lower().find(cut)
            if j >= 0:
                body = body[:j]
        commented = any(a <= m.start() < b for a, b in comment_spans)
        ordinal = i + 1
        ref = '%s#%d' % (page, ordinal)

        raw_date = (m.group(2) or '').strip()
        md = RE_LEG_MD.match(raw_date)
        ymd = RE_LEG_YMD.match(raw_date)
        if md:
            mo, da = int(md.group(1)), int(md.group(2))
            year, precision = leg_resolve_year(mo, da, start, end, year)
            date = '%04d-%02d-%02d' % (year, mo, da)
        elif ymd:
            yy = int(ymd.group(1))
            year = 1900 + yy if yy >= 50 else 2000 + yy
            mo, da = int(ymd.group(2)), int(ymd.group(3))
            date = '%04d-%02d-%02d' % (year, mo, da)
            precision = 'exact'
        else:
            failures.append('%s: unparsed legacy date %r' % (ref, raw_date))
            continue

        head_credit = m.group(7)
        body, dropped_body = scrub_mailto(body)
        head_credit, dropped_head = scrub_mailto(head_credit or '')
        items, remain = leg_split_labels(body)
        by_label = collections.defaultdict(list)
        for label, value in items:
            by_label[label].append(value)
        credit = head_credit
        if not RE_LEG_ROLE.match(strip_tags(credit or '').strip().lstrip('　 ')):
            for role in LEG_ROLES + ('作/画', '作／画'):
                if role in by_label:
                    credit = '%s：%s' % (role, by_label[role][0])
                    break
        author, illus, credit_raw, homepage = leg_parse_credit(credit)
        comment = None
        for key in COMMENT_KEYS:
            if key in by_label:
                comment = text_of(by_label[key][0])
                break
        editor = text_of(remain)
        if editor:
            editor = re.sub(r'^[（(]\s*', '', editor)
            editor = re.sub(r'\s*[）)]$', '', editor).strip() or None
        title_raw = text_of(m.group(6), keep_breaks=False)
        tm = RE_LEG_TITLE_BRACKET.match(title_raw or '')
        out.append({
            'title': (tm.group(1).strip() if tm else title_raw),
            'title_raw': title_raw,
            'href': html.unescape((m.group(3) or m.group(4) or m.group(5) or '').strip()),
            'author': author,
            'homepage': homepage,
            'illustrator': illus,
            'illustrator_url': None,
            'illustrator_raw': credit_raw if illus else None,
            'legacy_credit_raw': credit_raw,
            'date': date,
            'date_raw': raw_date or None,
            'date_precision': precision,
            'weekday': None,
            'size_kb': int(m.group(1)) if m.group(1) else None,
            'files_n': None,
            'kansou_slug': None,
            'noteky_url': None,
            'arasuji': text_of(by_label['あらすじ'][0]) if 'あらすじ' in by_label else None,
            'comment': comment,
            'suisen': editor,
            'osusume': None,
            'nav_links': [], 'inline_links': plain_links_of(body),
            'genre': [], 'genre_raw': [], 'type': [], 'type_raw': [],
            'keywords': [], 'keywords_raw': [], 'zokusei': [], 'zokusei_raw': [],
            'notice': None,
            'catalog_ref': ref,
            'legacy_format': 'flat',
            'entry_role': 'work',
            'commented_out': commented,
            'dropped_mailto': sorted(set(dropped_head) | set(dropped_body)),
            '_suisen_full_empty': not editor,
        })
    return out


def leg_table_cell_value(cell):
    """`***` / `**` の欠損マーカーを None にした表示テキスト。"""
    v = text_of(cell, keep_breaks=False)
    if v is None or RE_LEG_PLACEHOLDER.match(v):
        return None
    return v


def leg_parse_date(date_raw):
    """`99/9/12` (2 桁年) か `1999/9/12` → ISO 日付。合わなければ None。"""
    dm = RE_LEG_YMD.match(date_raw or '') or RE_LEG_YMD4.match(date_raw or '')
    if not dm:
        return None
    y = int(dm.group(1))
    if len(dm.group(1)) == 2:
        y = 1900 + y if y >= 50 else 2000 + y
    return '%04d-%02d-%02d' % (y, int(dm.group(2)), int(dm.group(3)))


def parse_legacy_table_entry(table, page, ordinal):
    """lib07〜09 (移行期テーブル)。行数・セル数が一定でないので寛容にパースする。"""
    table, dropped = scrub_mailto(table)
    rows = RE_TR.findall(table)
    if not rows:
        raise ParseError('%s#%d: no rows' % (page, ordinal))
    r1 = RE_TD.findall(rows[0])
    if len(r1) not in (5, 6):
        raise ParseError('%s#%d: row1 has %d cells (expected 5 or 6)' % (page, ordinal, len(r1)))
    links = anchors(r1[0])
    if not links:
        raise ParseError('%s#%d: no title link' % (page, ordinal))
    title = links[0]['label']
    image_flag = 'IMAGE' in strip_tags(r1[0]).upper()

    author_cell = leg_table_cell_value(r1[1])
    author, homepage = None, None
    if author_cell:
        b = RE_BOLD.search(r1[1])
        author = leg_clean_name(b.group(1) if b else author_cell)
        for a in anchors(r1[1]):
            if a['href'] and not a['href'].startswith('#'):
                homepage = a['href']

    illus, illus_raw = [], leg_table_cell_value(r1[2])
    if illus_raw and not re.search(r'イラスト(?:なし|無し|作品)', illus_raw):
        im = RE_LEG_ILLUST.search(illus_raw.strip('（）()'))
        if im:
            illus = [n for n in (leg_clean_name(x) for x in re.split(r'[、,＆&]', im.group(1))) if n]

    date_raw = leg_table_cell_value(r1[3])
    date = leg_parse_date(date_raw)
    if not date:
        raise ParseError('%s#%d: legacy date %r' % (page, ordinal, date_raw))

    size_raw = leg_table_cell_value(r1[4])
    sm = RE_SIZE.match((size_raw or '').replace(' ', '')) if size_raw else None

    noteky = None
    if len(r1) > 5:
        nm = RE_LEG_NOTEKY.search(r1[5])
        if nm:
            noteky = {'folder': nm.group(1), 'id': nm.group(2), 'path': nm.group(0)}

    # 行の数は一定でない (2〜4 行) ので、どの行かではなく「どのマーカーを含むか」で振り分ける。
    # 分類行のマーカーは <B> で囲まれていないため、散文行と一緒くたにすると
    # 【推薦文】 の値が分類行まで食い込む。行を分けたまま切り出すこと。
    cells = [c for r in rows[1:] for c in RE_TD.findall(r)]
    prose = ' '.join(c for c in cells if MARKERS['genre'] not in c)
    taxo = ' '.join(c for c in cells if MARKERS['genre'] in c)
    fields = marker_values(prose, (('arasuji', MARKERS['arasuji']), ('comment', MARKERS['comment']),
                                   ('comment_legacy', MARKERS['comment_legacy']),
                                   ('suisen', MARKERS['suisen']), ('notice', '【お知らせ】')))
    fields.update(marker_values(taxo, (('genre', MARKERS['genre']), ('type', MARKERS['type']),
                                       ('zokusei', MARKERS['zokusei']), ('keywords', MARKERS['keywords'])),
                                STOP_PLAIN))

    genre, genre_raw = split_tokens(text_of(fields['genre'], keep_breaks=False))
    typ, type_raw = split_tokens(text_of(fields['type'], keep_breaks=False))
    kw, kw_raw = split_tokens(text_of(fields['keywords'], keep_breaks=False))
    zok, zok_raw = split_tokens(text_of(fields['zokusei'], keep_breaks=False))
    suisen_html = fields['suisen']
    comment_html = fields['comment'] if fields['comment'] is not None else fields['comment_legacy']
    return {
        'title': title,
        'title_raw': title,
        'href': links[0]['href'],
        'author': author,
        'homepage': homepage,
        'illustrator': illus,
        'illustrator_url': None,
        'illustrator_raw': illus_raw,
        'legacy_credit_raw': None,
        'date': date,
        'date_raw': date_raw,
        'date_precision': 'exact',
        'weekday': None,
        'size_kb': int(sm.group(1)) if sm else None,
        'files_n': int(sm.group(2)) if sm and sm.group(2) else None,
        'kansou_slug': None,
        'noteky_url': noteky,
        'arasuji': body_text(fields['arasuji']),
        'comment': body_text(comment_html),
        'suisen': body_text(suisen_html),
        'osusume': None,
        'nav_links': nav_links_of(suisen_html or '') + nav_links_of(fields['arasuji'] or ''),
        'inline_links': plain_links_of(fields['arasuji'] or '') + plain_links_of(comment_html or ''),
        'genre': genre, 'genre_raw': genre_raw,
        'type': typ, 'type_raw': type_raw,
        'keywords': kw, 'keywords_raw': kw_raw,
        'zokusei': zok, 'zokusei_raw': zok_raw,
        'notice': text_of(fields['notice']) if fields['notice'] else None,
        'catalog_ref': '%s#%d' % (page, ordinal),
        'legacy_format': 'table',
        'legacy_image_flag': image_flag,
        # 作者欄が `***` で分類欄も無いブロックは作品ではなく編集部の告知
        # (シリーズ INDEX ページの案内など)。author 解決の対象から外す。
        'entry_role': ('notice' if author is None and not (genre or typ or kw) else 'work'),
        'commented_out': False,
        'dropped_mailto': sorted(set(dropped)),
        '_suisen_full_empty': not text_of(suisen_html or ''),
    }


def parse_legacy_table_page(page, src):
    """lib07〜09 の 1 ページ分。戻り値 (entries, failures)。"""
    entries, failures = [], []
    for i, table in enumerate(RE_TABLE.findall(src), 1):
        try:
            entries.append(parse_legacy_table_entry(table, page, i))
        except ParseError as exc:
            failures.append(str(exc))
    return entries, failures


def parse_legacy_pages(flat_pages, table_pages):
    """旧目録の全ブロックを (エントリ, 失敗) で返す。
    flat_pages / table_pages は [(page 名, HTML の全文)] (ファイルの読み込みは呼び出し側)。"""
    entries, failures = [], []
    for page, src in flat_pages:
        entries.extend(parse_legacy_flat_page(page, src, failures))
    for page, src in table_pages:
        e, f = parse_legacy_table_page(page, src)
        entries.extend(e)
        failures.extend(f)
    return entries, failures
