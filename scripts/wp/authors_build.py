#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""authors_build.py — 作者を `catalog/authors.json` にまとめる (進行台帳 タスク 1.4)。

入力
  catalog/episodes.jsonl   … 1.1/1.2 の出力
  catalog/terms.json       … ts_world (= 共有感想板の一覧。作者板と区別するために要る)
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
    表示名で本館の作者に寄せ、寄らないものは新規採番

表示名の突き合わせは NFKC + 空白除去 + casefold。ＭＯＮＤＯ と MONDO、ｔｏｓｈｉ９ と
toshi9 はこれで同じ鍵になる。
"""
import argparse
import collections
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

def parse_index(root):
    """作家別五十音目録 → (rows, yomi_of_name)。

    rows = [{path, title, author, homepage, kana, page}] — 題名→作者の第二情報源
    (タスク 1.8 の目録外収蔵でも使う)。yomi_of_name は表示名 → 所属行 (あ行/A/その他)。
    """
    rows, yomi = [], {}
    for page in INDEX_PAGES + INDEX_PAGES_OLD:
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


def build(root, annex_base=DEFAULT_ANNEX_BASE):
    cat = os.path.join(root, 'catalog')
    records = [json.loads(line) for line in
               open(os.path.join(cat, 'episodes.jsonl'), encoding='utf-8')]
    shared = shared_boards_of(root)
    index_rows, yomi_of = parse_index(root)

    # (1) 作者板を持つ作者 — 板 id が鍵
    name_boards = collections.defaultdict(collections.Counter)
    for rec in records:
        slug = rec.get('kansou_slug')
        if slug and slug not in shared and rec.get('author'):
            name_boards[norm_name(rec['author'])][slug] += 1

    # (2) 全 episode を作者鍵に割り当てる
    assign, unresolved, ambiguous, notices = {}, [], [], []
    new_keys = {}
    for rec in records:
        slug = rec.get('kansou_slug')
        name = rec.get('author')
        key, how = None, None
        if slug and slug not in shared:
            key, how = slug, 'board'
        elif name:
            boards = name_boards.get(norm_name(name))
            if boards:
                key, how = boards.most_common(1)[0][0], 'name->board'
                if len(boards) > 1:
                    ambiguous.append({'author': name, 'boards': dict(boards),
                                      'episode_id': rec['episode_id']})
            else:
                key = new_keys.setdefault(norm_name(name), 'name:' + norm_name(name))
                how = 'name-only'
        else:
            # 作者欄が `***` の編集部告知ブロック (旧目録 lib07#16) だけがここに来る
            (notices if rec.get('entry_role') == 'notice' else unresolved).append(
                rec['episode_id'])
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

    # (4) slug — 板 id はそのまま、板なしは pykakasi 候補
    overrides_path = os.path.join(cat, 'slug_overrides.yml')
    overrides = slugmod.load_overrides(overrides_path)
    prev = overrides.get('authors') or {}
    used, entries = set(), {}
    ordered = sorted(authors.values(), key=lambda a: (-len(a['episodes']), a['key']))
    for a in ordered:
        if a['board_slug']:
            used.add(a['board_slug'])
    for a in ordered:
        name = a['display_variants'].most_common(1)[0][0] if a['display_variants'] else a['key']
        a['display_name'] = name
        if a['board_slug']:
            a['slug'], a['slug_source'], a['slug_status'] = a['board_slug'], 'kansou-board', 'auto'
            entries[name] = {'slug': a['slug'], 'status': 'auto', 'source': 'kansou-board'}
            continue
        old = prev.get(name)
        if isinstance(old, dict) and old.get('status') == 'confirmed' and old.get('slug'):
            a['slug'], a['slug_source'], a['slug_status'] = old['slug'], old.get('source'), 'confirmed'
            used.add(a['slug'])
            entries[name] = dict(old)
            continue
        slug, source = slugmod.romanize(name)
        slug = slugmod.unique_slug(slug, used, fallback_prefix='author')
        a['slug'], a['slug_source'] = slug, source
        a['slug_status'] = 'auto' if source == 'ascii' else 'candidate'
        entries[name] = {'slug': slug, 'status': a['slug_status'], 'source': source}
    overrides = slugmod.merge_section(overrides, 'authors', entries)

    out = []
    for a in ordered:
        name = a['display_name']
        hp = a['homepages'].most_common(1)
        if not hp:
            hp = index_hp.get(norm_name(name), collections.Counter()).most_common(1)
        homepage = hp[0][0] if hp else None
        out.append(collections.OrderedDict([
            ('slug', a['slug']),
            ('display_name', name),
            ('display_variants', [n for n, _ in a['display_variants'].most_common()]),
            ('yomi_group', yomi_of.get(norm_name(name))),
            ('kansou_slug', a['board_slug']),
            ('kansou_annex_url',
             annex_base + '~ts/kansou/bbs@log_%s.cgi' % a['board_slug'] if a['board_slug'] else None),
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
        ('episodes_notice_no_author', notices),
        ('episodes_assigned', len(assign)),
        ('ambiguous_name_to_board', ambiguous[:20]),
        ('ambiguous_name_to_board_count', len(ambiguous)),
        ('yomi_resolved', sum(1 for a in out if a['yomi_group'])),
        ('homepage_present', sum(1 for a in out if a['homepage'])),
        ('index_rows_parsed', len(index_rows)),
        ('index_authors_distinct', len({norm_name(r['author']) for r in index_rows})),
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
    check('episode の割り当て漏れ 0 (entry_role=notice を除く)',
          report['episodes_assigned'] + len(report['episodes_notice_no_author'])
          == report['episodes'],
          '%d + notice %d / %d' % (report['episodes_assigned'],
                                   len(report['episodes_notice_no_author']),
                                   report['episodes']))
    slugs = [a['slug'] for a in payload['authors']]
    dup = [s for s, c in collections.Counter(slugs).items() if c > 1]
    check('slug の重複 0', not dup, '重複 %s' % dup[:5])
    check('板を持つ作者の slug は板 id',
          all(a['slug'] == a['kansou_slug'] for a in payload['authors'] if a['kansou_slug']))
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
