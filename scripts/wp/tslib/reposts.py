# -*- coding: utf-8 -*-
"""reposts.py — 作者本人の再掲から回収した本文を catalog に入れるロジック (T7.8、specs/S179.md)。

設計 (specs/S41.md)「最大掲載原則」+ 所有者決定「事前保留は行わない(例外なし)」に基づく。
`~/ts-novels-holding/stage_repost/` の**検証済み 2 件だけ**を扱う (他は検証未完なので触らない)。

  雨女               文庫に 2011 年に載った作品。文庫版の HTML は失われており、
                     本文は**作者本人の pixiv 再掲 (id 351985)** から回収した。
                     目録エントリは既に honkan にあるので、そのレコードに
                     provenance と本文の在処を**足す** (新しい話を作らない)。
  きらいなもの→ＧＷ   **文庫には一度も載っていない** pixiv 限定の番外編。
                     `corpus=extern-repost` で区別し、書誌カードに
                     「文庫未掲載・pixiv 由来」と出せるメタを持たせる。
  bc-cafe.net の 3 点  きりか進ノ介さんの現行サイト bc-cafe.net/bcwiki.old/ の添付から回収した
                     文庫未掲載の 3 点 (T4.4・T34.2、specs/S204.md)。本文は UTF-8 化して
                     `reposts/repost__bcwiki__*` にコミット済みで、来歴は
                     `reposts/repost__bcwiki__provenance.json`。holding は読まない。
                     source_path は本文ファイルそのもので、.txt は段落分割、.htm は
                     body_convert の HTML の経路で本文になる。

本文はプレーンテキストなので **T7.6 の body_convert の対象外** (`body_convert_exempt: true`)。
本文の正本はリポジトリ直下 `reposts/<episode_id>.txt` (git 管理。bc-cafe.net の 3 点は
`reposts/repost__bcwiki__*.{txt,htm}` で、このスクリプトは書き換えない)。
"""
import collections
import os
import re
import unicodedata

from tslib.fileio import read_text, read_json

CORPUS = 'extern-repost'
BODY_DIR = 'reposts'

# 検証済みのものだけを明示的に列挙する。holding の残りは T4.4 (specs/S204.md) で
# 既収蔵との重複と同定済みで、収録するのは bc-cafe.net の 3 点だけ。
ITEMS = [
    {
        'key': 'amaonna',
        'text': 'amaonna_pixiv351985.txt',
        'meta': 'novel_351985.json',
        # 文庫に載っていた作品。既存の目録エントリに本文と来歴を足す
        'attach_to_source_path': 'novel/200802/15232641/rainGirl.html',
        'note': '文庫掲載作。文庫版 HTML は未回収のため、本文は作者本人の pixiv 再掲から取得',
    },
    {
        'key': 'kirainamono_gw',
        'text': 'kirainamono_GW_pixiv272830.txt',
        'meta': 'novel_272830.json',
        'attach_to_source_path': None,        # 文庫未掲載 → 新規エントリ
        'note': '文庫には一度も掲載されていない pixiv 限定の番外編',
    },
    # bc-cafe.net の 3 点 (T34.2)。題名は本文の見出しがあればそれ、無題なら添付の
    # ファイル名 (uncatalogued_build.py と同じく拡張子を除いて NFKC)
    {
        'key': 'ama',
        'route': 'bcwiki',
        'body_file': 'repost__bcwiki__ama.txt',
        'title': None,
        'attach_to_source_path': None,
        'note': '文庫には掲載されていない。作者本人のサイト bc-cafe.net の Wiki の添付 (無題) から取得',
    },
    {
        'key': 'saidai_no_higaisha',
        'route': 'bcwiki',
        'body_file': 'repost__bcwiki__saidai_no_higaisha.htm',
        'title': '最大の被害者、あるいは',
        'attach_to_source_path': None,
        'note': '文庫には掲載されていない。作者本人のサイト bc-cafe.net の Wiki の添付から取得',
    },
    {
        'key': 'bangai3ts',
        'route': 'bcwiki',
        'body_file': 'repost__bcwiki__bangai3ts.txt',
        'title': None,
        'attach_to_source_path': None,
        'note': '文庫には掲載されていない。作者本人のサイト bc-cafe.net の Wiki の添付 (無題) から取得',
    },
]
BCWIKI_PROVENANCE = 'repost__bcwiki__provenance.json'
BCWIKI_HOME = 'https://bc-cafe.net/'

RE_HEADER = re.compile(r'^(?:#[^\n]*\n)+\s*')


