#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""cluster_review.py — 作品クラスタのレビュー資料を書く (T24.3、仕様は specs/S149.md)。

抽出
  catalog/works.jsonl の連載 (episode_count >= 2) のうち、構成話の題名の芯 (tslib/works.py の trim_title)
  が 2 種以上あり、芯どうしの共通接頭辞 (NFKC・小文字) が 2 文字未満のもの。
  根拠が規則 5/6 (filename-stem / title-prefix) だけのものを先頭に、強い根拠のあるものを後に並べる。

出力 catalog/review/clusters-YYYY-MM.md (既定は今月。--out で変えられる)
  作品ごとに見出し (work_slug / 機械の題名 / 根拠 / 話数) と表
  (episode_id / 題名 / ファイル名 / 掲載日 / corpus)、末尾に「裁定」欄。
  裁定欄には芯ごとの振り分けの一次案と、それを work_overrides.yml に写したときの形
  (specs/S150.md) を書いておく。所有者は承認・修正だけで済む (specs/S114.md の推奨)。
  一次案の作り方 (機械の振り分けで、裁定ではない):
    - 芯を、共通接頭辞 2 文字以上でつながるものどうしの組にまとめる
    - 強い根拠 (series-title-nav / series-html / title-page / episode-nav) がある作品は ○
    - 根拠が規則 5/6 だけの作品は、話数の最も多い組 (同数なら掲載の早い組) を元の作品に残し、
      他の組は 2 話以上なら分割 (work_title は組で最も多い芯)、1 話なら切り離し

  --since REV を付けると、REV の catalog/works.jsonl と今の catalog/works.jsonl を話の所属で
  突き合わせ、work_slug が変わった作品の一覧 (旧 slug → 新 slug) を末尾に書く
  (specs/S148.md の「slug が変わった作品数」の記録。T24.6 の URL の付け替えの下調べ)。

使い方
  python3 scripts/wp/cluster_review.py [--since REV] [--out PATH]

抽出・一次案・文書の組み立て・slug の突き合わせは scripts/wp/tslib/cluster_review.py。
このファイルは引数の解釈と読み書きと表示だけ。
"""
import argparse
import datetime
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, cluster_review as cr             # noqa: E402
from tslib.fileio import read_jsonl, write_text, ensure_parent  # noqa: E402
from tslib.gitinfo import show_file, short_rev            # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT


def load_works(root):
    return read_jsonl(paths.works_path(root))


def load_episodes(root):
    return {r['episode_id']: r for r in read_jsonl(paths.episodes_path(root))}


def slug_changes(root, rev):
    """REV の works.jsonl と今の works.jsonl を話の所属で突き合わせる。"""
    text = show_file(root, rev, 'catalog/works.jsonl')
    old = [json.loads(line) for line in text.splitlines() if line.strip()]
    new = load_works(root)
    return old, new, cr.slug_rows(old, new)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--out', default=None, help='既定: catalog/review/clusters-<今月>.md')
    ap.add_argument('--since', default=None, help='slug の変化をこの git のリビジョンと比べる')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    month = datetime.date.today().strftime('%Y-%m')
    out_path = args.out or os.path.join(paths.catalog_dir(root), 'review', 'clusters-%s.md' % month)

    works = load_works(root)
    episodes = load_episodes(root)
    weak, strong = cr.split_targets(works)
    L = cr.render_report(works, episodes, month)
    if args.since:
        old, new, rows = slug_changes(root, args.since)
        L += cr.render_slug_section(old, new, rows, short_rev(root, args.since))

    ensure_parent(out_path)
    write_text(out_path, '\n'.join(L).rstrip('\n') + '\n')
    print('cluster_review: %d 作品 (弱い根拠 %d / 強い根拠 %d) → %s'
          % (len(weak) + len(strong), len(weak), len(strong), os.path.relpath(out_path, root)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
