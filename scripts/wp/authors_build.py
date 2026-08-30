#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""authors_build.py — 作者を `catalog/authors.json` にまとめる (進行台帳 タスク 1.4)。

入力
  catalog/episodes.jsonl   … 1.1/1.2 の出力 (episode_overrides.py 適用後)
  catalog/terms.json       … ts_world (= 共有感想板の一覧。作者板と区別するために要る)
  catalog/slug_overrides.yml … 👤 1.5b の裁定 (confirmed / role: not-an-author)
  ~ts/kansou/bbs@log_.cgi  … **サイト自身の感想板一覧 (290 板)**。「表示名 → 板 id」の
                             権威ある対応表。目録から板がリンクされていない作者を拾う
  ~ts/kansou/bbs@log_*.cgi … 板一覧に載らない板ファイル (<title> から作者名が取れる)
  lib-index-*.html         … 作家別五十音目録。yomi (所属行) と題名→作者の第二情報源

出力
  catalog/authors.json             … 作者 1 人 1 レコード
  catalog/slug_overrides.yml       … 板を持たない作者の slug 候補 (👤 1.5b の確認対象)
  catalog/reports/authors_build.json

**同定の考え方** — slug は当時の感想板 id (`~ts/kansou/bbs@log_<id>.cgi`) が最も信頼できる。
これは作者本人に紐づく恒久 id で、表記揺れ (バレット/石積ナラ、ＫＥＢＯ/KEBO/けぼ) を
当時の運営自身が同一視していた証拠でもある。ただし:

  - **共有シリーズ板 7 つ (kayo_chan / himekami / foster / relay_novel / delayed /
    2daime / utanotsuki) は作者の板ではない**。そこに載る話は表示名で同定する
  - 旧目録 (corpus=legacy) には板リンクが無い (作品単位の noteky ノートしかない)。
    表示名で正規目録 (corpus=honkan) 側の作者に寄せ、寄らないものは新規採番

**板 id の情報源は 2 つある** (2026-08-30 追加。review/authors-slugs.md §8-1 の恒久対策):

  1 目録 (`lib*.html`) の感想リンク … 話ごとに付いている
  2 **サイトの感想板一覧** `~ts/kansou/bbs@log_.cgi` … 板の側から引く対応表

1 だけを見ていたため「板はあるのに目録からリンクされていない」作者 7 名を
機械の読み (pykakasi) で採番してしまっていた。2 を第二の情報源にすると構造的に消える。
板一覧に載らない板ファイル (`bbs@log_memuro_makoto.cgi` 等) も `<title>` から拾う。
再発防止として、自己検査に**「板一覧が示す板 id と作者 slug の不一致 0」**を置いた。

**作者の併合** — 👤 1.5b の裁定 (`slug_overrides.yml` の `status: confirmed`) で
複数の表示名が同じ slug を指すとき (根室　眞琴 → slents、麗香 → marie、ぽぽ/HIKU → popo、
海津里花 → aizu_rika 等)、**1 人の作者に統合**し、表示名は `display_variants` に集約する。
`role: not-an-author` の名前 (シェアワールド) は作者を作らない。

