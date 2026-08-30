#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""terms_build.py — 分類語彙を `catalog/terms.json` に正規化する (進行台帳 タスク 1.3)。

入力
  catalog/episodes.jsonl      … 1.1/1.2 の出力 (genre_raw / type_raw / keywords_raw / zokusei_raw)
  genre.html / type_of_change.html / keyword.html … 当時の語彙定義ページ (term description の原資料)
  share_world.html            … シェアワールドの一覧 (ts_world の原資料)
  catalog/*_map.yml           … 正規化マップ。無ければ既定値で書き出す (以後は人手で編集)

出力
  catalog/terms.json                  … 6 本のタクソノミーの語彙 (raw_variants 付き)
  catalog/genre_map.yml / type_map.yml / keyword_map.yml / world_map.yml
  catalog/slug_overrides.yml          … slug 候補 (👤 1.5b の確認対象。terms_* セクション)
  catalog/reports/terms_build.json    … 件数・被覆率・表記揺れ候補

正規化の段取り (原表記は必ず raw_variants に残す):
  NFKC → 「」『』 外し → 末尾の ？ / （？） 落とし → 同義語マップ適用 → 集計

「約 30 語」「約 25 語」は**中核語彙**の話であり、長い尾を捨てるという意味ではない。
出現数がしきい値以上の語に `core: true` を立て、それ以外も term としては残す
(史料性を落とさないため。テーマのファセット UI は core だけを出す)。
"""
import argparse
import collections
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

CORE_MIN_COUNT = {'ts_genre': 10, 'ts_type': 10, 'ts_keyword': 10}

RE_TAG = re.compile(r'<[^>]+>')
RE_SECTION = re.compile(r'<H3>\s*【(.+?)】\s*</H3>', re.I)
RE_DEFPAIR = re.compile(
    r'<TD[^>]*>\s*<B>(.*?)</B>\s*</TD>\s*<TD[^>]*>(.*?)</TD>', re.S | re.I)

# --- 既定の正規化マップ (初回のみ YAML に書き出す。以後は YAML が正)
# 設計書 §1.2 が名指しした類義統合 + 当時の語彙定義ページ (genre.html) の見出し語に寄せる。
DEFAULT_GENRE_MAP = {
    'ミステリ': 'ミステリー',          # 設計 §1.2 の指定。語彙ページの見出しは「ミステリ」
    'SS': 'ショートショート',
    '学園物': '学園',
    '2次創作': '二次創作',             # NFKC 後の ２次創作
    'コメディー': 'コメディ',
    'ギャグ': 'コメディ',
    '時代': '時代・歴史',
    '歴史': '時代・歴史',
    '理解ある姉妹/女友達': '理解ある姉妹／女友達',
    'あくしょんこめでぃ': 'アクションコメディ',
}
DEFAULT_TYPE_MAP = {
    '薬': '薬品',
    '魔術': '魔法',
    '医療': '医術',
}
DEFAULT_KEYWORD_MAP = {
    'おれっ娘': 'オレっ娘',
    '俺っ娘': 'オレっ娘',
    'ボクっ子': 'ボクっ娘',
    '僕っ娘': 'ボクっ娘',
    'お風呂': '入浴',
    '理解ある姉妹/女友達': '理解ある姉妹／女友達',
    'オレッ娘': 'オレっ娘',
    'ボクッ娘': 'ボクっ娘',
    'ハンター・シリーズ': 'ハンターシリーズ',
    'ダーティエンジェル': 'ダーティーエンジェル',
}

# ts_world — シェアワールド。判定規則は「当時の共有感想板」と「作品ディレクトリ」の 2 系統。
# share_world.html / share_world.htm と ~ts/kansou の板一覧が原資料。
DEFAULT_WORLD_MAP = {
    'kayo_chan': {'name': '「華代ちゃん」シリーズ', 'boards': ['kayo_chan'],
                  'path_prefixes': ['novel/kayo_chan/'], 'origin': '真城 悠'},
    'hunter': {'name': 'ハンターシリーズ', 'boards': ['kayo_chan_hunternumber'],
               'path_prefixes': [],
               'title_patterns': ['^ハンターシリーズ'], 'origin': '真城 悠',
               'note': '華代ちゃんシリーズからのスピンアウト。題名で判定する'},
    'himekami': {'name': '妖魔夜行 姫神奇譚シリーズ', 'boards': ['himekami'],
                 'path_prefixes': [], 'origin': '亜希みちる'},
    'foster': {'name': '次元管理人フォスターシリーズ', 'boards': ['foster'],
               'path_prefixes': ['novel/corrector/'], 'origin': '真城 悠',
               'note': 'ディレクトリ名は corrector だが中身はフォスター。'
                       '設計 v1.0 の world 一覧が corrector を別世界として数えていたのは誤り'},
    'rental': {'name': '「レンタル・ボディ」シリーズ', 'boards': ['rental_body'],
               'path_prefixes': ['novel/rental/'], 'origin': '２ｂｉｔ'},
    'mirror_ring': {'name': 'ミラーリングシリーズ', 'boards': [],
                    'path_prefixes': ['novel/mirror_ring/'], 'origin': 'ことぶきひかる'},
    'setsubou': {'name': '「切望」シリーズ', 'boards': ['setubou'],
                 'path_prefixes': ['novel/setsubou/'], 'origin': 'KCA'},
    'dirty': {'name': 'ダーティーエンジェルシリーズ', 'boards': ['d_angel'],
              'path_prefixes': ['novel/dirty/'], 'origin': '水谷秋夫'},
    'sugar': {'name': 'シェアワールド「SugarSweets」', 'boards': ['sugar_sweets'],
              'path_prefixes': ['novel/sugar/'], 'origin': '胡乱太'},
    'fms': {'name': 'FMS シリーズ', 'boards': [], 'path_prefixes': [],
            'title_patterns': ['^FMS', '^ＦＭＳ'], 'origin': '夜夢'},
    'relay_novel': {'name': 'リレー小説', 'boards': ['relay_novel'], 'path_prefixes': []},
    'delayed': {'name': '仮面ライターディレイド', 'boards': ['delayed'], 'path_prefixes': []},
    '2daime': {'name': '二代目シリーズ', 'boards': ['2daime'], 'path_prefixes': []},
    'utanotsuki': {'name': '詩の月シリーズ', 'boards': ['utanotsuki'], 'path_prefixes': []},
}

# ts_corpus — 収蔵区分。dojo / anthology は Phase 6 / 4.6 で使う枠を先に定義しておく。
CORPUS_TERMS = [
    ('honkan', '本館 (正規目録 lib1–73)', '1997–2014 の正規目録に載った収蔵作品'),
    ('legacy', '旧目録 (lib01–09)', '1997.11–2000.2 の旧形式目録にだけ載る初期史料'),
    ('dojo', 'ストーリー道場 (2ndbbs)', '閉鎖後の姉妹サイトに投稿された作品 (Phase 6)'),
    ('anthology', 'アンソロジー・特集', 'special/03summer など特集企画の収録作 (Phase 4.6)'),
]

TAXONOMY_META = {
    'ts_genre': {'rewrite': 'genre', 'field': 'genre_raw', 'vocab_page': 'genre.html'},
    'ts_type': {'rewrite': 'type', 'field': 'type_raw', 'vocab_page': 'type_of_change.html'},
    'ts_keyword': {'rewrite': 'keyword', 'field': 'keywords_raw', 'vocab_page': 'keyword.html'},
}


# --------------------------------------------------------------------------- 正規化

def normalize_token(token):
    """NFKC → 「」外し → 末尾の ？/（？） 落とし。同義語マップは呼び出し側で適用する。"""
    t = unicodedata.normalize('NFKC', token).strip()
    t = t.strip('「」『』"\'')
    t = re.sub(r'[（(]\s*[?？]\s*[)）]\s*$', '', t).strip()
    t = re.sub(r'[?？]+$', '', t).strip()
    return t


def variant_key(token):
    """表記揺れ候補の検出キー — カタカナ→ひらがな・長音/中黒/空白を落とした形。"""
    t = unicodedata.normalize('NFKC', token).lower()
    t = ''.join(chr(ord(c) - 0x60) if 'ァ' <= c <= 'ヶ' else c for c in t)
    return re.sub(r'[ー\-・\s　/／]', '', t)


# --------------------------------------------------------------------------- 語彙定義ページ

def parse_vocab_page(path):
    """genre.html 等 → {term: {'description': str, 'section': str}}。"""
    if not os.path.exists(path):
        return {}
    with open(path, encoding='utf-8') as fh:
        src = fh.read()
    sections = [(m.start(), m.group(1)) for m in RE_SECTION.finditer(src)]
    out = {}
    for m in RE_DEFPAIR.finditer(src):
        term = RE_TAG.sub('', m.group(1)).strip()
        desc = re.sub(r'\s+', ' ', RE_TAG.sub('', m.group(2))).strip()
        if not term or len(term) > 24:
            continue
        section = None
        for pos, name in sections:
            if pos < m.start():
                section = name
        out.setdefault(normalize_token(term), {'description': desc or None, 'section': section})
    return out


def parse_share_world(path):
    """share_world.html → [(名前, 原作者, 推薦文)]。ts_world の説明文の原資料。"""
    if not os.path.exists(path):
        return []
    with open(path, encoding='utf-8') as fh:
        src = fh.read()
    text = re.sub(r'\s+', ' ', RE_TAG.sub('\n', src))
    out = []
    for block in re.finditer(
            r'([^\n]{2,40}(?:シリーズ|設定|SugarSweets)[^\n]{0,20})\s*\n[^\n]*原作[：:]\s*\n?([^\n]{1,30})',
            text):
        out.append((block.group(1).strip(), block.group(2).strip()))
    return out


# --------------------------------------------------------------------------- 集計

def load_map(path, default, comment):
    """正規化マップを読む。無ければ既定値で作る (以後は YAML 側が正)。"""
    if os.path.exists(path):
        with open(path, encoding='utf-8') as fh:
            data = yaml.safe_load(fh) or {}
        return data.get('synonyms') or {}, data
    data = {'synonyms': dict(default)}
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(comment)
        yaml.safe_dump(data, fh, allow_unicode=True, sort_keys=True, default_flow_style=False)
    return dict(default), data


MAP_COMMENT = """\
# 分類語彙の正規化マップ (scripts/wp/terms_build.py が読む)。
#
#   synonyms:  原表記(NFKC・「」外し・？除去のあと) -> 統合先の見出し語
#
# NFKC 正規化・「」外し・末尾の ？/（？） 落としはスクリプト側で常に行うので、
# ここに書くのは**意味の統合**だけ (ミステリ -> ミステリー のような類義・異表記)。
# 原表記は terms.json の raw_variants に必ず残るので、統合しても史料性は落ちない。
# 表記揺れの候補は catalog/reports/terms_build.json の variant_candidates を見て
# 人が判断して追記してください。
"""


def collect(records, field, synonyms):
    """(canonical -> {'count', 'raw_variants', 'episodes'}) を作る。"""
    agg = collections.OrderedDict()
    for rec in records:
        for raw in rec.get(field) or []:
            norm = normalize_token(raw)
            if not norm:
                continue
            canon = synonyms.get(norm, norm)
            if not canon:
                continue
            slot = agg.setdefault(canon, {'count': 0, 'raw_variants': collections.Counter(),
                                          'episodes': []})
            slot['count'] += 1
            slot['raw_variants'][raw] += 1
            slot['episodes'].append(rec['episode_id'])
    return agg


def variant_candidates(agg):
    """まだ統合されていない表記揺れの候補 (人手レビュー用)。"""
    groups = collections.defaultdict(list)
    for canon, slot in agg.items():
        groups[variant_key(canon)].append((canon, slot['count']))
    out = []
    for key, members in groups.items():
        if len(members) > 1:
            out.append(sorted(members, key=lambda x: -x[1]))
    return sorted(out, key=lambda g: -sum(c for _, c in g))


# --------------------------------------------------------------------------- ts_world

def build_worlds(records, world_map):
    out = collections.OrderedDict()
    for slug, spec in world_map.items():
        boards = set(spec.get('boards') or [])
        prefixes = tuple(spec.get('path_prefixes') or [])
        patterns = [re.compile(p) for p in (spec.get('title_patterns') or [])]
        members, reasons = [], collections.Counter()
        for rec in records:
            hit = None
            if rec.get('kansou_slug') in boards:
                hit = 'board'
            elif prefixes and rec['source_path'].startswith(prefixes):
                hit = 'path'
            elif patterns and any(p.search(rec.get('title') or '') for p in patterns):
                hit = 'title'
            if hit:
                members.append(rec['episode_id'])
                reasons[hit] += 1
        out[slug] = {'name': spec.get('name') or slug,
                     'origin_author': spec.get('origin'),
                     'note': spec.get('note'),
                     'rule': {'boards': sorted(boards), 'path_prefixes': list(prefixes),
                              'title_patterns': spec.get('title_patterns') or []},
                     'count': len(members), 'match_reasons': dict(reasons),
                     'episodes': members}
    return out


# --------------------------------------------------------------------------- slug

def assign_slugs(taxonomy, agg_keys, overrides, section, board_slugs=None):
    """語 → slug。overrides の confirmed を最優先、次に感想板 id、最後に pykakasi。"""
    prev = overrides.get(section) or {}
    used, entries, result = set(), {}, {}
    # confirmed を先に押さえて衝突を避ける
    for name in agg_keys:
        rec = prev.get(name)
        if isinstance(rec, dict) and rec.get('status') == 'confirmed' and rec.get('slug'):
            used.add(rec['slug'])
    for name in agg_keys:
        rec = prev.get(name)
        if isinstance(rec, dict) and rec.get('status') == 'confirmed' and rec.get('slug'):
            result[name] = rec['slug']
            entries[name] = dict(rec)
            continue
        if board_slugs and name in board_slugs:
            slug, source = board_slugs[name], 'kansou-board'
        else:
            slug, source = slugmod.romanize(name)
        slug = slugmod.unique_slug(slug, used, fallback_prefix=taxonomy.replace('ts_', ''))
        result[name] = slug
        entries[name] = {'slug': slug, 'status': 'auto' if source == 'ascii' else 'candidate',
                         'source': source}
    return result, entries


# --------------------------------------------------------------------------- main

def build(root, core_min=None):
    core_min = core_min or CORE_MIN_COUNT
    records = [json.loads(line) for line in
               open(os.path.join(root, 'catalog', 'episodes.jsonl'), encoding='utf-8')]
    cat = os.path.join(root, 'catalog')
    overrides_path = os.path.join(cat, 'slug_overrides.yml')
    overrides = slugmod.load_overrides(overrides_path)

    genre_syn, _ = load_map(os.path.join(cat, 'genre_map.yml'), DEFAULT_GENRE_MAP, MAP_COMMENT)
    type_syn, _ = load_map(os.path.join(cat, 'type_map.yml'), DEFAULT_TYPE_MAP, MAP_COMMENT)
    kw_syn, _ = load_map(os.path.join(cat, 'keyword_map.yml'), DEFAULT_KEYWORD_MAP, MAP_COMMENT)
    world_path = os.path.join(cat, 'world_map.yml')
    if os.path.exists(world_path):
        with open(world_path, encoding='utf-8') as fh:
            world_map = (yaml.safe_load(fh) or {}).get('worlds') or DEFAULT_WORLD_MAP
    else:
        world_map = DEFAULT_WORLD_MAP
        with open(world_path, 'w', encoding='utf-8') as fh:
            fh.write('# シェアワールド (ts_world) の判定規則。\n'
                     '# boards = 共有感想板の slug / path_prefixes = 作品ディレクトリ /\n'
                     '# title_patterns = 題名の正規表現 (板もディレクトリも無い世界の最終手段)。\n'
                     '# 原資料: share_world.html・share_world.htm・~ts/kansou/bbs@log_.cgi\n')
            yaml.safe_dump({'worlds': world_map}, fh, allow_unicode=True, sort_keys=True,
                           default_flow_style=False)

    synonyms = {'ts_genre': genre_syn, 'ts_type': type_syn, 'ts_keyword': kw_syn}
    taxonomies, report_tax = collections.OrderedDict(), collections.OrderedDict()

    for tax, meta in TAXONOMY_META.items():
        agg = collect(records, meta['field'], synonyms[tax])
        if tax == 'ts_keyword':
            # 旧目録の【属性】17 語はキーワードへ統合する (設計 §1.2)
            zok = collect(records, 'zokusei_raw', synonyms[tax])
            for name, slot in zok.items():
                dst = agg.setdefault(name, {'count': 0, 'raw_variants': collections.Counter(),
                                            'episodes': []})
                dst['count'] += slot['count']
                dst['raw_variants'].update(slot['raw_variants'])
                dst['episodes'].extend(slot['episodes'])
                dst['from_zokusei'] = True
        vocab = parse_vocab_page(os.path.join(root, meta['vocab_page']))
        vocab_matched = sum(1 for n in agg if n in vocab)
        names = sorted(agg, key=lambda n: (-agg[n]['count'], n))
        section = 'terms_' + tax.replace('ts_', '')
        slug_of, entries = assign_slugs(tax, names, overrides, section)
        overrides = slugmod.merge_section(overrides, section, entries)
        threshold = core_min.get(tax, 10)
        terms = []
        for name in names:
            slot = agg[name]
            info = vocab.get(name) or {}
            terms.append(collections.OrderedDict([
                ('slug', slug_of[name]),
                ('name', name),
                ('count', slot['count']),
                ('core', slot['count'] >= threshold),
                ('tier', info.get('section')),
                ('description', info.get('description')),
                ('raw_variants', [v for v, _ in slot['raw_variants'].most_common()]),
                ('from_zokusei', slot.get('from_zokusei', False)),
            ]))
        total = sum(t['count'] for t in terms)
        core = [t for t in terms if t['core']]
        taxonomies[tax] = collections.OrderedDict([
            ('rewrite', meta['rewrite']),
            ('source_field', meta['field']),
            ('terms', terms),
        ])
        report_tax[tax] = collections.OrderedDict([
            ('raw_tokens_distinct',
             len({t for r in records for t in (r.get(meta['field']) or [])})),
            ('terms', len(terms)),
            ('core_terms', len(core)),
            ('core_min_count', threshold),
            ('core_coverage_pct', round(100.0 * sum(t['count'] for t in core) / total, 2)
             if total else 0),
            ('tokens_total', total),
            ('terms_with_description', sum(1 for t in terms if t['description'])),
            ('vocab_page', meta['vocab_page']),
            ('vocab_page_terms', len(vocab)),
            ('vocab_page_matched', vocab_matched),
            ('merged_by_map', len(synonyms[tax])),
            ('variant_candidates', [[list(x) for x in g] for g in variant_candidates(agg)[:40]]),
        ])

    worlds = build_worlds(records, world_map)
    wsection = 'terms_world'
    wentries = {}
    for slug, w in worlds.items():
        wentries[w['name']] = {'slug': slug, 'status': 'auto', 'source': 'share-world'}
    overrides = slugmod.merge_section(overrides, wsection, wentries)
    taxonomies['ts_world'] = collections.OrderedDict([
        ('rewrite', 'world'),
        ('source_field', 'kansou_slug + source_path + title'),
        ('terms', [collections.OrderedDict([
            ('slug', slug), ('name', w['name']), ('count', w['count']),
            ('core', True), ('origin_author', w['origin_author']),
            ('note', w['note']), ('rule', w['rule']), ('match_reasons', w['match_reasons']),
        ]) for slug, w in worlds.items()]),
    ])
    taxonomies['ts_corpus'] = collections.OrderedDict([
        ('rewrite', None),
        ('source_field', 'corpus'),
        ('terms', [collections.OrderedDict([
            ('slug', s), ('name', n), ('count',
                                       sum(1 for r in records if r['corpus'] == s)),
            ('core', True), ('description', d)]) for s, n, d in CORPUS_TERMS]),
    ])

    # episode -> world の対応表 (import が使う)
    world_index = collections.OrderedDict()
    for slug, w in worlds.items():
        for eid in w['episodes']:
            world_index.setdefault(eid, []).append(slug)

    terms_json = collections.OrderedDict([
        ('generated_by', 'scripts/wp/terms_build.py'),
        ('episodes', len(records)),
        ('taxonomies', taxonomies),
        ('episode_worlds', world_index),
    ])
    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/terms_build.py'),
        ('episodes', len(records)),
        ('taxonomies', report_tax),
        ('worlds', collections.OrderedDict(
            (s, {'name': w['name'], 'count': w['count'], 'match_reasons': w['match_reasons']})
            for s, w in worlds.items())),
        ('worlds_total', len(worlds)),
        ('episodes_with_world', len(world_index)),
        ('corpus_counts', dict(collections.Counter(r['corpus'] for r in records))),
        ('slug_pending_review', sum(slugmod.pending_count(overrides, sec)
                                    for sec in overrides if sec.startswith('terms_'))),
        ('slug_pending_review_all_sections', slugmod.pending_count(overrides)),
        ('pykakasi_available', slugmod.kakasi() is not None),
    ])
    return terms_json, report, overrides, overrides_path


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--check', action='store_true', help='ファイルを書かず集計だけ')
    args = ap.parse_args(argv)
    if yaml is None:
        print('PyYAML が要ります (pip install pyyaml)', file=sys.stderr)
        return 2
    root = os.path.abspath(args.root)
    terms, report, overrides, overrides_path = build(root)

    for tax, rep in report['taxonomies'].items():
        print('%-11s raw=%4d -> terms=%4d (core %d, %.1f%% of tokens) desc=%d' %
              (tax, rep['raw_tokens_distinct'], rep['terms'], rep['core_terms'],
               rep['core_coverage_pct'], rep['terms_with_description']))
    print('ts_world    worlds=%d  episodes_with_world=%d' %
          (report['worlds_total'], report['episodes_with_world']))
    print('ts_corpus   %s' % report['corpus_counts'])
    print('slug 確認待ち (status != confirmed): %d 件  pykakasi=%s' %
          (report['slug_pending_review'], report['pykakasi_available']))
    if not slugmod.kakasi():
        print('  ! pykakasi が未導入。slug 候補が作れていません', file=sys.stderr)

    if not args.check:
        cat = os.path.join(root, 'catalog')
        os.makedirs(os.path.join(cat, 'reports'), exist_ok=True)
        with open(os.path.join(cat, 'terms.json'), 'w', encoding='utf-8') as fh:
            json.dump(terms, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        with open(os.path.join(cat, 'reports', 'terms_build.json'), 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        slugmod.save_overrides(overrides_path, overrides, notes=[
            'terms_* セクション = 分類語彙の slug (scripts/wp/terms_build.py)',
            'authors セクション = 作者 slug (scripts/wp/authors_build.py)',
            'works セクション = 作品 slug (scripts/wp/work_builder.py)',
        ])
        print('wrote catalog/terms.json, catalog/reports/terms_build.json, '
              'catalog/slug_overrides.yml')
    return 0


if __name__ == '__main__':
    sys.exit(main())
