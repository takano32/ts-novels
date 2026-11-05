# -*- coding: utf-8 -*-
"""cluster_review.py — 作品クラスタのレビュー資料の中身 (T24.3、仕様は specs/S149.md)。

抽出の規則・芯ごとの一次案・文書の Markdown (render_report と、--since の節の render_slug_section) と、
slug が変わった作品の突き合わせ。戻り値は行の列で、つないで書くのは入口。
ファイルと git の読み書きは scripts/wp/cluster_review.py。題名の芯は tslib/works.py の
trim_title と common_prefix を使う。
"""
import collections
import json
import os
import unicodedata

from tslib import works as wk

WEAK_ONLY = {'filename-stem', 'title-prefix'}


def nfkc_lower(s):
    return unicodedata.normalize('NFKC', s or '').lower()


def is_target(w):
    if w['episode_count'] < 2:
        return False
    cores = {wk.trim_title(e['title']) for e in w['episodes']}
    if len(cores) < 2:
        return False
    return len(wk.common_prefix([nfkc_lower(c) for c in cores])) < 2


def is_weak(w):
    return set(w['evidence']) <= WEAK_ONLY


def core_families(w):
    """芯ごとの組。共通接頭辞 2 文字以上でつながる芯を 1 組にする (掲載順に貪欲)。
    戻り値は [(代表の芯, [episode, …]), …] で、元の作品に残す組が先頭。"""
    fams = []                                   # [{'keys': [芯の nfkc_lower], 'eps': [...], 'cores': Counter}]
    for i, e in enumerate(w['episodes']):
        core = wk.trim_title(e['title'])
        key = nfkc_lower(core)
        for f in fams:
            if any(len(wk.common_prefix([key, k])) >= 2 for k in f['keys']):
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
        strong = sorted(set(w['evidence']) & wk.STRONG)
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


def slug_rows(old, new):
    """REV の works (old) と今の works (new) を話の所属で突き合わせる。
    戻り値は work_slug が変わった作品の [(旧 work, 新 work_slug の列)] (旧 work_slug 順)。"""
    new_of = {e['episode_id']: w for w in new for e in w['episodes']}
    rows = []
    for w in old:
        tgt = {new_of[e['episode_id']]['work_slug'] for e in w['episodes'] if e['episode_id'] in new_of}
        if tgt == {w['work_slug']}:
            continue
        rows.append((w, sorted(tgt)))
    rows.sort(key=lambda x: x[0]['work_slug'])
    return rows


def split_targets(works):
    """対象の作品を (弱い根拠だけ, 強い根拠あり) に分ける。どちらも work_slug 順。"""
    targets = [w for w in works if is_target(w)]
    weak = sorted([w for w in targets if is_weak(w)], key=lambda w: w['work_slug'])
    strong = sorted([w for w in targets if not is_weak(w)], key=lambda w: w['work_slug'])
    return weak, strong


def render_report(works, episodes, month):
    """文書の本体 (見出し・説明・弱い根拠と強い根拠の 2 つの部分) の行の列。番号は 2 つの部分を通しで振る。"""
    weak, strong = split_targets(works)
    L = ['# 作品クラスタのレビュー (%s)' % month, '',
         '`scripts/wp/cluster_review.py` の出力 (仕様は specs/S149.md、裁定の書き方は specs/S150.md)。'
         '手で直さず、作り直すときはスクリプトを走らせる。裁定はこのファイルの写しか返信でもらい、'
         '`catalog/work_overrides.yml` に転記する (T24.5)。', '',
         '- 対象: 連載 (2 話以上) のうち、話の題名の芯が 2 種以上あり、芯どうしの共通接頭辞が 2 文字未満の作品',
         '- 件数: **%d 作品** (根拠が規則 5/6 だけ = 弱い根拠 %d を先頭に、強い根拠のあるもの %d を後に)'
         % (len(weak) + len(strong), len(weak), len(strong)),
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
    return L


def render_slug_section(old, new, rows, rev):
    """--since の節「slug が変わった作品」の行の列。rows は slug_rows(old, new)、rev は比べた版の短い名前。
    「束ね直された」は、移った先が 1 作品で、その作品の話数が旧の作品と違うもの。"""
    merged = sum(1 for w, t in rows if len(t) == 1 and
                 sum(1 for x in new if x['work_slug'] == t[0] and x['episode_count'] != w['episode_count']))
    L = ['## slug が変わった作品 (%s との比較)' % rev, '',
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
            w['work_slug'], ', '.join('`%s`' % s for s in tgt), md_cell(w['title']),
            md_cell(t['title']), w['episode_count'], t['episode_count']))
    L.append('')
    return L
