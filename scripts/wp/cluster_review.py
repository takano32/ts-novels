#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""cluster_review.py — 作品クラスタのレビュー資料を書く (T.24.3、仕様は specs/S.149.md)。

抽出
  catalog/works.jsonl の連載 (episode_count >= 2) のうち、構成話の題名の芯 (work_builder.trim_title)
  が 2 種以上あり、芯どうしの共通接頭辞 (NFKC・小文字) が 2 文字未満のもの。
  根拠が規則 5/6 (filename-stem / title-prefix) だけのものを先頭に、強い根拠のあるものを後に並べる。

出力 catalog/review/clusters-YYYY-MM.md (既定は今月。--out で変えられる)
  作品ごとに見出し (work_slug / 機械の題名 / 根拠 / 話数) と表
  (episode_id / 題名 / ファイル名 / 掲載日 / corpus)、末尾に「裁定」欄。
  裁定欄には芯ごとの振り分けの一次案と、それを work_overrides.yml に写したときの形
  (specs/S.150.md) を書いておく。所有者は承認・修正だけで済む (specs/S.114.md の推奨)。
  一次案の作り方 (機械の振り分けで、裁定ではない):
    - 芯を、共通接頭辞 2 文字以上でつながるものどうしの組にまとめる
    - 強い根拠 (series-title-nav / series-html / title-page / episode-nav) がある作品は ○
    - 根拠が規則 5/6 だけの作品は、話数の最も多い組 (同数なら掲載の早い組) を元の作品に残し、
      他の組は 2 話以上なら分割 (work_title は組で最も多い芯)、1 話なら切り離し

  --since REV を付けると、REV の catalog/works.jsonl と今の catalog/works.jsonl を話の所属で
  突き合わせ、work_slug が変わった作品の一覧 (旧 slug → 新 slug) を末尾に書く
  (specs/S.148.md の「slug が変わった作品数」の記録。T.24.6 の URL の付け替えの下調べ)。

使い方
  python3 scripts/wp/cluster_review.py [--since REV] [--out PATH]