def pixiv_url(novel_id):
    return 'https://www.pixiv.net/novel/show.php?id=%s' % novel_id


# --------------------------------------------------------------------------- 入力

def split_holding_text(raw):
    """holding のテキスト (先頭に # のヘッダ行) → (本文, ヘッダ行の列)。"""
    body = RE_HEADER.sub('', raw).strip() + '\n'
    return body, [ln for ln in raw.split('\n') if ln.startswith('#')]


def load_item(holding, item):
    tpath = os.path.join(holding, item['text'])
    mpath = os.path.join(holding, item['meta'])
    if not (os.path.exists(tpath) and os.path.exists(mpath)):
        return None
    body, header = split_holding_text(read_text(tpath))
    return {'body': body, 'meta': read_json(mpath)['body'], 'header': header}


def load_bcwiki(root, item):
    """bc-cafe.net の 1 点: 本文ファイルと provenance.json の該当項目"""
    path = os.path.join(root, BODY_DIR, item['body_file'])
    ppath = os.path.join(root, BODY_DIR, BCWIKI_PROVENANCE)
    if not (os.path.exists(path) and os.path.exists(ppath)):
        return None
    prov = read_json(ppath)
    hit = [it for it in prov['items'] if it['file'] == item['body_file']]
    if len(hit) != 1:
        return None
    return {'page': prov, 'item': hit[0], 'size': os.path.getsize(path)}


# --------------------------------------------------------------------------- レコード

def bcwiki_provenance_of(got):
    page, it = got['page'], got['item']
    return collections.OrderedDict([
        ('method', 'author-repost'),
        ('route', 'bcwiki'),
        ('route_label', '作者本人のサイト bc-cafe.net の Wiki の添付から取得'),
        ('wiki_page', page['wiki_page']),
        ('wiki_page_url', page['wiki_page_url']),
        ('attachment_name', it['attachment_name']),
        ('source_url', it['url']),
        ('uploaded_at', it['uploaded_at']),
        ('acquired_at', page['acquired_at']),
        ('md5_raw', it['md5']),
        ('bytes_raw', it['bytes']),
        ('encoding_raw', it['encoding_raw']),
    ])


def new_record(eid, source_path, title, author, homepage, date_raw, size_kb, url):
    """文庫未掲載の新規エントリの共通部分 (pixiv と bc-cafe.net で同じ形)"""
    rec = collections.OrderedDict()
    rec['episode_id'] = eid
    rec['corpus'] = CORPUS
    rec['source_path'] = source_path
    rec['source_anchor'] = None
    rec['source_kind'] = 'repost'
    rec['source_exists'] = False
    rec['source_shared_by'] = 1
    rec['entry_type'] = 'text'
    rec['title'] = title
    rec['author'] = author
    rec['homepage'] = homepage
    rec['illustrator'] = []
    rec['illustrator_url'] = None
    rec['date'] = date_raw[:10]
    rec['date_raw'] = date_raw
    rec['date_precision'] = 'exact'
    rec['weekday'] = None
    rec['size_kb'] = size_kb
    rec['files_n'] = None
    rec['kansou_slug'] = None
    rec['kansou_annex_url'] = None
    rec['arasuji'] = None
    rec['comment'] = None
    rec['suisen'] = None
    rec['osusume'] = None
    rec['nav_links'] = []
    rec['inline_links'] = []
    rec['genre'] = []
    rec['genre_raw'] = []
    rec['type'] = []
    rec['type_raw'] = []
    rec['keywords'] = []
    rec['keywords_raw'] = []
    rec['zokusei'] = []
    rec['entry_role'] = 'work'
    rec['catalog_ref'] = None
    rec['orig_url'] = url
    rec['annex_url'] = None
    rec['annex_yays_url'] = None
    return rec


def provenance_of(meta, acquired_at):
    return collections.OrderedDict([
        ('method', 'author-repost'),
        ('route', 'pixiv'),
        ('route_label', '作者本人の pixiv 再掲から取得'),
        ('pixiv_novel_id', meta['id']),
        ('pixiv_user_id', meta['userId']),
        ('pixiv_user_name', meta['userName']),
        ('source_url', pixiv_url(meta['id'])),
        ('published_at', meta['createDate']),
        ('acquired_at', acquired_at),
        ('character_count', meta.get('characterCount')),
    ])


