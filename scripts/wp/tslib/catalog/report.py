# -*- coding: utf-8 -*-
"""report.py — catalog/reports/catalog_build.json の中身と、標準出力の要約。"""
import collections

from tslib.checks import report_lines


def report_of(all_records, failures, checks, legacy_stats=None):
    records = [r for r in all_records if r['corpus'] == 'honkan']
    legacy = [r for r in all_records if r['corpus'] == 'legacy']
    prov = [r['provenance'] for r in records]
    covered = [p for p in prov if p]
    routes = collections.Counter(p['route'] for p in covered)
    mixed = sum(1 for p in covered if p.get('route_mixed'))
    years = collections.Counter(r['date'][:4] for r in records)
    legacy_prov = [r['provenance'] for r in legacy if r['provenance']]
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/catalog_build.py'),
        ('corpus', 'honkan (lib1-73)'),
        ('entries_total', len(all_records)),
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
        ('legacy', collections.OrderedDict(list((legacy_stats or {}).items()) + [
            ('provenance_covered', len(legacy_prov)),
            ('provenance_coverage_pct',
             round(100.0 * len(legacy_prov) / len(legacy), 2) if legacy else 0),
            ('annex_url_present', sum(1 for r in legacy if r['annex_url'])),
            ('annex_yays_url_present', sum(1 for r in legacy if r['annex_yays_url'])),
            ('mailto_dropped_distinct', len({a for r in legacy for a in r['_dropped_mailto']})),
            ('arasuji_present', sum(1 for r in legacy if r['arasuji'])),
            ('comment_present', sum(1 for r in legacy if r['comment'])),
            ('suisen_present', sum(1 for r in legacy if r['suisen'])),
            ('illustrator_present', sum(1 for r in legacy if r['illustrator'])),
            ('homepage_present', sum(1 for r in legacy if r['homepage'])),
            ('authors_distinct_display', len({r['author'] for r in legacy if r['author']})),
        ])),
        ('selftest', checks.as_report()),
        ('selftest_ok', checks.ok),
    ])


def summary_lines(rep):
    """標準出力に出す要約の行。"""
    out = []
    out.append('entries total=%d (honkan=%d legacy=%d)' %
               (rep['entries_total'], rep['entries'], rep['legacy'].get('added', 0)))
    out.append('entries=%d parse_failures=%d unique_ids=%d' %
               (rep['entries'], rep['parse_failures'], rep['unique_episode_ids']))
    out.append('source files: present=%d missing=%d  types=%s' %
               (rep['source_files_present'], rep['source_files_missing'], rep['entry_types']))
    out.append('annex_url=%d annex_yays_url=%d' % (rep['annex_url_present'], rep['annex_yays_url_present']))
    out.append('mailto dropped: %d distinct / %d entries' %
               (rep['mailto_dropped_distinct'], rep['mailto_dropped_entries']))
    out.append('provenance coverage=%.2f%% (%d/%d) routes=%s mixed=%d' %
               (rep['provenance_coverage_pct'], rep['provenance_covered'], rep['entries'],
                rep['provenance_routes'], rep['provenance_route_mixed']))
    leg = rep['legacy']
    if leg.get('blocks_parsed'):
        out.append('legacy: blocks=%d failures=%d dup_dropped=%d added=%d (flat/table=%s) '
                   'commented_out=%d present=%d prov=%.2f%%' %
                   (leg['blocks_parsed'], leg['parse_failures'], leg['dropped_as_honkan_duplicate'],
                    leg['added'], leg['added_by_format'], leg['added_commented_out'],
                    leg['added_source_present'], leg['provenance_coverage_pct']))
    out.append('--- selftest')
    out.extend(report_lines(rep['selftest']))
    return out


def print_summary(rep):
    for line in summary_lines(rep):
        print(line)
