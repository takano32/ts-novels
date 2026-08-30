#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""work_builder.py — Episode を Work にまとめる (進行台帳 タスク 1.5)。

入力
  catalog/episodes.jsonl / catalog/authors.json
  series.html            … 完結シリーズ一覧 (シード)
  novel/**/title*.htm*   … シリーズタイトルページ (シード)
出力
  catalog/works.jsonl          … Work 1 件 1 行
  catalog/work_overrides.yml   … あいまいなクラスタの人手上書き雛形 (👤 1.5b)
  catalog/slug_overrides.yml   … work slug の候補 (👤 1.5b)
  catalog/reports/work_builder.json

**クラスタリングの根拠を強い順に積む** (どの規則で繋がったかは works.jsonl の
`evidence` に全部残す。弱い根拠しか無いクラスタは needs_review が立つ):

  1 series-title-nav  推薦文の【シリーズタイトルはこちら】が同じページを指す (最強)
  2 series-html       series.html の 1 行に列挙された各話リンク
  3 title-page        タイトルページ自身が目録エントリとして存在する場合の吸収
  4 episode-nav       【第N話はこちら】等が指す兄弟話
  5 filename-stem     同一ディレクトリ・同一作者で、末尾の連番を外したファイル名が同じ
  6 title-prefix      同一作者で題名の共通接頭辞が 4 文字以上 (最弱)

