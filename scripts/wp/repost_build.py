#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""repost_build.py — 作者本人の再掲から回収した本文を catalog に入れる (進行台帳 タスク 1.9)。

設計 v1.4「最大掲載原則」+ 所有者決定「事前保留は行わない(例外なし)」に基づく。
`~/ts-novels-holding/stage_repost/` の**検証済み 2 件だけ**を扱う (他は検証未完なので触らない)。

  雨女               文庫に 2011 年に載った作品。文庫版の HTML は失われており、
                     本文は**作者本人の pixiv 再掲 (id 351985)** から回収した。
                     目録エントリは既に honkan にあるので、そのレコードに
                     provenance と本文の在処を**足す** (新しい話を作らない)。
  きらいなもの→ＧＷ   **文庫には一度も載っていない** pixiv 限定の番外編。
                     `corpus=extern-repost` で区別し、書誌カードに
                     「文庫未掲載・pixiv 由来」と出せるメタを持たせる。

本文はプレーンテキストなので **1.6 body_convert の対象外** (`body_convert_exempt: true`)。
Phase 3 で空行区切りの段落分割 → core/paragraph ブロック化する。
本文の正本はリポジトリ直下 `reposts/<episode_id>.txt` (git 管理)。

`catalog_build.py` / `uncatalogued_build.py` の後に実行すること
(episodes.jsonl を読んで自分の corpus 分を差し替えて書き戻す = 何度でも同じ結果)。
"""
import argparse
import collections
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import catalog_build as cb                                # noqa: E402

DEFAULT_ROOT = cb.DEFAULT_ROOT
DEFAULT_HOLDING = os.path.expanduser('~/ts-novels-holding/stage_repost')
CORPUS = 'extern-repost'
BODY_DIR = 'reposts'

# 検証済みの 2 件だけを明示的に列挙する。holding には検証未完の回収物が同居しており
# (きりか進ノ介さんの wiki 発掘・ライターマンの 2 作・城弾シアター版ディレイド 4/5 等)、
# それらは進行台帳 8.4 で 👤 が検証を終えてから足す。
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
]

RE_HEADER = re.compile(r'^(?:#[^\n]*\n)+\s*')


def load_item(holding, item):
    tpath = os.path.join(holding, item['text'])
    mpath = os.path.join(holding, item['meta'])
    if not (os.path.exists(tpath) and os.path.exists(mpath)):
        return None
    with open(tpath, encoding='utf-8') as fh:
        raw = fh.read()
    body = RE_HEADER.sub('', raw).strip() + '\n'
    with open(mpath, encoding='utf-8') as fh:
        meta = json.load(fh)['body']
    return {'body': body, 'meta': meta,
            'header': [ln for ln in raw.split('\n') if ln.startswith('#')]}


def provenance_of(meta, acquired_at):
    return collections.OrderedDict([
        ('method', 'author-repost'),
        ('route', 'pixiv'),
        ('route_label', '作者本人の pixiv 再掲から取得'),
        ('pixiv_novel_id', meta['id']),
        ('pixiv_user_id', meta['userId']),
        ('pixiv_user_name', meta['userName']),
        ('source_url', 'https://www.pixiv.net/novel/show.php?id=%s' % meta['id']),
        ('published_at', meta['createDate']),
        ('acquired_at', acquired_at),
        ('character_count', meta.get('characterCount')),
    ])


def build(root, holding, acquired_at):
    cat = os.path.join(root, 'catalog')
    epath = os.path.join(cat, 'episodes.jsonl')
    records = [json.loads(line) for line in open(epath, encoding='utf-8')]
    records = [r for r in records if r['corpus'] != CORPUS]
    by_path = {r['source_path']: r for r in records}

    bodies, added, patched, missing = {}, [], [], []
    for item in ITEMS:
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
            eid = rec['episode_id']
            rec['provenance'] = provenance_of(meta, acquired_at)
            rec['body_source_path'] = '%s/%s.txt' % (BODY_DIR, eid)
            rec['body_format'] = 'plain-text'
            rec['body_convert_exempt'] = True
            rec['body_note'] = item['note']
            rec['repost_url'] = 'https://www.pixiv.net/novel/show.php?id=%s' % meta['id']
            rec['published_in_bunko'] = True
            patched.append(eid)
        else:
            eid = 'repost__pixiv__%s' % meta['id']
            rec = collections.OrderedDict()
            rec['episode_id'] = eid
            rec['corpus'] = CORPUS
            rec['source_path'] = 'repost/pixiv/%s' % meta['id']
            rec['source_anchor'] = None
            rec['source_kind'] = 'repost'
            rec['source_exists'] = False
            rec['source_shared_by'] = 1
            rec['entry_type'] = 'text'
            rec['title'] = meta['title']
            rec['author'] = meta['userName']
            rec['homepage'] = 'https://www.pixiv.net/users/%s' % meta['userId']
            rec['illustrator'] = []
            rec['illustrator_url'] = None
            rec['date'] = meta['createDate'][:10]
            rec['date_raw'] = meta['createDate']
            rec['date_precision'] = 'exact'
            rec['weekday'] = None
            rec['size_kb'] = max(1, round(len(body.encode('utf-8')) / 1024))
            rec['files_n'] = None
            rec['kansou_slug'] = None
            rec['kansou_annex_url'] = None
            rec['arasuji'] = (meta.get('description') or None)
            rec['comment'] = None
            rec['suisen'] = None
            rec['osusume'] = None
            rec['nav_links'] = []
            rec['inline_links'] = []
            rec['genre'] = []
            rec['genre_raw'] = []
            rec['type'] = []
            rec['type_raw'] = []
            rec['keywords'] = [t['tag'] for t in (meta.get('tags') or {}).get('tags') or []]
            rec['keywords_raw'] = list(rec['keywords'])
            rec['zokusei'] = []
            rec['entry_role'] = 'work'
            rec['catalog_ref'] = None
            rec['orig_url'] = 'https://www.pixiv.net/novel/show.php?id=%s' % meta['id']
            rec['annex_url'] = None
            rec['annex_yays_url'] = None
            rec['provenance'] = provenance_of(meta, acquired_at)
            rec['body_source_path'] = '%s/%s.txt' % (BODY_DIR, eid)
            rec['body_format'] = 'plain-text'
            rec['body_convert_exempt'] = True
            rec['body_note'] = item['note']
            rec['repost_url'] = rec['orig_url']
            # 書誌カードに「文庫未掲載・pixiv 由来」と出すための旗
            rec['published_in_bunko'] = False
            records.append(rec)
            added.append(eid)
        bodies[eid] = body

    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/repost_build.py'),
        ('holding_dir', holding),
        ('items_declared', len(ITEMS)),
        ('patched_existing', patched),
        ('added_extern_repost', added),
        ('missing_inputs', missing),
        ('bodies_written', sorted(bodies)),
        ('note', 'holding の他の回収物は検証未完のため対象外 (進行台帳 8.4)'),
    ])
    return records, bodies, report


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--holding', default=DEFAULT_HOLDING)
    ap.add_argument('--acquired-at', default='2026-08-30')
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    records, bodies, report = build(root, args.holding, args.acquired_at)

    print('既存エントリに本文と来歴を追加: %s' % report['patched_existing'])
    print('文庫未掲載として新規収録: %s' % report['added_extern_repost'])
    if report['missing_inputs']:
        print('  ! 入力が見つからない: %s' % report['missing_inputs'], file=sys.stderr)
    ok = not report['missing_inputs']
    print('  [%s] 宣言した %d 件がすべて収録できた'
          % ('OK ' if ok else 'NG ', report['items_declared']))

    if not args.check:
        cat = os.path.join(root, 'catalog')
        os.makedirs(os.path.join(root, BODY_DIR), exist_ok=True)
        for eid, body in bodies.items():
            with open(os.path.join(root, BODY_DIR, eid + '.txt'), 'w', encoding='utf-8') as fh:
                fh.write(body)
        with open(os.path.join(cat, 'episodes.jsonl'), 'w', encoding='utf-8') as fh:
            for r in records:
                fh.write(json.dumps(r, ensure_ascii=False) + '\n')
        os.makedirs(os.path.join(cat, 'reports'), exist_ok=True)
        with open(os.path.join(cat, 'reports', 'repost_build.json'), 'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        print('wrote %s/*.txt (%d), catalog/episodes.jsonl, catalog/reports/repost_build.json'
              % (BODY_DIR, len(bodies)))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
