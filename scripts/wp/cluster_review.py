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

抽出・一次案・作品 1 件の書式・slug の突き合わせは scripts/wp/tslib/cluster_review.py。
このファイルは引数の解釈と読み書きと表示だけ。
"""
import argparse
import datetime
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, cluster_review as cr             # noqa: E402
from tslib.gitinfo import show_file, short_rev            # noqa: E402

DEFAULT_ROOT = paths.REPO_ROOT


def load_works(root):
    with open(os.path.join(root, 'catalog', 'works.jsonl'), encoding='utf-8') as fh:
        return [json.loads(line) for line in fh]


def load_episodes(root):
    out = {}
    with open(os.path.join(root, 'catalog', 'episodes.jsonl'), encoding='utf-8') as fh:
        for line in fh:
            r = json.loads(line)
            out[r['episode_id']] = r
    return out


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
    out_path = args.out or os.path.join(root, 'catalog', 'review', 'clusters-%s.md' % month)

    works = load_works(root)
    episodes = load_episodes(root)
    targets = [w for w in works if cr.is_target(w)]
    weak = sorted([w for w in targets if cr.is_weak(w)], key=lambda w: w['work_slug'])
    strong = sorted([w for w in targets if not cr.is_weak(w)], key=lambda w: w['work_slug'])

    L = ['# 作品クラスタのレビュー (%s)' % month, '',
         '`scripts/wp/cluster_review.py` の出力 (仕様は specs/S149.md、裁定の書き方は specs/S150.md)。'
         '手で直さず、作り直すときはスクリプトを走らせる。裁定はこのファイルの写しか返信でもらい、'
         '`catalog/work_overrides.yml` に転記する (T24.5)。', '',
         '- 対象: 連載 (2 話以上) のうち、話の題名の芯が 2 種以上あり、芯どうしの共通接頭辞が 2 文字未満の作品',
         '- 件数: **%d 作品** (根拠が規則 5/6 だけ = 弱い根拠 %d を先頭に、強い根拠のあるもの %d を後に)'
         % (len(targets), len(weak), len(strong)),
         '- 一次案は芯ごとの機械の振り分けで、裁定ではない。強い根拠のある作品は ○、弱い根拠だけの作品は'
         '話数の最も多い芯の組を残し、他の組を分割 (2 話以上) か切り離し (1 話) にしている', '',
         '## 弱い根拠だけの作品 (%d)' % len(weak), '']
    n = 0
    for w in weak:
        n += 1
        L += cr.render_work(w, n, episodes)
    L += ['## 強い根拠のある作品 (%d)' % len(strong), '']
    for w in strong:
        n += 1
        L += cr.render_work(w, n, episodes)

    if args.since:
        old, new, rows = slug_changes(root, args.since)
        rev = short_rev(root, args.since)
        merged = sum(1 for w, t in rows if len(t) == 1 and
                     sum(1 for x in new if x['work_slug'] == t[0] and x['episode_count'] != w['episode_count']))
        L += ['## slug が変わった作品 (%s との比較)' % rev, '',
              '題名の芯の追加規則 (specs/S148.md) などで work_slug が変わった作品。話の所属で突き合わせた。'
              '本番の URL の付け替え (T24.6) は本番の `export-pathmap` の差分で作るので、これはその下調べ。', '',
              '- works: %d → %d / slug が変わった作品: **%d** (うち他の作品と束ね直されたもの %d)'
              % (len(old), len(new), len(rows), merged), '',
              '| 旧 work_slug | 新 work_slug | 旧題名 | 新題名 | 話数 (旧 → 新の作品) |',
              '|---|---|---|---|---|']
        new_by_slug = {w['work_slug']: w for w in new}
        for w, tgt in rows:
            t = new_by_slug[tgt[0]]
            L.append('| `%s` | %s | %s | %s | %d → %d |' % (
                w['work_slug'], ', '.join('`%s`' % s for s in tgt), cr.md_cell(w['title']),
                cr.md_cell(t['title']), w['episode_count'], t['episode_count']))
        L.append('')

    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    with open(out_path, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L).rstrip('\n') + '\n')
    print('cluster_review: %d 作品 (弱い根拠 %d / 強い根拠 %d) → %s'
          % (len(targets), len(weak), len(strong), os.path.relpath(out_path, root)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