**Work は必ず 1 作者に閉じる**。共有世界 (華代ちゃん等) のタイトルページは 69 名の話を
1 つに束ねてしまうので、作者をまたぐ結合は行わない (シリーズとしての同一性は
ts_world タクソノミーが担う)。
"""
import argparse
import collections
import hashlib
import html
import json
import os
import re
import sys
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import slugs as slugmod                                   # noqa: E402

try:
    import yaml
except ImportError:                                       # pragma: no cover
    yaml = None

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

RE_TAG = re.compile(r'<[^>]+>')
RE_COMMENT = re.compile(r'<!--.*?-->', re.S)
RE_TR = re.compile(r'<TR[^>]*>(.*?)</TR>', re.S | re.I)
RE_TD = re.compile(r'<T[DH][^>]*>(.*?)</T[DH]>', re.S | re.I)
RE_A = re.compile(r'<a\s+[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>',
                  re.S | re.I)
RE_TITLE_PAGE = re.compile(r'(?:^|/)(?:[a-z0-9_\-]*title[a-z0-9_\-]*|index|contents)\.html?$', re.I)
RE_SERIES_NAV = re.compile(r'シリーズタイトル')
RE_EPISODE_NAV = re.compile(r'第[0-9０-９一二三四五六七八九十百]+[話章編]|前編|後編|中編|上巻|下巻')
# 題名から「第N話」「(前編)」などの巻号表記を落として作品名の芯を出す
RE_TITLE_TRIM = re.compile(
    r'(?:\s|　)*(?:[（(【「『]?\s*(?:第\s*[0-9０-９一二三四五六七八九十百]+\s*[話章巻部編幕夜]'
    r'|[0-9０-９]{1,3}|前編|後編|中編|完結編|最終話|序章|終章|外伝|番外編|特別編|Ｐａｒｔ\s*[0-9０-９]+'
    r'|part\s*[0-9]+|#[0-9]+|vol\.?\s*[0-9]+)\s*[）)】」』]?)+\s*$', re.I)
STEM_TRIM = re.compile(r'[-_]?(?:[0-9]{1,3})(?:[-_.][0-9]{1,3})?$')
# 巻号表記が題名の途中に来る形 (「女神仮面ミィミル 第一話 女神テオドラの帰還」) は
# そこで切ると作品名の芯が残る
RE_TITLE_CUT = re.compile(
    r'\s*(?:[（(【「『]\s*)?(?:第\s*[0-9０-９一二三四五六七八九十百]+\s*[話章巻部編幕夜]'
    r'|前編|後編|中編|完結編|最終話|序章|終章|外伝|番外編|特別編'
    r'|Ｐａｒｔ\s*[0-9０-９]+|part\s*[0-9]+|#\s*[0-9]+|vol\.?\s*[0-9]+)', re.I)


class Union:
    def __init__(self):
        self.parent = {}
        self.why = collections.defaultdict(set)

    def add(self, x):
        self.parent.setdefault(x, x)

    def find(self, x):
        self.add(x)
        while self.parent[x] != x:
            self.parent[x] = self.parent[self.parent[x]]
            x = self.parent[x]
        return x

    def union(self, a, b, rule):
        ra, rb = self.find(a), self.find(b)
        self.why[a].add(rule)
        self.why[b].add(rule)
        if ra != rb:
            self.parent[rb] = ra


def text_of(fragment):
    if fragment is None:
        return None
    s = html.unescape(RE_TAG.sub(' ', fragment))
    return re.sub(r'\s+', ' ', s).strip() or None


def norm_path(href, base=''):
    href = html.unescape((href or '').strip()).split('#')[0]
    href = re.sub(r'^(?:\./)+', '', href)
    if re.match(r'^[a-zA-Z][a-zA-Z0-9+.\-]*://', href):
        return None
    return href or None


def trim_title(title):
    t = unicodedata.normalize('NFKC', title or '').strip()
    cut = RE_TITLE_CUT.search(t)
    if cut and len(t[:cut.start()].strip(' 　-–—~〜「」『』()（）')) >= 2:
        t = t[:cut.start()]
    prev = None
    while prev != t:
        prev = t
        t = RE_TITLE_TRIM.sub('', t).strip(' 　-–—~〜「」『』()（）')
    return t or unicodedata.normalize('NFKC', title or '').strip()


def stem_of(path):
    base = os.path.basename(path)
    base = re.sub(r'\.html?$', '', base, flags=re.I)
    return STEM_TRIM.sub('', base) or base


def common_prefix(strings):
    if not strings:
        return ''
    a, b = min(strings), max(strings)
    i = 0
    while i < min(len(a), len(b)) and a[i] == b[i]:
        i += 1
    return a[:i]


# --------------------------------------------------------------------------- シード

def parse_series_html(root):
    """series.html → [{author, work_title, title_page, episode_paths[]}] (コメント行は除く)。"""
    path = os.path.join(root, 'series.html')
    if not os.path.exists(path):
        return []
    with open(path, encoding='utf-8') as fh:
        src = RE_COMMENT.sub('', fh.read())
    out = []
    for row in RE_TR.findall(src):
        cells = RE_TD.findall(row)
        if len(cells) < 2:
            continue
        author = text_of(cells[0])
        links = RE_A.findall(cells[1])
        if not author or author == '作者名':
            continue
        title_page, eps = None, []
        for g in links:
            href = norm_path(g[0] or g[1] or g[2])
            label = text_of(g[3]) or ''
            if not href:
                continue
            if RE_SERIES_NAV.search(label):
                title_page = href
            else:
                eps.append(href)
        work_title = text_of(RE_A.sub(' ', cells[1]))
        out.append({'author': author, 'work_title': work_title,
                    'title_page': title_page, 'episode_paths': eps})
    return out


def title_pages_on_disk(root):
    out = set()
    for base in ('novel', os.path.join('~yays', 'library', 'novel')):
        top = os.path.join(root, base)
        if not os.path.isdir(top):
            continue
        for dirpath, _dirs, files in os.walk(top):
            for name in files:
                rel = os.path.relpath(os.path.join(dirpath, name), root)
                if RE_TITLE_PAGE.search(rel):
                    out.add(rel)
    return out


# --------------------------------------------------------------------------- クラスタリング

def cluster(records, author_of):
    u = Union()
    for rec in records:
        u.add(rec['episode_id'])
    by_path = collections.defaultdict(list)
    for rec in records:
        by_path[rec['source_path']].append(rec)

    def link(a, b, rule):
        if a is b:
            return
        if author_of.get(a['episode_id']) != author_of.get(b['episode_id']):
            return                                  # Work は 1 作者に閉じる
        u.union(a['episode_id'], b['episode_id'], rule)

    # 1 series-title-nav — 同じシリーズタイトルページを指す話どうし
    nav_groups = collections.defaultdict(list)
    for rec in records:
        for nav in rec.get('nav_links') or []:
            if RE_SERIES_NAV.search(nav.get('label') or ''):
                tgt = norm_path(nav['href'])
                if tgt:
                    nav_groups[tgt].append(rec)
    for tgt, members in nav_groups.items():
        for m in members[1:]:
            link(members[0], m, 'series-title-nav')
        # 3 title-page — そのタイトルページ自体が目録エントリなら吸収する
        for host in by_path.get(tgt, []):
            link(members[0], host, 'title-page')

    # 2 series-html — 1 行に列挙された各話リンク
    for row in parse_series_html_cache:
        paths = list(row['episode_paths'])
        if row['title_page']:
            paths.append(row['title_page'])
        members = [r for p in paths for r in by_path.get(p, [])]
        for m in members[1:]:
            link(members[0], m, 'series-html')

    # 4 episode-nav — 【第N話はこちら】が指す兄弟話
    for rec in records:
        for nav in rec.get('nav_links') or []:
            label = nav.get('label') or ''
            if RE_SERIES_NAV.search(label) or not RE_EPISODE_NAV.search(label):
                continue
            tgt = norm_path(nav['href'])
            for other in by_path.get(tgt or '', []):
                link(rec, other, 'episode-nav')

    # 5 filename-stem — 同一ディレクトリ・同一作者の連番ファイル
    stem_groups = collections.defaultdict(list)
    for rec in records:
        if rec['source_kind'] != 'local':
            continue
        d = os.path.dirname(rec['source_path'])
        stem = stem_of(rec['source_path'])
        if len(stem) >= 3:
            stem_groups[(author_of.get(rec['episode_id']), d, stem)].append(rec)
    for members in stem_groups.values():
        for m in members[1:]:
            link(members[0], m, 'filename-stem')

    # 6 title-prefix — 同一作者・巻号を落とした題名が一致
    title_groups = collections.defaultdict(list)
    for rec in records:
        core = trim_title(rec.get('title'))
        if len(core) >= 4:
            title_groups[(author_of.get(rec['episode_id']), core)].append(rec)
    for members in title_groups.values():
        for m in members[1:]:
            link(members[0], m, 'title-prefix')
    return u


parse_series_html_cache = []


# --------------------------------------------------------------------------- 組み立て

STRONG = {'series-title-nav', 'series-html', 'title-page', 'episode-nav'}


def md5_of(path):
    try:
        with open(path, 'rb') as fh:
            return hashlib.md5(fh.read()).hexdigest()
    except OSError:
        return None


def episode_order_key(rec):
    m = re.search(r'(\d+)(?:\.html?)?$', re.sub(r'\.html?$', '', rec['source_path']))
    num = int(m.group(1)) if m else 0
    return (rec.get('date') or '', num, rec['source_path'])


def build(root):
    cat = os.path.join(root, 'catalog')
    records = [json.loads(line) for line in
               open(os.path.join(cat, 'episodes.jsonl'), encoding='utf-8')]
    authors = json.load(open(os.path.join(cat, 'authors.json'), encoding='utf-8'))['authors']
    author_of, author_name = {}, {}
    for a in authors:
        for eid in a['episodes']:
            author_of[eid] = a['slug']
            author_name[eid] = a['display_name']

    global parse_series_html_cache
    parse_series_html_cache = parse_series_html(root)
    disk_title_pages = title_pages_on_disk(root)

    u = cluster(records, author_of)
    groups = collections.defaultdict(list)
    for rec in records:
        groups[u.find(rec['episode_id'])].append(rec)

    overrides_path = os.path.join(cat, 'slug_overrides.yml')
    overrides = slugmod.load_overrides(overrides_path)
    prev_slugs = overrides.get('works') or {}
    wo_path = os.path.join(cat, 'work_overrides.yml')
    work_overrides = {}
    if os.path.exists(wo_path) and yaml is not None:
        with open(wo_path, encoding='utf-8') as fh:
            work_overrides = (yaml.safe_load(fh) or {}).get('works') or {}

    used_slugs, works, slug_entries = set(), [], {}
    ordered = sorted(groups.values(), key=lambda g: (-len(g), g[0]['episode_id']))
    for members in ordered:
        members.sort(key=episode_order_key)
        eids = [m['episode_id'] for m in members]
        aslug = author_of.get(eids[0])
        rules = set()
        for eid in eids:
            rules |= u.why.get(eid, set())
        # 作品名 — タイトルページの題名 > 巻号を落とした題名の一致 > 共通接頭辞
        cores = [trim_title(m.get('title')) for m in members]
        core_counts = collections.Counter(c for c in cores if c)
        if len(members) == 1:
            work_title = members[0].get('title')
        elif core_counts and core_counts.most_common(1)[0][1] >= max(2, len(members) // 2):
            work_title = core_counts.most_common(1)[0][0]
        else:
            pref = common_prefix([unicodedata.normalize('NFKC', m.get('title') or '')
                                  for m in members]).strip(' 　-–—「」『』()（）')
            work_title = pref if len(pref) >= 3 else (members[0].get('title') or '')
        title_pages = sorted({m['source_path'] for m in members
                              if RE_TITLE_PAGE.search(m['source_path'])}
                             | {norm_path(n['href']) for m in members
                                for n in (m.get('nav_links') or [])
                                if RE_SERIES_NAV.search(n.get('label') or '')
                                and norm_path(n['href'])})

        # 重複ファイル (md5 一致) の正本を 1 つ選ぶ
        digests = collections.defaultdict(list)
        for m in members:
            if m.get('source_exists'):
                dg = md5_of(os.path.join(root, m['source_path']))
                if dg:
                    digests[dg].append(m)
        alias_of, alias_paths = {}, []
        for dg, dup in digests.items():
            if len(dup) > 1:
                canon = min(dup, key=episode_order_key)
                for other in dup:
                    if other is not canon:
                        alias_of[other['episode_id']] = canon['episode_id']
                        alias_paths.append(other['source_path'])

        multi_dir = len({os.path.dirname(m['source_path']) for m in members}) > 1
        needs_review = bool(
            len(members) > 1 and (not (rules & STRONG) or multi_dir or len(members) >= 15))

        key = '%s|%s' % (aslug, work_title)
        ov = work_overrides.get(key) or {}
        if ov.get('work_title'):
            work_title = ov['work_title']

        old = prev_slugs.get(key)
        if isinstance(old, dict) and old.get('status') == 'confirmed' and old.get('slug'):
            slug, source, status = old['slug'], old.get('source'), 'confirmed'
        else:
            roman, source = slugmod.romanize(work_title)
            slug = '%s-%s' % (aslug, roman) if roman else aslug
            status = 'auto' if source == 'ascii' else 'candidate'
        slug = slugmod.unique_slug(slug, used_slugs, fallback_prefix='%s-work' % aslug)
        slug_entries[key] = {'slug': slug, 'status': status, 'source': source}

        works.append(collections.OrderedDict([
            ('work_slug', slug),
            ('title', work_title),
            ('author_slug', aslug),
            ('author_display', author_name.get(eids[0])),
            ('episode_count', len(members)),
            ('corpora', dict(collections.Counter(m['corpus'] for m in members))),
            ('first_date', min((m.get('date') or '' for m in members), default=None)),
            ('last_date', max((m.get('date') or '' for m in members), default=None)),
            ('title_pages', [p for p in title_pages if p]),
            ('title_page_on_disk', [p for p in title_pages if p in disk_title_pages]),
            ('evidence', sorted(rules)),
            ('needs_review', needs_review),
            ('multi_directory', multi_dir),
            ('alias_paths', sorted(set(alias_paths))),
            ('slug_source', source),
            ('slug_status', status),
            ('episodes', [collections.OrderedDict([
                ('episode_id', m['episode_id']),
                ('menu_order', i + 1),
                ('title', m.get('title')),
                ('source_path', m['source_path']),
                ('date', m.get('date')),
                ('alias_of', alias_of.get(m['episode_id'])),
            ]) for i, m in enumerate(members)]),
        ]))

    overrides = slugmod.merge_section(overrides, 'works', slug_entries)

    orphans = [r['episode_id'] for r in records
               if r['episode_id'] not in {e['episode_id'] for w in works for e in w['episodes']}]
    review = [w for w in works if w['needs_review']]
    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/work_builder.py'),
        ('episodes', len(records)),
        ('works', len(works)),
        ('works_single_episode', sum(1 for w in works if w['episode_count'] == 1)),
        ('works_multi_episode', sum(1 for w in works if w['episode_count'] > 1)),
        ('episodes_in_works', sum(w['episode_count'] for w in works)),
        ('orphans', orphans),
        ('needs_review', len(review)),
        ('needs_review_reasons', dict(collections.Counter(
            'weak-evidence' if not (set(w['evidence']) & STRONG) else
            ('multi-directory' if w['multi_directory'] else 'large-cluster') for w in review))),
        ('evidence_histogram', dict(collections.Counter(
            tuple(w['evidence']) and ','.join(w['evidence']) or 'single' for w in works))),
        ('series_html_rows', len(parse_series_html_cache)),
        ('series_html_rows_with_links',
         sum(1 for r in parse_series_html_cache if r['episode_paths'] or r['title_page'])),
        ('title_pages_on_disk', len(disk_title_pages)),
        ('works_with_title_page', sum(1 for w in works if w['title_pages'])),
        ('alias_paths_total', sum(len(w['alias_paths']) for w in works)),
        ('largest_works', [[w['work_slug'], w['episode_count']] for w in works[:10]]),
        ('slug_sources', dict(collections.Counter(w['slug_source'] for w in works))),
        ('slug_pending_review', sum(1 for w in works if w['slug_status'] == 'candidate')),
    ])
    return works, report, overrides, overrides_path, review


WORK_OVERRIDES_HEADER = """\
# Work クラスタの人手上書き (scripts/wp/work_builder.py が読む)。
#
# 鍵は "<作者slug>|<機械が付けた作品名>"。値に書けるもの:
#   work_title: 正しい作品名   … これを直すと slug の候補も作り直される
#   note:       メモ
#
# 下に並んでいるのは needs_review が立ったクラスタ (根拠が弱い / 複数ディレクトリに
# 散らばる / 15 話以上) の雛形です。**正しければ何もしなくて構いません**。
# 進行台帳 docs/wp-implementation-tasks.md の 👤 1.5b がこのファイルの確認タスクです。
"""


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    if yaml is None:
        print('PyYAML が要ります', file=sys.stderr)
        return 2
    root = os.path.abspath(args.root)
    works, report, overrides, overrides_path, review = build(root)

    print('works=%d (単発 %d / 連載 %d)  episodes=%d  orphan=%d' %
          (report['works'], report['works_single_episode'], report['works_multi_episode'],
           report['episodes_in_works'], len(report['orphans'])))
    print('needs_review=%d %s' % (report['needs_review'], report['needs_review_reasons']))
    print('title page を持つ work=%d / series.html の有効行=%d / alias=%d' %
          (report['works_with_title_page'], report['series_html_rows_with_links'],
           report['alias_paths_total']))
    print('slug: %s 確認待ち=%d' % (report['slug_sources'], report['slug_pending_review']))
    ok = not report['orphans'] and report['episodes_in_works'] == report['episodes']
    print('  [%s] 全 episode がいずれかの work に属す (orphan 0)' % ('OK ' if ok else 'NG '))

    if not args.check:
        cat = os.path.join(root, 'catalog')
        os.makedirs(os.path.join(cat, 'reports'), exist_ok=True)
        with open(os.path.join(cat, 'works.jsonl'), 'w', encoding='utf-8') as fh:
            for w in works:
                fh.write(json.dumps(w, ensure_ascii=False) + '\n')
        with open(os.path.join(cat, 'reports', 'work_builder.json'), 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        existing = {}
        wo_path = os.path.join(cat, 'work_overrides.yml')
        if os.path.exists(wo_path):
            with open(wo_path, encoding='utf-8') as fh:
                existing = (yaml.safe_load(fh) or {}).get('works') or {}
        stub = dict(existing)
        for w in review:
            key = '%s|%s' % (w['author_slug'], w['title'])
            stub.setdefault(key, {'work_title': w['title'], 'episodes': w['episode_count'],
                                  'evidence': w['evidence'], 'confirmed': False})
        with open(wo_path, 'w', encoding='utf-8') as fh:
            fh.write(WORK_OVERRIDES_HEADER)
            yaml.safe_dump({'works': stub}, fh, allow_unicode=True, sort_keys=True,
                           default_flow_style=False)
        slugmod.save_overrides(overrides_path, overrides, notes=[
            'terms_* セクション = 分類語彙の slug (scripts/wp/terms_build.py)',
            'authors セクション = 作者 slug (scripts/wp/authors_build.py)',
            'works セクション = 作品 slug (scripts/wp/work_builder.py)',
        ])
        print('wrote catalog/works.jsonl, catalog/work_overrides.yml, '
              'catalog/reports/work_builder.json, catalog/slug_overrides.yml')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