表示名の突き合わせは NFKC + 空白除去 + casefold。ＭＯＮＤＯ と MONDO、ｔｏｓｈｉ９ と
toshi9 はこれで同じ鍵になる。
"""
import argparse
import collections
import glob
import html
import json
import os
import re
import sys
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import slugs as slugmod                                   # noqa: E402

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DEFAULT_ANNEX_BASE = 'https://takano32.github.io/ts-novels/'
WAYBACK_FMT = 'https://web.archive.org/web/2010/%s'
EXPECTED_AUTHORS = 399                      # survey 実測 (表示名の異なり数)

RE_TAG = re.compile(r'<[^>]+>')
RE_H2 = re.compile(r'<H2>\s*-?\s*(.*?)\s*-?\s*</H2>', re.I)
RE_ROW = re.compile(r'<TR[^>]*>\s*<TD[^>]*>(.*?)</TD>\s*<TD[^>]*>(.*?)</TD>\s*</TR>', re.S | re.I)
RE_ANCHOR = re.compile(
    r'<a\s+[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>', re.S | re.I)
INDEX_PAGES = ['lib-index-%s.html' % s for s in
               ('aa', 'ka', 'sa', 'ta', 'na', 'ha', 'ma', 'ya', 'ra', 'wa', 'en', 'etc')]
INDEX_PAGES_OLD = ['lib-index-%d.html' % i for i in range(1, 5)]

# --- 感想板一覧 (~ts/kansou/bbs@log_.cgi) ------------------------------------
KANSOU_DIR = os.path.join('~ts', 'kansou')
BOARD_LIST = 'bbs@log_.cgi'
RE_BOARD_ROW = re.compile(
    r'<TR>\s*<TD>\s*<A\s+HREF="bbs@log_([^"]*?)\.cgi"\s*>(.*?)</A>', re.I | re.S)
RE_BOARD_TITLE = re.compile(r'<title>(.*?)</title>', re.I | re.S)
# 「○○さん作品　感想掲示板」「○○さん感想掲示板」「○○さん　感想BBS」…の定型を剥がす
RE_BOARD_SUFFIX = re.compile(
    r'(?:さん|様)?[\s　]*(?:達)?(?:の)?[\s　]*(?:作品)?[\s　]*(?:の)?[\s　]*(?:感想)?[\s　]*'
    r'(?:専用)?[\s　]*(?:掲示板|BBS|ＢＢＳ)[\s　]*$', re.I)
# 板一覧そのもの / 作者に紐づかない汎用板。作者名の情報源にしない
BOARD_TITLE_DENY = re.compile(r'感想掲示板一覧|マルチ掲示板|活動報告|^BBS$|Crocus', re.I)


def norm_name(name):
    """表示名の突き合わせ鍵。NFKC + 空白除去 + casefold。"""
    if not name:
        return ''
    s = unicodedata.normalize('NFKC', name)
    s = re.sub(r'[\s　]+', '', s)
    return s.casefold()


def text_of(fragment):
    if fragment is None:
        return None
    s = RE_TAG.sub('', fragment)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip() or None


# --------------------------------------------------------------------------- lib-index

def parse_index_old(root, page):
    """lib-index-1〜4.html (旧世代版) は列の並びが逆で、作者セルが rowspan で
    複数行にまたがる: `<td rowspan=N><b>作者</b></td><td><a>題名</a></td>` +
    続く N-1 行は題名セルだけ。作者を持ち越しながら読む。"""
    path = os.path.join(root, page)
    if not os.path.exists(path):
        return []
    with open(path, encoding='utf-8') as fh:
        src = fh.read()
    rows, author, remain = [], None, 0
    for m in re.finditer(r'<tr[^>]*>(.*?)</tr>', src, re.S | re.I):
        cells = re.findall(r'<td([^>]*)>(.*?)</td>', m.group(1), re.S | re.I)
        if not cells:
            continue
        if len(cells) >= 2:
            attrs, body = cells[0]
            name = text_of(body)
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
        href = html.unescape((links[0][0] or links[0][1] or links[0][2] or '').strip())
        title = text_of(links[0][3])
        if not href or not title:
            continue
        rows.append({'page': page, 'path': href, 'title': title,
                     'author': author, 'homepage': None, 'kana': None})
    return rows


def parse_index(root):
    """作家別五十音目録 → (rows, yomi_of_name)。

    rows = [{path, title, author, homepage, kana, page}] — 題名→作者の第二情報源
    (タスク 1.8 の目録外収蔵でも使う)。yomi_of_name は表示名 → 所属行 (あ行/A/その他)。
    """
    rows, yomi = [], {}
    for page in INDEX_PAGES_OLD:
        rows.extend(parse_index_old(root, page))
    for page in INDEX_PAGES:
        path = os.path.join(root, page)
        if not os.path.exists(path):
            continue
        with open(path, encoding='utf-8') as fh:
            src = fh.read()
        heads = [(m.start(), text_of(m.group(1))) for m in RE_H2.finditer(src)]
        for m in RE_ROW.finditer(src):
            links = RE_ANCHOR.findall(m.group(1))
            if not links:
                continue
            href = html.unescape((links[0][0] or links[0][1] or links[0][2] or '').strip())
            title = text_of(links[0][3])
            cell = m.group(2)
            homepage = None
            for a in RE_ANCHOR.finditer(cell):
                url = html.unescape((a.group(1) or a.group(2) or a.group(3) or '').strip())
                if url.lower().startswith('mailto:'):
                    continue
                if url:
                    homepage = url
            author = text_of(re.sub(r'\(\s*homepage\s*\)', '', RE_TAG.sub('|', cell), flags=re.I)
                             .replace('|', ' '))
            if not author or not title:
                continue
            kana = None
            for pos, name in heads:
                if pos < m.start():
                    kana = name
            rows.append({'page': page, 'path': href, 'title': title,
                         'author': author, 'homepage': homepage, 'kana': kana})
            if kana and page in INDEX_PAGES:
                yomi.setdefault(norm_name(author), kana)
    return rows, yomi


# --------------------------------------------------------------------------- 感想板一覧

def board_names_of(title):
    """板の見出し → その板が誰の板かを表す表示名 (0〜2 件)。

    「ぽぽさん(HIKUさん)作品感想掲示板」のように**サイト自身が同一人物の別名を
    括弧で併記している**板があるので、括弧の内外を別々の名前として返す。
    『華代ちゃんシリーズ』のような括弧書き・「〜シリーズ」は作者名ではないので落とす。
    """
    t = html.unescape(RE_TAG.sub('', title or ''))
    t = re.sub(r'[\s　]+', ' ', t).strip()
    if not t or BOARD_TITLE_DENY.search(t):
        return []
    t = RE_BOARD_SUFFIX.sub('', t).strip()
    if not t:
        return []
    m = re.match(r'^(.*?)[（(](.+?)[)）]$', t)
    parts = [m.group(1), m.group(2)] if m else [t]
    out = []
    for part in parts:
        name = part.strip()
        for _ in range(2):                      # 「○○さんさん」対策に 2 回まで
            name = re.sub(r'(?:さん|様)$', '', name).strip()
        if not name or re.match(r'^[「『].*[」』]$', name) or name.endswith('シリーズ'):
            continue
        out.append(name)
    return out


def parse_boards(root, shared):
    """感想板の実体から「表示名 → 板 id」を引く。

    戻り値 (name_to_boards, stats)。name_to_boards の値は板 id のリストで、
    **板一覧 (bbs@log_.cgi) に載っている板を先頭**にする (載っている方が権威)。
    共有シリーズ板は作者の板ではないので除く。
    """
    base = os.path.join(root, KANSOU_DIR)
    listed, disk_only = collections.OrderedDict(), collections.OrderedDict()
    rows = 0
    list_path = os.path.join(base, BOARD_LIST)
    if os.path.exists(list_path):
        with open(list_path, encoding='utf-8', errors='replace') as fh:
            for slug, title in RE_BOARD_ROW.findall(fh.read()):
                rows += 1
                if slug and slug not in shared:
                    listed.setdefault(slug, title)
    for path in sorted(glob.glob(os.path.join(base, 'bbs@log_*.cgi'))):
        slug = os.path.basename(path)[len('bbs@log_'):-len('.cgi')]
        if not slug or slug in listed or slug in shared:
            continue
        with open(path, encoding='utf-8', errors='replace') as fh:
            m = RE_BOARD_TITLE.search(fh.read(65536))
        if m:
            disk_only.setdefault(slug, m.group(1))

    name_to_boards = collections.OrderedDict()
    named = [0, 0]
    for i, table in enumerate((listed, disk_only)):
        for slug, title in table.items():
            names = board_names_of(title)
            if names:
                named[i] += 1
            for name in names:
                name_to_boards.setdefault(norm_name(name), [])
                if slug not in name_to_boards[norm_name(name)]:
                    name_to_boards[norm_name(name)].append(slug)
    stats = collections.OrderedDict([
        ('board_list_rows', rows),
        ('board_list_personal', len(listed)),
        ('board_list_shared_world', rows - len(listed)),
        ('board_list_with_author_name', named[0]),
        ('board_files_not_listed', len(disk_only)),
        ('board_files_not_listed_with_author_name', named[1]),
        ('board_files_not_listed_slugs', sorted(disk_only)),
        ('names_with_board', len(name_to_boards)),
    ])
    return name_to_boards, stats


# --------------------------------------------------------------------------- 同定

def shared_boards_of(root):
    path = os.path.join(root, 'catalog', 'terms.json')
    if not os.path.exists(path):
        return set()
    with open(path, encoding='utf-8') as fh:
        terms = json.load(fh)
    out = set()
    for term in terms['taxonomies'].get('ts_world', {}).get('terms', []):
        out.update(term.get('rule', {}).get('boards') or [])
    return out


NO_AUTHOR_BY_DESIGN = ('notice', 'unattributed', 'series-index')


def build(root, annex_base=DEFAULT_ANNEX_BASE):
    cat = os.path.join(root, 'catalog')
    records = [json.loads(line) for line in
               open(os.path.join(cat, 'episodes.jsonl'), encoding='utf-8')]
    shared = shared_boards_of(root)
    index_rows, yomi_of = parse_index(root)
    board_of_name, board_stats = parse_boards(root, shared)

    # 👤 1.5b の裁定を先に読む。confirmed は「表示名 → 確定 slug」で、
    # role: not-an-author は「この表示名では作者を作らない」の意。
    overrides_path = os.path.join(cat, 'slug_overrides.yml')
    overrides = slugmod.load_overrides(overrides_path)
    prev = overrides.get('authors') or {}
    confirmed = {norm_name(k): v for k, v in prev.items()
                 if isinstance(v, dict) and v.get('status') == 'confirmed' and v.get('slug')}
    not_authors = {k for k, v in confirmed.items() if v.get('role') == 'not-an-author'}

    # (1) 作者板を持つ作者 — 板 id が鍵
    name_boards = collections.defaultdict(collections.Counter)
    for rec in records:
        slug = rec.get('kansou_slug')
        if slug and slug not in shared and rec.get('author'):
            name_boards[norm_name(rec['author'])][slug] += 1

    # (2) 全 episode を作者鍵に割り当てる
    assign, unresolved, ambiguous, notices = {}, [], [], []
    not_an_author_eps, from_board_list = [], {}
    new_keys = {}
    for rec in records:
        slug = rec.get('kansou_slug')
        name = rec.get('author')
        nname = norm_name(name)
        key, how = None, None
        if name and nname in not_authors:
            # 「シェアワールド」のように作者欄に人名でない値が入っているもの。
            # 作者不詳ではなく「作者ではない」— 話は ts_world 側で拾われる。
            not_an_author_eps.append(rec['episode_id'])
            continue
        if slug and slug not in shared:
            key, how = slug, 'board'
        elif name:
            boards = name_boards.get(nname)
            if boards:
                key, how = boards.most_common(1)[0][0], 'name->board'
                if len(boards) > 1:
                    ambiguous.append({'author': name, 'boards': dict(boards),
                                      'episode_id': rec['episode_id']})
            else:
                # 目録から一度もリンクされていない板を**サイトの板一覧から**引く。
                # 裁定 (confirmed) がある名前は裁定が勝つ — 板一覧が示す板 id と
                # 裁定が食い違うとき (根室　眞琴 → 板 memuro_makoto / 裁定 slents) は
                # 板で鍵を作らず、裁定の slug で後段の併合に載せる。
                cand = board_of_name.get(nname) or []
                conf = confirmed.get(nname)
                pick = cand[0] if (cand and not conf) else \
                    (conf.get('slug') if conf and conf.get('slug') in cand else None)
                if pick:
                    key, how = pick, 'name->board-list'
                    from_board_list[nname] = pick
                else:
                    key = new_keys.setdefault(nname, 'name:' + nname)
                    how = 'name-only'
        else:
            # ここに来るのは (a) 作者欄が `***` の編集部告知ブロック (旧目録 lib07#16)、
            # (b) 目録外収蔵で作者が特定できなかったもの (entry_role=unattributed)、
            # (c) シリーズ目次ページ (entry_role=series-index)。
            # どれも「取りこぼし」ではない。
            if rec.get('entry_role') in NO_AUTHOR_BY_DESIGN:
                notices.append(rec['episode_id'])
            else:
                unresolved.append(rec['episode_id'])
            continue
        assign[rec['episode_id']] = (key, how)

    # (3) 作者レコードを組む
    authors = collections.OrderedDict()
    for rec in records:
        got = assign.get(rec['episode_id'])
        if not got:
            continue
        key, how = got
        a = authors.setdefault(key, {
            'key': key,
            'board_slug': key if not key.startswith('name:') else None,
            'display_variants': collections.Counter(),
            'episodes': [], 'corpora': collections.Counter(),
            'homepages': collections.Counter(), 'match_how': collections.Counter(),
            'first_date': None, 'last_date': None, 'shared_boards': set(),
        })
        if rec.get('author'):
            a['display_variants'][rec['author']] += 1
        a['episodes'].append(rec['episode_id'])
        a['corpora'][rec['corpus']] += 1
        a['match_how'][how] += 1
        if rec.get('homepage'):
            a['homepages'][rec['homepage']] += 1
        d = rec.get('date')
        if d:
            a['first_date'] = min(a['first_date'] or d, d)
            a['last_date'] = max(a['last_date'] or d, d)
        if rec.get('kansou_slug') in shared:
            a['shared_boards'].add(rec['kansou_slug'])

    # 五十音目録の homepage も拾う (目録に載らない作者の分)
    index_hp = collections.defaultdict(collections.Counter)
    for row in index_rows:
        if row['homepage']:
            index_hp[norm_name(row['author'])][row['homepage']] += 1

    # (4) slug — 板 id はそのまま、板なしは裁定 (confirmed) → pykakasi 候補
    used, entries = set(), {}
    ordered = sorted(authors.values(), key=lambda a: (-len(a['episodes']), a['key']))
    for a in ordered:
        if a['board_slug']:
            used.add(a['board_slug'])
    # 裁定済みの slug は先に予約しておく (候補が先に取ってしまうのを防ぐ)
    used.update(v['slug'] for v in confirmed.values() if v.get('role') != 'not-an-author')
    for a in ordered:
        name = a['display_variants'].most_common(1)[0][0] if a['display_variants'] else a['key']
        a['display_name'] = name
        if a['board_slug']:
            a['slug'], a['slug_source'], a['slug_status'] = a['board_slug'], 'kansou-board', 'auto'
            continue
        old = confirmed.get(norm_name(name))
        if old:
            a['slug'], a['slug_source'], a['slug_status'] = old['slug'], old.get('source'), 'confirmed'
            continue
        slug, source = slugmod.romanize(name)
        slug = slugmod.unique_slug(slug, used, fallback_prefix='author')
        a['slug'], a['slug_source'] = slug, source
        a['slug_status'] = 'auto' if source == 'ascii' else 'candidate'

    # (5) 裁定による併合 — 同じ slug に落ちた作者レコードを 1 人にまとめる。
    # 「根室　眞琴」と「スレントス -Slents-」、「麗香」と「Ｍａｒｉｅ」のように
    # 表示名が違うだけの同一人物を、確定 slug を鍵にして 1 レコードへ集約する。
    by_slug = collections.OrderedDict()
    for a in ordered:
        by_slug.setdefault(a['slug'], []).append(a)
    merges, ordered_merged = [], []
    for slug, group in by_slug.items():
        if len(group) == 1:
            ordered_merged.append(group[0])
            continue
        # 板を持つレコードを土台にする (無ければ話数最大)
        base = next((g for g in group if g['board_slug'] == slug), group[0])
        rest = [g for g in group if g is not base]
        merges.append({'slug': slug, 'kept': base['display_name'],
                       'merged': [g['display_name'] for g in rest],
                       'episodes_added': sum(len(g['episodes']) for g in rest)})
        for g in rest:
            base['display_variants'].update(g['display_variants'])
            base['episodes'].extend(g['episodes'])
            base['corpora'].update(g['corpora'])
            base['homepages'].update(g['homepages'])
            base['match_how'].update(g['match_how'])
            base['shared_boards'] |= g['shared_boards']
            for field, pick in (('first_date', min), ('last_date', max)):
                if g[field]:
                    base[field] = pick(base[field] or g[field], g[field])
            if (not base['board_slug'] and base['slug_status'] != 'confirmed'
                    and g['slug_status'] == 'confirmed'):
                base['slug_source'], base['slug_status'] = g['slug_source'], 'confirmed'
        base['display_name'] = base['display_variants'].most_common(1)[0][0]
        ordered_merged.append(base)
    ordered = sorted(ordered_merged, key=lambda a: (-len(a['episodes']), a['key']))

    # (6) slug_overrides.yml へ書き戻す行を作る。代表表示名のほかに、
    # **裁定済みの表示名は併合で代表でなくなっても必ず残す** (stale 印が付かないように)。
    for a in ordered:
        entries[a['display_name']] = {'slug': a['slug'], 'status': a['slug_status'],
                                      'source': a['slug_source']}
        for variant in a['display_variants']:
            old = prev.get(variant)
            if isinstance(old, dict) and old.get('status') == 'confirmed':
                entries.setdefault(variant, old)
    for name, rec in prev.items():                 # 作者を作らない名前も台帳に残す
        if isinstance(rec, dict) and rec.get('role') == 'not-an-author':
            entries[name] = rec
    overrides = slugmod.merge_section(overrides, 'authors', entries)

    out = []
    for a in ordered:
        name = a['display_name']
        hp = a['homepages'].most_common(1)
        if not hp:
            hp = index_hp.get(norm_name(name), collections.Counter()).most_common(1)
        homepage = hp[0][0] if hp else None
        # この作者の表示名が指す板を板一覧・板ファイルから全部引く。旧名義の板
        # (薪喬 → shinkyou、根室　眞琴 → memuro_makoto)・別名の板 (HIKU → hiku)・
        # 誤植の板 (kamikawa_ayano_) がここに残る
        implied = []
        for variant in a['display_variants']:
            for b in board_of_name.get(norm_name(variant), []):
                if b not in implied:
                    implied.append(b)
        a['implied_boards'] = implied
        out.append(collections.OrderedDict([
            ('slug', a['slug']),
            ('display_name', name),
            ('display_variants', [n for n, _ in a['display_variants'].most_common()]),
            ('yomi_group', yomi_of.get(norm_name(name))),
            ('kansou_slug', a['board_slug']),
            ('kansou_annex_url',
             annex_base + '~ts/kansou/bbs@log_%s.cgi' % a['board_slug'] if a['board_slug'] else None),
            ('kansou_slug_alt', [b for b in implied if b != a['board_slug']]),
            ('shared_boards', sorted(a['shared_boards'])),
            ('homepage', homepage),
            ('homepage_wayback', WAYBACK_FMT % homepage if homepage else None),
            ('homepage_all', [u for u, _ in a['homepages'].most_common()]),
            ('active_links', []),          # なろう/pixiv 等。7.1 の作者連絡で埋める
            ('contact_status', 'uncontacted'),
            ('episode_count', len(a['episodes'])),
            ('corpora', dict(a['corpora'])),
            ('first_date', a['first_date']),
            ('last_date', a['last_date']),
            ('identified_by', dict(a['match_how'])),
            ('slug_source', a['slug_source']),
            ('slug_status', a['slug_status']),
            ('episodes', a['episodes']),
        ]))

    variant_groups = [a for a in out if len(a['display_variants']) > 1]
    by_name = collections.defaultdict(list)
    for a in out:
        for v in a['display_variants']:
            by_name[norm_name(v)].append(a['slug'])
    shared_names = [[k, v] for k, v in by_name.items() if len(set(v)) > 1]

    # 板一覧との突き合わせ — その作者の表示名がいずれかの板を指しているのに、
    # 作者 slug がどの板 id とも一致しないなら**取りこぼしか誤同定**。
    # (review/authors-slugs.md §8-1 で 7 名を出した見落としを機械で塞ぐ検査)
    board_mismatch = [
        {'slug': a['slug'], 'display_name': a['display_name'],
         'display_variants': a['display_variants'],
         'boards': a2['implied_boards'], 'episodes': a['episode_count']}
        for a, a2 in zip(out, ordered)
        if a2['implied_boards'] and a['slug'] not in a2['implied_boards']]
    confirmed_slugs = {v['slug'] for k, v in confirmed.items()
                       if v.get('role') != 'not-an-author'}
    confirmed_missing = sorted(confirmed_slugs - {a['slug'] for a in out})
    # 板 id は分かっているが、板ログの原本が回収できていない作者。
    # kansou_annex_url は別館に実体が無い (=書誌カードの「当時の感想を読む」が出せない)。
    # 板 id 自体は当時のサイトが付けた正しい値なので slug には使う。
    board_file_missing = sorted(
        a['slug'] for a in out if a['kansou_slug'] and not os.path.exists(
            os.path.join(root, KANSOU_DIR, 'bbs@log_%s.cgi' % a['kansou_slug'])))

    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/authors_build.py'),
        ('episodes', len(records)),
        ('authors', len(out)),
        ('authors_with_board', sum(1 for a in out if a['kansou_slug'])),
        ('authors_without_board', sum(1 for a in out if not a['kansou_slug'])),
        ('display_name_strings_distinct',
         len({r['author'] for r in records if r.get('author')})),
        ('display_variant_groups', len(variant_groups)),
        ('display_variants_merged',
         sum(len(a['display_variants']) - 1 for a in variant_groups)),
        ('display_variant_slots', sum(len(a['display_variants']) for a in out)),
        # 同じ表示名を別々の作者鍵が使っている = 同名別人か、統合し損ねている可能性
        ('display_names_shared_by_authors', len(shared_names)),
        ('display_names_shared_detail', shared_names[:20]),
        ('display_variant_examples',
         [[a['slug'], a['display_variants']] for a in variant_groups[:20]]),
        ('shared_boards', sorted(shared)),
        ('episodes_unresolved_author', unresolved),
        ('episodes_no_author_by_design', notices),
        ('episodes_no_author_by_design_count', len(notices)),
        ('episodes_not_an_author', not_an_author_eps),
        ('episodes_assigned', len(assign)),
        ('ambiguous_name_to_board', ambiguous[:20]),
        ('ambiguous_name_to_board_count', len(ambiguous)),
        ('yomi_resolved', sum(1 for a in out if a['yomi_group'])),
        ('homepage_present', sum(1 for a in out if a['homepage'])),
        ('index_rows_parsed', len(index_rows)),
        ('index_authors_distinct', len({norm_name(r['author']) for r in index_rows})),
        # --- 感想板一覧を第二の情報源にした効果 (2026-08-30) ---
        ('kansou_board_sources', board_stats),
        ('authors_identified_by_board_list', len(from_board_list)),
        ('authors_identified_by_board_list_detail',
         sorted(from_board_list.items(), key=lambda kv: kv[1])),
        ('authors_with_alt_board', sum(1 for a in out if a['kansou_slug_alt'])),
        ('authors_with_missing_board_file', len(board_file_missing)),
        ('authors_with_missing_board_file_slugs', board_file_missing),
        ('board_slug_mismatch', board_mismatch),
        ('board_slug_mismatch_count', len(board_mismatch)),
        # --- 👤 1.5b の裁定 (confirmed) の反映状況 ---
        ('confirmed_names', len(confirmed)),
        ('confirmed_slugs_distinct', len(confirmed_slugs)),
        ('confirmed_slugs_missing_in_authors', confirmed_missing),
        ('not_an_author_names', sorted(prev[k]['slug'] for k in prev
                                       if isinstance(prev[k], dict)
                                       and prev[k].get('role') == 'not-an-author')),
        ('author_merges', merges),
        ('author_merges_count', len(merges)),
        ('authors_removed_by_merge', sum(len(m['merged']) for m in merges)),
        ('slug_sources', dict(collections.Counter(a['slug_source'] for a in out))),
        ('slug_pending_review', sum(1 for a in out if a['slug_status'] == 'candidate')),
    ])
    payload = collections.OrderedDict([
        ('generated_by', 'scripts/wp/authors_build.py'),
        ('annex_base', annex_base),
        ('authors', out),
    ])
    return payload, report, overrides, overrides_path, index_rows


def selftest(payload, report):
    results = []

    def check(name, ok, detail=''):
        results.append((name, bool(ok), detail))
    n = report['authors']
    # 台帳の「作者数 ≈ 399」は survey の author_count=399 を引いたものだが、
    # 399 は**表示名の異なり数**であって作者の人数ではない。表記揺れを感想板 id で
    # 統合すると人数はこれより減る。ここでは「表示名 − 統合で消えた分 = 作者数」が
    # 帳尻として合うことを検査する。
    check('作者数の内訳が合う (延べ表示名 − 統合分 == 作者数)',
          report['display_variant_slots'] - report['display_variants_merged'] == n,
          '%d - %d == %d' % (report['display_variant_slots'],
                             report['display_variants_merged'], n))
    check('作者数を記録 (台帳の ≈399 は表示名の異なり数であり人数ではない)', True,
          '実測 %d 名 / 表示名 %d 種 / 複数作者が使う表示名 %d 種'
          % (n, report['display_name_strings_distinct'],
             report['display_names_shared_by_authors']))
    check('全 episode の author が解決 (未解決 0)',
          not report['episodes_unresolved_author'],
          '未解決 %d 件 %s' % (len(report['episodes_unresolved_author']),
                              report['episodes_unresolved_author'][:5]))
    check('episode の割り当て漏れ 0 (作者不詳の告知/目録外を除く)',
          report['episodes_assigned'] + report['episodes_no_author_by_design_count']
          + len(report['episodes_not_an_author']) == report['episodes'],
          '%d + 作者不詳 %d + 作者ではない %d / %d'
          % (report['episodes_assigned'], report['episodes_no_author_by_design_count'],
             len(report['episodes_not_an_author']), report['episodes']))
    slugs = [a['slug'] for a in payload['authors']]
    dup = [s for s, c in collections.Counter(slugs).items() if c > 1]
    check('slug の重複 0', not dup, '重複 %s' % dup[:5])
    check('板を持つ作者の slug は板 id',
          all(a['slug'] == a['kansou_slug'] for a in payload['authors'] if a['kansou_slug']))
    # 感想板一覧 (~ts/kansou/bbs@log_.cgi、290 板) との突き合わせ。
    # 「板があるのに機械の読みで採番していた 7 名」(review §8-1) の再発を構造的に防ぐ。
    check('confirmed slug と板一覧の不一致 0 (板がある表示名は必ずその板 id を使う)',
          report['board_slug_mismatch_count'] == 0,
          '不一致 %d 件 %s' % (report['board_slug_mismatch_count'],
                               [m['display_name'] for m in report['board_slug_mismatch'][:5]]))
    check('👤 1.5b の裁定 (confirmed) が全て authors.json に出ている',
          not report['confirmed_slugs_missing_in_authors'],
          '未反映 %s' % report['confirmed_slugs_missing_in_authors'][:5])
    check('裁定による作者の併合を記録', True,
          '%d 組 / 作者 %d 名が併合で消えた'
          % (report['author_merges_count'], report['authors_removed_by_merge']))
    # 検査ではなく記録 — 板ログの原本が未回収なだけで、板 id 自体は正しい
    check('板 id は分かるが板ログの原本が未回収の作者 (記録のみ)', True,
          '%d 名 %s' % (report['authors_with_missing_board_file'],
                        report['authors_with_missing_board_file_slugs'][:5]))
    return all(ok for _, ok, _ in results), results


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    payload, report, overrides, overrides_path, _ = build(root, args.annex_base)
    ok, results = selftest(payload, report)
    report['selftest'] = [{'name': n, 'ok': o, 'detail': d} for n, o, d in results]
    report['selftest_ok'] = ok

    print('authors=%d (板あり %d / 板なし %d) 表示名の異なり=%d 表記揺れ統合 %d 組' %
          (report['authors'], report['authors_with_board'], report['authors_without_board'],
           report['display_name_strings_distinct'], report['display_variant_groups']))
    print('yomi 解決=%d homepage=%d 五十音目録の行=%d' %
          (report['yomi_resolved'], report['homepage_present'], report['index_rows_parsed']))
    bs = report['kansou_board_sources']
    print('感想板一覧=%d 板 (共有世界の板 %d を除く作者板 %d / 作者名が取れた %d) + '
          '一覧に無い板ファイル=%d / 板一覧で拾った作者=%d 名 別名義の板=%d 名' %
          (bs['board_list_rows'], bs['board_list_shared_world'], bs['board_list_personal'],
           bs['board_list_with_author_name'], bs['board_files_not_listed'],
           report['authors_identified_by_board_list'], report['authors_with_alt_board']))
    print('裁定 confirmed=%d 名 → slug %d 種 / 併合 %d 組 (作者 %d 名が統合で消えた)' %
          (report['confirmed_names'], report['confirmed_slugs_distinct'],
           report['author_merges_count'], report['authors_removed_by_merge']))
    for m in report['author_merges']:
        print('  %s ← %s' % (m['slug'], ' / '.join([m['kept']] + m['merged'])))
    print('slug: %s  確認待ち=%d' % (report['slug_sources'], report['slug_pending_review']))
    for n, o, d in results:
        print('  [%s] %s %s' % ('OK ' if o else 'NG ', n, d))

    if not args.check:
        os.makedirs(os.path.join(root, 'catalog', 'reports'), exist_ok=True)
        with open(os.path.join(root, 'catalog', 'authors.json'), 'w', encoding='utf-8') as fh:
            json.dump(payload, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        with open(os.path.join(root, 'catalog', 'reports', 'authors_build.json'),
                  'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        slugmod.save_overrides(overrides_path, overrides, notes=[
            'terms_* セクション = 分類語彙の slug (scripts/wp/terms_build.py)',
            'authors セクション = 作者 slug (scripts/wp/authors_build.py)',
            'works セクション = 作品 slug (scripts/wp/work_builder.py)',
        ])
        print('wrote catalog/authors.json, catalog/reports/authors_build.json, '
              'catalog/slug_overrides.yml')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
