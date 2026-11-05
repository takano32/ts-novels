#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""terms_build.py — 分類語彙を `catalog/terms.json` に正規化する (T7.3、specs/S173.md)。

入力
  catalog/episodes.jsonl      … T7.1/T7.2 の出力 (genre_raw / type_raw / keywords_raw / zokusei_raw)
  genre.html / type_of_change.html / keyword.html … 当時の語彙定義ページ (term description の原資料)
  share_world.html            … シェアワールドの一覧 (ts_world の原資料)
  catalog/*_map.yml           … 正規化マップ。無ければ既定値で書き出す (以後は人手で編集)

出力
  catalog/terms.json                  … 6 本のタクソノミーの語彙 (raw_variants 付き)
  catalog/genre_map.yml / type_map.yml / keyword_map.yml / world_map.yml
  catalog/slug_overrides.yml          … slug 候補 (T7.5.1 の人手確認の対象。terms_* セクション)
  catalog/reports/terms_build.json    … 件数・被覆率・表記揺れ候補

ロジックは scripts/wp/tslib/terms.py (specs/S243.md)。このファイルは読み書きと表示だけ。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, slugs as slugmod, terms  # noqa: E402
from tslib.checks import report_lines  # noqa: E402
from tslib.fileio import read_jsonl, write_json, ensure_dir, yaml  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT


def build(root, core_min=None):
    """入力を読んで terms.json とレポートを組む。戻り値 (terms_json, report, overrides, overrides_path)。"""
    records = read_jsonl(paths.episodes_path(root))
    cat = paths.catalog_dir(root)
    overrides_path = paths.slug_overrides_path(root)
    overrides = slugmod.load_overrides(overrides_path)
    synonyms = terms.load_synonym_maps(cat)
    world_map = terms.load_world_map(cat)
    vocabs = {tax: terms.parse_vocab_page(os.path.join(root, meta['vocab_page']))
              for tax, meta in terms.TAXONOMY_META.items()}
    notes = []
    terms_json, report, overrides = terms.assemble(records, synonyms, vocabs, world_map, overrides,
                                                   core_min, notes)
    for line in notes:
        print(line)
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
    terms_json, report, overrides, overrides_path = build(root)

    for line in terms.summary_lines(report):
        print(line)
    if not slugmod.kakasi():
        print('  ! pykakasi が未導入。slug 候補が作れていません', file=sys.stderr)
    for line in report_lines(report['selftest']):
        print(line)

    if not args.check:
        ensure_dir(paths.reports_dir(root))
        write_json(paths.terms_path(root), terms_json)
        write_json(paths.report_path(root, 'terms_build.json'), report)
        slugmod.save_overrides(overrides_path, overrides, notes=slugmod.OVERRIDES_NOTES)
        print('wrote catalog/terms.json, catalog/reports/terms_build.json, '
              'catalog/slug_overrides.yml')
    return 0 if report['selftest_ok'] else 1


if __name__ == '__main__':
    sys.exit(main())
