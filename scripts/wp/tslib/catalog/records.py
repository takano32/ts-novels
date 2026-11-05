# -*- coding: utf-8 -*-
"""records.py — パース結果 → episodes.jsonl のレコード (両目録) の組み立て。

  - 冪等キー = episode_id。source_path の `/` を `__` に置換し、anchor があれば `@<anchor>`、
    同一パスを指すエントリが複数ある場合は `+<掲載日 YYYYMMDD>` (keys.episode_id_of)。
  - honkan と同じ source_path を指す legacy エントリは重複として落とす (パス単位 dedup)。
  - `_` で始まる欄は自己検査用で、出力前に strip_private で取り除く。
"""
import collections
import os

from tslib.fileio import read_text
from tslib.paths import YAYS_PREFIX, kansou_annex_url
from tslib.catalog.honkan import HONKAN_PAGES, parse_page
from tslib.catalog.legacy import LEGACY_FLAT_PAGES, LEGACY_TABLE_PAGES, parse_legacy_pages
from tslib.catalog.keys import split_href, episode_id_of, entry_type_of, orig_url_of, annex_url_of
from tslib.catalog.provenance import provenance_for


def strip_private(rec):
    return collections.OrderedDict((k, v) for k, v in rec.items() if not k.startswith('_'))


def shared_groups(entries):
    """同じ (source_path, anchor) を指すエントリの数。entries には `_key` が付いていること。"""
    return collections.Counter(e['_key'][:2] for e in entries)


def episode_id_for(entry, groups):
    """グループに相方がいれば掲載日で区別した episode_id。"""
    source_path, anchor, _kind = entry['_key']
    shared = groups[(source_path, anchor)] > 1
    return episode_id_of(source_path, anchor, entry['date'].replace('-', '') if shared else None)


def location_fields(root, annex_base, source_path, anchor, kind, exists, prov_map):
    """orig_url / annex_url / annex_yays_url / provenance — 両目録で同じ規則。順序もこの通りに出力される。"""
    yays_path = YAYS_PREFIX + source_path
    out = collections.OrderedDict()
    out['orig_url'] = orig_url_of(source_path, anchor, kind)
    out['annex_url'] = annex_url_of(annex_base, source_path, anchor) if exists else None
    out['annex_yays_url'] = (annex_url_of(annex_base, yays_path)
                             if os.path.exists(os.path.join(root, yays_path)) else None)
    out['provenance'] = provenance_for(source_path, prov_map) if exists else None
    return out


def source_exists(root, source_path, kind):
    return kind == 'local' and os.path.exists(os.path.join(root, source_path))


# --------------------------------------------------------------------------- 正規目録

def honkan_record(e, eid, groups, root, annex_base, prov_map):
    source_path, anchor, kind = e['_key']
    exists = source_exists(root, source_path, kind)
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
    rec['kansou_annex_url'] = kansou_annex_url(annex_base, e['kansou_slug'])
    rec['arasuji'] = e['arasuji']
    rec['comment'] = e['comment']
    rec['osusume'] = e['osusume']
    rec['suisen'] = e['suisen']
    rec['takedown_notice'] = e.get('takedown_notice')  # 作者の公開停止申し出 (取り下げの根拠)
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
    rec.update(location_fields(root, annex_base, source_path, anchor, kind, exists, prov_map))
    rec['_dropped_mailto'] = e['dropped_mailto']       # 自己検査用。出力前に取り除く
    rec['_suisen_full_empty'] = e['_suisen_full_empty']
    return rec


def honkan_records(parsed, root, annex_base, prov_map):
    """パース済みエントリ (`_key` 付き) → corpus=honkan のレコード。"""
    groups = shared_groups(parsed)
    return [honkan_record(e, episode_id_for(e, groups), groups, root, annex_base, prov_map)
            for e in parsed]


def build(root, annex_base, prov_map=None):
    """正規目録 lib1〜73 を読んでレコードにする。戻り値 (records, failures)。"""
    prov_map = {} if prov_map is None else prov_map
    parsed, failures = [], []
    for page in HONKAN_PAGES:
        entries, fails = parse_page(page, read_text(os.path.join(root, page)))
        failures.extend(fails)
        for e in entries:
            e['_key'] = split_href(e.pop('href'))
            parsed.append(e)
    return honkan_records(parsed, root, annex_base, prov_map), failures


# --------------------------------------------------------------------------- 旧目録

