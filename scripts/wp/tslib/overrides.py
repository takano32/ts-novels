# -*- coding: utf-8 -*-
"""overrides.py — 話 1 件単位の人手上書き (catalog/episode_overrides.yml) を episodes.jsonl に当てるロジック。

**旧サイトの原本 (lib*.html) の誤植を catalog 側で直す唯一の場所**。原本アーカイブ (GitHub Pages)
は原本レイヤなので誤植もそのまま保ち、直すのはこちらだけ、という分担にしている。

**episode_id は source_path から導くので、この上書きでは決して変わらない**。
上書き可能な欄も SETTABLE に限っており、結合キーや provenance には触れない。

`expect:` は上書き前にあるはずの値。合わなければ自己検査が落ちる (原本や
パーサが変わったのに古い上書きが生き残る事故を防ぐ)。既に `set:` の値に
なっている場合は「適用済み」として素通しする (再実行・`--check` 用)。
"""
import collections

from tslib.checks import Checks
from tslib.fileio import read_yaml
from tslib.paths import DEFAULT_ANNEX_BASE, kansou_annex_url

# 結合キー (episode_id) と来歴 (provenance) は決して上書きさせない
SETTABLE = ('author', 'kansou_slug', 'entry_role', 'title', 'homepage', 'date')


def load_overrides(path):
    """episode_overrides.yml の episodes セクション (無ければ {})。"""
    return read_yaml(path).get('episodes') or {}


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
                rec['kansou_annex_url'] = kansou_annex_url(annex_base, value)
        note = spec.get('note')
        if changes:
            rec['overrides_applied'] = changes
            if note:
                rec['override_note'] = note
        applied.append({'key': key, 'episode_id': rec['episode_id'],
                        'changes': changes, 'already_applied': not changes, 'note': note})
    return applied, problems


def selftest(applied, problems, overrides):
    c = Checks()
    c.check('episode_overrides.yml の全行が 1 話に当たる',
            not [p for p in problems if p['why'].startswith('episode が')],
            '外れ %d 件' % len([p for p in problems if p['why'].startswith('episode が')]))
    c.check('expect (上書き前の値) が原本と一致',
            not [p for p in problems if p['why'] == 'expect 不一致'],
            '不一致 %s' % [p for p in problems if p['why'] == 'expect 不一致'][:3])
    c.check('上書きできない欄を触っていない',
            not [p for p in problems if p['why'].startswith('上書きできない')])
    c.check('上書きの件数を記録', True,
            '%d 行 / 実際に変わった話 %d 件'
            % (len(overrides), sum(1 for a in applied if a['changes'])))
    return c


def report_of(overrides, applied, problems, ids_unchanged, checks, ok):
    """レポート。selftest_ok は旧版どおり ok (検査が全部通り、かつ episode_id の列が変わらない) の値。"""
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/episode_overrides.py'),
        ('rules', len(overrides)),
        ('episodes_changed', sum(1 for a in applied if a['changes'])),
        ('episodes_already_applied', sum(1 for a in applied if a['already_applied'])),
        ('applied', applied),
        ('problems', problems),
        ('episode_id_unchanged', ids_unchanged),
        ('selftest', checks.as_report()),
        ('selftest_ok', ok),
    ])