"""
import argparse
import collections
import datetime
import json
import os
import subprocess
import sys
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import work_builder as wb                                 # noqa: E402

DEFAULT_ROOT = wb.DEFAULT_ROOT
WEAK_ONLY = {'filename-stem', 'title-prefix'}


def nfkc_lower(s):
    return unicodedata.normalize('NFKC', s or '').lower()


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


def is_target(w):
    if w['episode_count'] < 2:
        return False
    cores = {wb.trim_title(e['title']) for e in w['episodes']}
    if len(cores) < 2:
        return False
    return len(wb.common_prefix([nfkc_lower(c) for c in cores])) < 2


def is_weak(w):
    return set(w['evidence']) <= WEAK_ONLY


def core_families(w):
    """芯ごとの組。共通接頭辞 2 文字以上でつながる芯を 1 組にする (掲載順に貪欲)。
    戻り値は [(代表の芯, [episode, …]), …] で、元の作品に残す組が先頭。"""
    fams = []                                   # [{'keys': [芯の nfkc_lower], 'eps': [...], 'cores': Counter}]
    for i, e in enumerate(w['episodes']):
        core = wb.trim_title(e['title'])
        key = nfkc_lower(core)
        for f in fams:
            if any(len(wb.common_prefix([key, k])) >= 2 for k in f['keys']):
                break
        else:
            f = {'keys': [], 'eps': [], 'cores': collections.Counter(), 'first': i}
            fams.append(f)
        if key not in f['keys']:
            f['keys'].append(key)
        f['eps'].append(e)
        f['cores'][core] += 1
    fams.sort(key=lambda f: (-len(f['eps']), f['first']))
    return [(f['cores'].most_common(1)[0][0], f['eps']) for f in fams]


def md_cell(s):
    return (s or '').replace('|', '\\|').replace('\n', ' ')


def yaml_str(s):
    return json.dumps(s, ensure_ascii=False)


def proposal(w):
    """一次案 (本文の行と、work_overrides.yml に写したときの行)。"""
    key = '%s|%s' % (w['author_slug'], w['title'])
    fams = core_families(w)
    if not is_weak(w):
        strong = sorted(set(w['evidence']) & wb.STRONG)
        text = ['- 一次案: **○** (強い根拠 %s で繋がっている。芯の違いは参考として下に並べる)'
                % ' / '.join('`%s`' % r for r in strong)]
        for core, eps in fams:
            text.append('  - 芯「%s」: %d 話' % (md_cell(core), len(eps)))
        return text, ['  %s:' % yaml_str(key), '    confirmed: true']
    if len(fams) == 1:
        return (['- 一次案: **○** (芯は違うが、どれも共通接頭辞 2 文字以上でつながる)'],
                ['  %s:' % yaml_str(key), '    confirmed: true'])
    keep_core, keep_eps = fams[0]
    splits = [(c, eps) for c, eps in fams[1:] if len(eps) >= 2]
    detaches = [eps[0] for c, eps in fams[1:] if len(eps) == 1]
    kinds = (['分割'] if splits else []) + (['切り離し'] if detaches else [])
    text = ['- 一次案: **%s** (芯ごとの機械の振り分け。元の作品に残すのは芯「%s」の %d 話)'
            % (' + '.join(kinds), md_cell(keep_core), len(keep_eps))]
    for c, eps in splits:
        text.append('  - 分割 → 新しい作品「%s」: %s' % (md_cell(c), ', '.join('`%s`' % e['episode_id'] for e in eps)))
    for e in detaches:
        text.append('  - 切り離し → 単独の作品「%s」: `%s`' % (md_cell(e['title']), e['episode_id']))
    ylines = ['  %s:' % yaml_str(key), '    confirmed: true']
    if splits:
        ylines.append('    split:')
        for c, eps in splits:
            ylines.append('      - work_title: %s' % yaml_str(c))
            ylines.append('        episodes: [%s]' % ', '.join(e['episode_id'] for e in eps))
    if detaches:
        ylines.append('    detach: [%s]' % ', '.join(e['episode_id'] for e in detaches))
    return text, ylines


def render_work(w, n, episodes):
    out = ['### %d. `%s` — %s' % (n, w['work_slug'], md_cell(w['title'])), '',
           '- 根拠: %s / 話数: %d / 作者: %s (`%s`)%s' % (
               ', '.join('`%s`' % r for r in w['evidence']) or '—', w['episode_count'],
               md_cell(w['author_display']), w['author_slug'],
               ' / 複数ディレクトリ' if w.get('multi_directory') else ''),
           '',
           '| episode_id | 題名 | ファイル名 | 掲載日 | corpus |',
           '|---|---|---|---|---|']
    for e in w['episodes']:
        rec = episodes.get(e['episode_id'], {})
        out.append('| `%s` | %s | %s | %s | %s |' % (
            e['episode_id'], md_cell(e['title']), md_cell(os.path.basename(e['source_path'])),
            e.get('date') or '—', rec.get('corpus') or '—'))
    text, ylines = proposal(w)
    out += ['', '**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):', ''] + text
    out += ['', '<details><summary>一次案を work_overrides.yml に写したときの形</summary>', '',
            '```yaml'] + ylines + ['```', '', '</details>', '']
    return out


def slug_changes(root, rev):
    """REV の works.jsonl と今の works.jsonl を話の所属で突き合わせる。"""
    r = subprocess.run(['git', '-C', root, 'show', '%s:catalog/works.jsonl' % rev],
                       capture_output=True, text=True, check=True)
    old = [json.loads(line) for line in r.stdout.splitlines() if line.strip()]
    new = load_works(root)
    new_of = {e['episode_id']: w for w in new for e in w['episodes']}
    rows = []
    for w in old:
        tgt = {new_of[e['episode_id']]['work_slug'] for e in w['episodes'] if e['episode_id'] in new_of}
        if tgt == {w['work_slug']}:
            continue
        rows.append((w, sorted(tgt)))
    rows.sort(key=lambda x: x[0]['work_slug'])
    return old, new, rows


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
    targets = [w for w in works if is_target(w)]
    weak = sorted([w for w in targets if is_weak(w)], key=lambda w: w['work_slug'])
    strong = sorted([w for w in targets if not is_weak(w)], key=lambda w: w['work_slug'])

    L = ['# 作品クラスタのレビュー (%s)' % month, '',
         '`scripts/wp/cluster_review.py` の出力 (仕様は specs/S.149.md、裁定の書き方は specs/S.150.md)。'
         '手で直さず、作り直すときはスクリプトを走らせる。裁定はこのファイルの写しか返信でもらい、'
         '`catalog/work_overrides.yml` に転記する (T.24.5)。', '',
         '- 対象: 連載 (2 話以上) のうち、話の題名の芯が 2 種以上あり、芯どうしの共通接頭辞が 2 文字未満の作品',
         '- 件数: **%d 作品** (根拠が規則 5/6 だけ = 弱い根拠 %d を先頭に、強い根拠のあるもの %d を後に)'
         % (len(targets), len(weak), len(strong)),
         '- 一次案は芯ごとの機械の振り分けで、裁定ではない。強い根拠のある作品は ○、弱い根拠だけの作品は'
         '話数の最も多い芯の組を残し、他の組を分割 (2 話以上) か切り離し (1 話) にしている', '',
         '## 弱い根拠だけの作品 (%d)' % len(weak), '']
    n = 0
    for w in weak:
        n += 1
        L += render_work(w, n, episodes)
    L += ['## 強い根拠のある作品 (%d)' % len(strong), '']
    for w in strong:
        n += 1
        L += render_work(w, n, episodes)

    if args.since:
        old, new, rows = slug_changes(root, args.since)
        rev = subprocess.run(['git', '-C', root, 'rev-parse', '--short', args.since],
                             capture_output=True, text=True, check=True).stdout.strip()
        merged = sum(1 for w, t in rows if len(t) == 1 and
                     sum(1 for x in new if x['work_slug'] == t[0] and x['episode_count'] != w['episode_count']))
        L += ['## slug が変わった作品 (%s との比較)' % rev, '',
              '題名の芯の追加規則 (specs/S.148.md) などで work_slug が変わった作品。話の所属で突き合わせた。'
              '本番の URL の付け替え (T.24.6) は本番の `export-pathmap` の差分で作るので、これはその下調べ。', '',
              '- works: %d → %d / slug が変わった作品: **%d** (うち他の作品と束ね直されたもの %d)'
              % (len(old), len(new), len(rows), merged), '',
              '| 旧 work_slug | 新 work_slug | 旧題名 | 新題名 | 話数 (旧 → 新の作品) |',
              '|---|---|---|---|---|']
        new_by_slug = {w['work_slug']: w for w in new}
        for w, tgt in rows:
            t = new_by_slug[tgt[0]]
            L.append('| `%s` | %s | %s | %s | %d → %d |' % (
                w['work_slug'], ', '.join('`%s`' % s for s in tgt), md_cell(w['title']),
                md_cell(t['title']), w['episode_count'], t['episode_count']))
        L.append('')

    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    with open(out_path, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L).rstrip('\n') + '\n')
    print('cluster_review: %d 作品 (弱い根拠 %d / 強い根拠 %d) → %s'
          % (len(targets), len(weak), len(strong), os.path.relpath(out_path, root)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
