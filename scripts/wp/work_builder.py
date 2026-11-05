#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""work_builder.py — Episode を Work にまとめる (T7.5、specs/S175.md)。

入力
  catalog/episodes.jsonl / catalog/authors.json
  takedown/denylist.yml  … 停止告知のある話が載っているかの照合だけに使う (T45.7.6)
  series.html            … 完結シリーズ一覧 (シード)
  novel/**/title*.htm*   … シリーズタイトルページ (シード)
出力
  catalog/works.jsonl          … Work 1 件 1 行
  catalog/work_overrides.yml   … あいまいなクラスタの人手上書き雛形 (T7.5.1 の人手確認)
  catalog/slug_overrides.yml   … work slug の候補 (T7.5.1 の人手確認)
  catalog/reports/work_builder.json

クラスタリングの規則と人手の上書きの当て方は scripts/wp/tslib/works.py (specs/S243.md)。
このファイルは読み書きと表示だけ。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, slugs as slugmod, works as wk  # noqa: E402
from tslib.checks import mark  # noqa: E402
from tslib.fileio import (read_jsonl, read_json, read_text, write_jsonl, write_json, write_yaml, ensure_dir,  # noqa: E402
                          exit_without_yaml)

DEFAULT_ROOT = paths.REPO_ROOT


def build(root, work_overrides_path=None):
    """入力を読んで works.jsonl とレポートを組む。
    戻り値 (works, report, overrides, overrides_path, review, override_errors, work_overrides, records)。"""
    records = read_jsonl(paths.episodes_path(root))
    authors = read_json(paths.authors_path(root))['authors']
    series_rows = wk.parse_series_html(root)
    disk_title_pages = wk.title_pages_on_disk(root)
    overrides_path = paths.slug_overrides_path(root)
    overrides = slugmod.load_overrides(overrides_path)
    work_overrides = wk.load_work_overrides(work_overrides_path or paths.work_overrides_path(root))
    works, report, overrides, review, override_errors = wk.assemble(
        records, authors, series_rows, disk_title_pages, overrides, work_overrides, wk.digest_in(root))
    return works, report, overrides, overrides_path, review, override_errors, work_overrides, records


def denylist_text_of(root):
    """takedown/denylist.yml の全文 (無ければ '')。停止告知の照合は文字列の包含で見る (旧 catalog_build と同じ)"""
    path = paths.denylist_path(root)
    return read_text(path) if os.path.exists(path) else ''


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    exit_without_yaml()
    root = os.path.abspath(args.root)
    works, report, overrides, overrides_path, review, override_errors, work_overrides, records = build(root)

    for line in wk.summary_lines(report):
        print(line)
    ok = not report['orphans'] and report['episodes_in_works'] == report['episodes']
    # 表示だけ。判断の値はレポートから読む (Checks に乗せない理由は tslib/checks.py)
    print('  [%s] 全 episode がいずれかの work に属す (orphan 0)' % mark(ok))
    confirmed_review = wk.confirmed_but_review(review, work_overrides)
    print('  [%s] work_overrides.yml の指示 (split / detach / merge_into / confirmed) が正しい (誤り %d 件)'
          % (mark(not override_errors), len(override_errors)))
    for e in override_errors:
        print('      ' + e)
    print('  [%s] confirmed: true の作品は needs_review が立たない' % mark(not confirmed_review))
    # 停止告知の照合 (T45.7.6。以前は catalog_build の自己検査で、前回の works.jsonl を読んでいた)
    notices, unlisted = wk.takedown_unlisted(records, works, denylist_text_of(root))
    print('  [%s] 停止告知のある話が denylist に載っている (告知 %d 件、未掲載 %d 件)'
          % (mark(not unlisted), notices, len(unlisted)))
    for ref in unlisted:
        print('      ' + ref)
    ok = ok and not override_errors and not confirmed_review and not unlisted

    if not args.check and not override_errors:
        ensure_dir(paths.reports_dir(root))
        write_jsonl(paths.works_path(root), works)
        write_json(paths.report_path(root, 'work_builder.json'), report)
        write_yaml(paths.work_overrides_path(root), {'works': wk.overrides_stub(work_overrides, review)},
                   wk.WORK_OVERRIDES_HEADER)
        slugmod.save_overrides(overrides_path, overrides, notes=slugmod.OVERRIDES_NOTES)
        print('wrote catalog/works.jsonl, catalog/work_overrides.yml, '
              'catalog/reports/work_builder.json, catalog/slug_overrides.yml')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
