#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""qa_report.py — catalog/reports/*.json をまとめて `catalog/QA.md` を書く (T7.7、specs/S180.md)。

`make catalog` の最後に走る。数字はすべて各段のレポート JSON から引くだけで、
ここでは何も計算し直さない (二重帳簿を作らないため)。本文の組み立ては scripts/wp/tslib/qa.py。
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, qa  # noqa: E402
from tslib.fileio import read_jsonl, read_json, write_text  # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT


def load_reports(root):
    """catalog/reports/*.json を鍵ごとに読む (無いものは None)。"""
    out = {}
    for key, name in qa.REPORT_NAMES.items():
        path = paths.report_path(root, name)
        out[key] = read_json(path) if os.path.exists(path) else None
    return out


def bodies_file_count(root):
    """bodies/*.md の実ファイル数 (レポートとの照合用)。bodies/ が無ければ 0。"""
    bodies_dir = paths.bodies_dir(root)
    if not os.path.isdir(bodies_dir):
        return 0
    return len([n for n in os.listdir(bodies_dir) if n.endswith('.md')])


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    text = qa.render(load_reports(root), read_jsonl(paths.episodes_path(root)), bodies_file_count(root))
    write_text(os.path.join(root, 'catalog', 'QA.md'), text)
    print('wrote catalog/QA.md')
    return 0


if __name__ == '__main__':
    sys.exit(main())