def legacy_record(e, eid, groups, root, annex_base, prov_map):
    source_path, anchor, kind = e['_key']
    exists = source_exists(root, source_path, kind)
    rec = collections.OrderedDict()
    rec['episode_id'] = eid
    rec['corpus'] = 'legacy'
    rec['source_path'] = source_path
    rec['source_anchor'] = anchor
    rec['source_kind'] = kind
    rec['source_exists'] = exists
    rec['source_shared_by'] = groups[(source_path, anchor)]
    rec['entry_type'] = entry_type_of(source_path, kind)
    rec['title'] = e['title']
    rec['title_raw'] = e['title_raw']
    rec['author'] = e['author']
    rec['homepage'] = e['homepage']
    rec['illustrator'] = e['illustrator']
    rec['illustrator_url'] = e['illustrator_url']
    rec['illustrator_raw'] = e['illustrator_raw']
    rec['legacy_credit_raw'] = e['legacy_credit_raw']
    rec['date'] = e['date']
    rec['date_raw'] = e['date_raw']
    rec['date_precision'] = e['date_precision']
    rec['weekday'] = e['weekday']
    rec['size_kb'] = e['size_kb']
    rec['files_n'] = e['files_n']
    rec['kansou_slug'] = None
    rec['kansou_annex_url'] = None
    rec['noteky_url'] = (annex_url_of(annex_base, e['noteky_url']['path'])
                         if e['noteky_url'] else None)
    rec['noteky_id'] = e['noteky_url']['id'] if e['noteky_url'] else None
    rec['arasuji'] = e['arasuji']
    rec['comment'] = e['comment']
    rec['osusume'] = e['osusume']
    rec['suisen'] = e['suisen']
    rec['notice'] = e['notice']
    rec['nav_links'] = e['nav_links']
    rec['inline_links'] = e['inline_links']
    rec['genre'] = e['genre']
    rec['genre_raw'] = e['genre_raw']
    rec['type'] = e['type']
    rec['type_raw'] = e['type_raw']
    rec['keywords'] = e['keywords']
    rec['keywords_raw'] = e['keywords_raw']
    rec['zokusei'] = e['zokusei']
    rec['zokusei_raw'] = e.get('zokusei_raw', [])
    rec['legacy_format'] = e['legacy_format']
    rec['entry_role'] = e['entry_role']
    rec['commented_out'] = e['commented_out']
    rec['catalog_ref'] = e['catalog_ref']
    rec.update(location_fields(root, annex_base, source_path, anchor, kind, exists, prov_map))
    rec['_dropped_mailto'] = e['dropped_mailto']
    rec['_suisen_full_empty'] = e['_suisen_full_empty']
    return rec


def dedup_against_honkan(entries, honkan_records):
    """honkan 側が同じ source_path を既に持つ旧目録エントリを落とす。戻り値 (kept, dropped)。
    各エントリに `_key` を付ける。"""
    honkan_paths = {r['source_path'] for r in honkan_records}
    kept, dropped = [], []
    for e in entries:
        source_path, anchor, kind = split_href(e['href'])
        e['_key'] = (source_path, anchor, kind)
        (dropped if source_path in honkan_paths else kept).append(e)
    return kept, dropped


def legacy_records(kept, honkan_records, root, annex_base, prov_map):
    """重複除外済みの旧目録エントリ → corpus=legacy のレコード。
    episode_id が honkan と (同一パス・同一日付の再掲で) 衝突すれば `~2` `~3` … で一意化する。"""
    groups = shared_groups(kept)
    used = {r['episode_id'] for r in honkan_records}
    records = []
    for e in kept:
        eid = episode_id_for(e, groups)
        if eid in used:                       # 同一パス・同一日付の再掲は連番で一意化する
            base, n = eid, 2
            while eid in used:
                eid = '%s~%d' % (base, n)
                n += 1
        used.add(eid)
        records.append(legacy_record(e, eid, groups, root, annex_base, prov_map))
    return records


def legacy_stats(entries, failures, dropped_dup, records):
    return collections.OrderedDict([
        ('blocks_parsed', len(entries)),
        ('parse_failures', len(failures)),
        ('parse_failure_detail', failures[:20]),
        ('dropped_as_honkan_duplicate', len(dropped_dup)),
        ('added', len(records)),
        ('added_by_format', dict(collections.Counter(r['legacy_format'] for r in records))),
        ('added_commented_out', sum(1 for r in records if r['commented_out'])),
        ('added_source_present', sum(1 for r in records if r['source_exists'])),
        ('added_entry_roles', dict(collections.Counter(r['entry_role'] for r in records))),
        ('added_authors_unresolved',
         sum(1 for r in records if not r['author'] and r['entry_role'] == 'work')),
        ('added_entry_types', dict(collections.Counter(r['entry_type'] for r in records))),
        ('added_year_histogram', dict(sorted(collections.Counter(
            r['date'][:4] for r in records).items()))),
        ('date_precision', dict(collections.Counter(r['date_precision'] for r in records))),
        ('noteky_links', sum(1 for r in records if r['noteky_url'])),
        ('zokusei_tokens_distinct', len({t for r in records for t in r['zokusei']})),
    ])


def read_legacy_pages(root):
    """旧目録 lib01〜09 のファイルを読む。戻り値 (flat_pages, table_pages) = [(page, src)]。"""
    flat = [(page, read_text(os.path.join(root, page))) for page in LEGACY_FLAT_PAGES]
    table = [(page, read_text(os.path.join(root, page))) for page in LEGACY_TABLE_PAGES]
    return flat, table


def build_legacy(root, annex_base, honkan_records, prov_map):
    """旧目録 lib01〜09 を正規目録 (corpus=honkan) との差分だけ corpus=legacy のレコードにする (T7.2)。

    dedup は**パス単位** — honkan 側が同じ `source_path` を(アンカー付きの集約リンクや
    改訂再掲として)既に持っているなら、旧目録側は重複とみなして落とす。
    旧目録固有の欄 (noteky_url / zokusei / commented_out 等) は legacy レコードにだけ付く。
    戻り値 (records, failures, stats)。
    """
    flat, table = read_legacy_pages(root)
    entries, failures = parse_legacy_pages(flat, table)
    kept, dropped_dup = dedup_against_honkan(entries, honkan_records)
    records = legacy_records(kept, honkan_records, root, annex_base, prov_map)
    return records, failures, legacy_stats(entries, failures, dropped_dup, records)
