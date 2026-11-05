#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""catalog_build.py — 目録 (正規 lib1〜73 + 旧 lib01〜09) を catalog/episodes.jsonl へ正規化する。

T7.1 (corpus=honkan) と T7.2 (corpus=legacy)。仕様と記録は specs/S171.md・S172.md。
仕様は scripts/workflows/wp-survey-2026-08-30.json の catalog 節 (fields / parse_strategy)。

  - honkan のエントリ = `<TABLE BORDER=1 …>` 1 つ。必ず 4 行、1 行目は 6 セル。
    HTML は 1 行に長大に詰まっているので行単位パースは禁止 (常に findall)。
  - legacy は 2 形式: lib01〜06 = 非テーブル (`NNKB(updated M/D)<b><a …>［題］</a> 作・NAME</b><br>`)、
    lib07〜09 = 移行期テーブル (【属性】欄・2 桁年・`***` 欠損値・per-work の noteky 感想リンク)。
    honkan と同じ source_path を指す legacy エントリは重複として落とす (パス単位 dedup)。
  - 冪等キー = episode_id。source_path の `/` を `__` に置換し、anchor があれば `@<anchor>`、
    同一パスを指すエントリが複数ある場合は `+<掲載日 YYYYMMDD>`。
  - mailto はこの段階で捨てる (WP の DB にメールアドレスを構造的に入れないため)。
    自己検査が「捨てたはずのアドレスが出力に残っていないこと」をロジックで確認する。

ロジックは scripts/wp/tslib/catalog/ (specs/S243.md)。このファイルは引数の解釈と読み書きだけ。

使い方:
    python3 scripts/wp/catalog_build.py                 # 生成 + 自己検査 + レポート
    python3 scripts/wp/catalog_build.py --check         # 何も書かずに検査だけ
    python3 scripts/wp/catalog_build.py --no-provenance # git 走査を省く (高速)
    python3 scripts/wp/catalog_build.py --no-legacy     # corpus=honkan のみ (T7.1 相当の出力)
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths  # noqa: E402
from tslib.fileio import write_jsonl, write_json, ensure_parent  # noqa: E402
from tslib.catalog.provenance import build_provenance_map  # noqa: E402
from tslib.catalog.records import build_regular, build_legacy, strip_private  # noqa: E402
from tslib.catalog.selftest import selftest  # noqa: E402
from tslib.catalog.report import report_of, summary_lines  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT
DEFAULT_ANNEX_BASE = paths.DEFAULT_ANNEX_BASE


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--out', default=None, help='既定 <root>/catalog/episodes.jsonl')
    ap.add_argument('--report', default=None, help='既定 <root>/catalog/reports/catalog_build.json')
    ap.add_argument('--annex-base', default=DEFAULT_ANNEX_BASE)
    ap.add_argument('--no-provenance', action='store_true', help='git 走査を省く')
    ap.add_argument('--no-legacy', action='store_true', help='旧目録 lib01-09 を含めない')
    ap.add_argument('--check', action='store_true', help='ファイルを書かず検査のみ')
    args = ap.parse_args(argv)

    root = os.path.abspath(args.root)
    annex_base = paths.with_trailing_slash(args.annex_base)
    prov_map = build_provenance_map(root) if not args.no_provenance else {}
    records, failures = build_regular(root, annex_base, prov_map=prov_map)
    legacy_stats = None
    if not args.no_legacy:
        legacy, legacy_failures, legacy_stats = build_legacy(root, annex_base, records, prov_map)
        records = records + legacy
        failures = failures + legacy_failures
    checks = selftest(records, failures, legacy_stats)
    rep = report_of(records, failures, checks, legacy_stats)
    for line in summary_lines(rep):
        print(line)

    if not args.check:
        out = args.out or paths.episodes_path(root)
        rpt = args.report or paths.report_path(root, 'catalog_build.json')
        ensure_parent(out)
        ensure_parent(rpt)
        write_jsonl(out, (strip_private(r) for r in records))
        write_json(rpt, rep)
        print('wrote %s (%d lines) and %s' % (os.path.relpath(out, root), len(records),
                                              os.path.relpath(rpt, root)))
    return 0 if checks.ok else 1


if __name__ == '__main__':
    sys.exit(main())