def bcwiki_record(item, got):
    """bc-cafe.net の 1 点の新規エントリ。"""
    it = got['item']
    eid = 'repost__bcwiki__%s' % item['key']
    stem = os.path.splitext(it['attachment_name'])[0]
    rec = new_record(eid, '%s/%s' % (BODY_DIR, item['body_file']),
                     unicodedata.normalize('NFKC', item['title'] or stem), it['author'],
                     BCWIKI_HOME, it['uploaded_at'], max(1, round(got['size'] / 1024)), it['url'])
    rec['source_exists'] = True      # 本文ファイルはリポジトリにある (body_convert が読む)
    # date は掲載日でなく Wiki に添付された日時 (provenance の uploaded_at と同じ)
    rec['provenance'] = bcwiki_provenance_of(got)
    rec['body_source_path'] = rec['source_path']
    if item['body_file'].endswith('.txt'):
        rec['body_format'] = 'plain-text'
        rec['body_convert_exempt'] = True
    else:
        rec['entry_type'] = 'html'   # 文庫の他の HTML の話と同じく body_convert にかける
    rec['body_note'] = item['note']
    rec['repost_url'] = rec['orig_url']
    rec['published_in_bunko'] = False
    return rec


def attach_pixiv(rec, meta, item, acquired_at):
    """既存の目録エントリに pixiv 再掲の本文と来歴を足す (rec をその場で書き換える)。"""
    eid = rec['episode_id']
    rec['provenance'] = provenance_of(meta, acquired_at)
    rec['body_source_path'] = '%s/%s.txt' % (BODY_DIR, eid)
    rec['body_format'] = 'plain-text'
    rec['body_convert_exempt'] = True
    rec['body_note'] = item['note']
    rec['repost_url'] = pixiv_url(meta['id'])
    rec['published_in_bunko'] = True
    return eid


def pixiv_record(meta, body, item, acquired_at):
    """文庫未掲載の pixiv 限定作の新規エントリ。"""
    eid = 'repost__pixiv__%s' % meta['id']
    rec = new_record(eid, 'repost/pixiv/%s' % meta['id'], meta['title'], meta['userName'],
                     'https://www.pixiv.net/users/%s' % meta['userId'], meta['createDate'],
                     max(1, round(len(body.encode('utf-8')) / 1024)), pixiv_url(meta['id']))
    rec['arasuji'] = (meta.get('description') or None)
    rec['keywords'] = [t['tag'] for t in (meta.get('tags') or {}).get('tags') or []]
    rec['keywords_raw'] = list(rec['keywords'])
    rec['provenance'] = provenance_of(meta, acquired_at)
    rec['body_source_path'] = '%s/%s.txt' % (BODY_DIR, eid)
    rec['body_format'] = 'plain-text'
    rec['body_convert_exempt'] = True
    rec['body_note'] = item['note']
    rec['repost_url'] = rec['orig_url']
    # 書誌カードに「文庫未掲載・pixiv 由来」と出すための旗
    rec['published_in_bunko'] = False
    return rec


def report_of(holding, patched, added, missing, bodies):
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/repost_build.py'),
        ('holding_dir', holding),
        ('items_declared', len(ITEMS)),
        ('patched_existing', patched),
        ('added_extern_repost', added),
        ('missing_inputs', missing),
        ('bodies_written', sorted(bodies)),
        ('note', 'holding の他の回収物は既収蔵との重複のため対象外 (T4.4、S204)。'
                 'bc-cafe.net の 3 点の本文は reposts/ のコミット済みファイルをそのまま使う (T34.2)'),
    ])


def build(root, records, holding, acquired_at):
    """episodes.jsonl のレコードを受け取り、自分の corpus 分を差し替える。
    戻り値 (records, bodies {episode_id: 本文}, report)。"""
    records = [r for r in records if r['corpus'] != CORPUS]
    by_path = {r['source_path']: r for r in records}

    bodies, added, patched, missing = {}, [], [], []
    for item in ITEMS:
        if item.get('route') == 'bcwiki':
            got = load_bcwiki(root, item)
            if not got:
                missing.append(item['body_file'])
                continue
            rec = bcwiki_record(item, got)
            records.append(rec)
            added.append(rec['episode_id'])
            continue
        got = load_item(holding, item)
        if not got:
            missing.append(item['text'])
            continue
        meta, body = got['meta'], got['body']
        if item['attach_to_source_path']:
            rec = by_path.get(item['attach_to_source_path'])
            if rec is None:
                missing.append(item['attach_to_source_path'])
                continue
            eid = attach_pixiv(rec, meta, item, acquired_at)
            patched.append(eid)
        else:
            rec = pixiv_record(meta, body, item, acquired_at)
            eid = rec['episode_id']
            records.append(rec)
            added.append(eid)
        bodies[eid] = body

    return records, bodies, report_of(holding, patched, added, missing, bodies)
