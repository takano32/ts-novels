#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""episode_overrides.py — 話 1 件単位の人手上書きを episodes.jsonl に適用する。

入力  catalog/episode_overrides.yml … 鍵 = source_path (`#anchor` 可)
      catalog/episodes.jsonl        … 1.1/1.2/1.8/1.9 の出力
出力  catalog/episodes.jsonl        … 上書きを適用して書き戻す
      catalog/reports/episode_overrides.json

**旧館の原本 (lib*.html) の誤植を catalog 側で直す唯一の場所**。別館 (GitHub Pages)
は原本レイヤなので誤植もそのまま保ち、直すのはこちらだけ、という分担にしている。
`make catalog` では repost_build の直後・terms_build の直前に走らせる
(terms / authors / works が上書き後の episodes.jsonl を読むように)。

**episode_id は source_path から導くので、この上書きでは決して変わらない**。
上書き可能な欄も author / kansou_slug / entry_role / title / homepage / date に
限っており、結合キーや provenance には触れない。

`expect:` は上書き前にあるはずの値。合わなければ自己検査が落ちる (原本や
パーサが変わったのに古い上書きが生き残る事故を防ぐ)。既に `set:` の値に
なっている場合は「適用済み」として素通しする (再実行・`--check` 用)。
"""
import argparse
import collections
import json
import os
import sys

try:
    import yaml
except ImportError:                                       # pragma: no cover
    yaml = None

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DEFAULT_ANNEX_BASE = 'https://takano32.github.io/ts-novels/'
# 結合キー (episode_id) と来歴 (provenance) は決して上書きさせない
SETTABLE = ('author', 'kansou_slug', 'entry_role', 'title', 'homepage', 'date')


def load_overrides(path):
    if not os.path.exists(path):
        return {}
    if yaml is None:
        raise RuntimeError('PyYAML が必要です (pip install pyyaml)')
    with open(path, encoding='utf-8') as fh:
        return (yaml.safe_load(fh) or {}).get('episodes') or {}


def key_of(rec):
    return (rec['source_path'] + '#' + rec['source_anchor']) if rec.get('source_anchor') \
        else rec['source_path']


def apply_overrides(records, overrides, annex_base=DEFAULT_ANNEX_BASE):
    """(applied, problems) を返す。records はその場で書き換える。"""
    by_key = collections.defaultdict(list)
    for rec in records:
        by_key[key_of(rec)].append(rec)
    applied, problems = [], []
    for key in sorted(overrides):
        spec = overrides[key] or {}
        targets = by_key.get(key) or []
        if len(targets) != 1:
            problems.append({'key': key, 'why': 'episode が %d 件 (1 件でなければ適用しない)'
                             % len(targets)})
            continue
        rec = targets[0]
        sets = spec.get('set') or {}
        expect = spec.get('expect') or {}
        bad = [f for f in sets if f not in SETTABLE]
        if bad:
            problems.append({'key': key, 'why': '上書きできない欄 %s' % bad})
            continue
        for field, want in expect.items():
            if rec.get(field) == want or rec.get(field) == sets.get(field):
                continue
            problems.append({'key': key, 'why': 'expect 不一致',
                             'field': field, 'expect': want, 'actual': rec.get(field)})
        changes = []
        for field, value in sets.items():
            before = rec.get(field)
            if before == value:
                continue
            rec[field] = value
            changes.append({'field': field, 'from': before, 'to': value})
            if field == 'kansou_slug':
                rec['kansou_annex_url'] = (
                    annex_base + '~ts/kansou/bbs@log_%s.cgi' % value if value else None)
        note = spec.get('note')
        if changes:
            rec['overrides_applied'] = changes
            if note:
                rec['override_note'] = note
        applied.append({'key': key, 'episode_id': rec['episode_id'],
                        'changes': changes, 'already_applied': not changes, 'note': note})
    return applied, problems


def selftest(applied, problems, overrides):
    results = []

    def check(name, ok, detail=''):
        results.append((name, bool(ok), detail))
    check('episode_overrides.yml の全行が 1 話に当たる',
          not [p for p in problems if p['why'].startswith('episode が')],
          '外れ %d 件' % len([p for p in problems if p['why'].startswith('episode が')]))
    check('expect (上書き前の値) が原本と一致',
          not [p for p in problems if p['why'] == 'expect 不一致'],
          '不一致 %s' % [p for p in problems if p['why'] == 'expect 不一致'][:3])
    check('上書きできない欄を触っていない',
          not [p for p in problems if p['why'].startswith('上書きできない')])
    check('上書きの件数を記録', True,
          '%d 行 / 実際に変わった話 %d 件'
          % (len(overrides), sum(1 for a in applied if a['changes'])))
    return all(ok for _, ok, _ in results), results


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    if yaml is None:
        print('PyYAML が要ります', file=sys.stderr)
        return 2
    root = os.path.abspath(args.root)
    cat = os.path.join(root, 'catalog')
    path = os.path.join(cat, 'episodes.jsonl')
    records = [json.loads(line) for line in open(path, encoding='utf-8')]
    before_ids = [r['episode_id'] for r in records]
    overrides = load_overrides(os.path.join(cat, 'episode_overrides.yml'))
    applied, problems = apply_overrides(records, overrides, args.annex_base)
    ok, results = selftest(applied, problems, overrides)
    ok = ok and before_ids == [r['episode_id'] for r in records]

    print('episode_overrides: %d 行 / 変更 %d 話 / 適用済み %d 話' %
          (len(overrides), sum(1 for a in applied if a['changes']),
           sum(1 for a in applied if a['already_applied'])))
    for a in applied:
        for c in a['changes']:
            print('  %s  %s: %r -> %r' % (a['episode_id'], c['field'], c['from'], c['to']))
    for n, o, d in results:
        print('  [%s] %s %s' % ('OK ' if o else 'NG ', n, d))

    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/episode_overrides.py'),
        ('rules', len(overrides)),
        ('episodes_changed', sum(1 for a in applied if a['changes'])),
        ('episodes_already_applied', sum(1 for a in applied if a['already_applied'])),
        ('applied', applied),
        ('problems', problems),
        ('episode_id_unchanged', before_ids == [r['episode_id'] for r in records]),
        ('selftest', [{'name': n, 'ok': o, 'detail': d} for n, o, d in results]),
        ('selftest_ok', ok),
    ])
    if not args.check:
        os.makedirs(os.path.join(cat, 'reports'), exist_ok=True)
        with open(path, 'w', encoding='utf-8') as fh:
            for rec in records:
                fh.write(json.dumps(rec, ensure_ascii=False) + '\n')
        with open(os.path.join(cat, 'reports', 'episode_overrides.json'),
                  'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        print('wrote catalog/episodes.jsonl, catalog/reports/episode_overrides.json')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
