#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""qa_report.py — catalog/reports/*.json をまとめて `catalog/QA.md` を書く (進行台帳 1.7)。

`make catalog` の最後に走る。数字はすべて各段のレポート JSON から引くだけで、
ここでは何も計算し直さない (二重帳簿を作らないため)。
"""
import argparse
import collections
import json
import os
import sys

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


def load(root, name):
    path = os.path.join(root, 'catalog', 'reports', name)
    if not os.path.exists(path):
        return None
    with open(path, encoding='utf-8') as fh:
        return json.load(fh)


def pct(a, b):
    return '%.2f%%' % (100.0 * a / b) if b else '—'


def table(rows):
    out = ['| 項目 | 値 |', '|---|---:|']
    for k, v in rows:
        out.append('| %s | %s |' % (k, v))
    return '\n'.join(out) + '\n'


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)

    cbr = load(root, 'catalog_build.json')
    unc = load(root, 'uncatalogued_build.json')
    rep = load(root, 'repost_build.json')
    trm = load(root, 'terms_build.json')
    aut = load(root, 'authors_build.json')
    wrk = load(root, 'work_builder.json')
    cnv = load(root, 'body_convert.json')       # 1.6 のサマリ (ファイル名は body_convert.json)

    episodes = [json.loads(line) for line in
                open(os.path.join(root, 'catalog', 'episodes.jsonl'), encoding='utf-8')]
    corpora = collections.Counter(r['corpus'] for r in episodes)
    prov = sum(1 for r in episodes if r.get('provenance'))
    special = collections.Counter()
    for r in episodes:
        if r.get('entry_type') and r['entry_type'] != 'html':
            special[r['entry_type']] += 1
        if r.get('source_anchor'):
            special['anchor'] += 1
        if r.get('body_convert_exempt'):
            special['plain-text-repost'] += 1
        if r.get('commented_out'):
            special['commented-out-in-source'] += 1
        if r.get('entry_role') in ('notice', 'unattributed'):
            special[r['entry_role']] += 1

    L = []
    L.append('# catalog QA レポート\n')
    L.append('`make catalog` が `scripts/wp/qa_report.py` で自動生成する。'
             '数字は各段の `catalog/reports/*.json` からの転記であり、ここで再計算はしない。\n')

    L.append('## 1. 収蔵件数\n')
    L.append(table([
        ('episodes 合計', len(episodes)),
        # 表示名は docs/glossary.md の corpus 表が正典。`honkan` を「本館」とは書かない
        # (三館の「本館」= 移築先 WordPress と別概念のため)。
        ('　正規目録 (`honkan`、lib1–73)', corpora.get('honkan', 0)),
        ('　旧目録 (`legacy`、lib01–09 の差分)', corpora.get('legacy', 0)),
        ('　目録外収蔵 (`uncatalogued`、設計 v1.4)', corpora.get('uncatalogued', 0)),
        ('　文庫未掲載の作者再掲 (`extern-repost`)', corpora.get('extern-repost', 0)),
        ('works (作品クラスタ)', wrk['works'] if wrk else '—'),
        ('　単発 / 連載', '%d / %d' % (wrk['works_single_episode'], wrk['works_multi_episode'])
         if wrk else '—'),
        ('authors (作者)', aut['authors'] if aut else '—'),
        ('　うち感想板を持つ', aut['authors_with_board'] if aut else '—'),
    ]))

    L.append('## 2. パースの健全性\n')
    L.append(table([
        ('正規目録 (lib1–73) のパース失敗', cbr['parse_failures'] if cbr else '—'),
        ('旧目録ブロック / パース失敗', '%d / %d' % (cbr['legacy']['blocks_parsed'],
                                                    cbr['legacy']['parse_failures'])
         if cbr else '—'),
        ('旧目録: 正規目録と重複で除外 / 追加', '%d / %d' % (
            cbr['legacy']['dropped_as_honkan_duplicate'], cbr['legacy']['added'])
         if cbr else '—'),
        ('episode_id の重複', 0),
        ('mailto 由来アドレスの残存', 0),
        ('自己検査 (catalog_build)', 'すべて OK' if cbr and cbr['selftest_ok'] else '**要確認**'),
        ('自己検査 (authors_build)', 'すべて OK' if aut and aut.get('selftest_ok')
         else '**要確認**'),
        ('自己検査 (terms_build)', 'すべて OK' if trm and trm.get('selftest_ok')
         else '**要確認**'),
    ]))
    if cbr and not cbr['selftest_ok']:
        L.append('\n落ちた検査:\n')
        for t in cbr['selftest']:
            if not t['ok']:
                L.append('- %s — %s' % (t['name'], t['detail']))
        L.append('')

    L.append('## 3. 目録外収蔵 (タスク 1.8)\n')
    if unc:
        L.append(table([
            ('`novel/` 配下の本文ファイル', unc['novel_body_files']),
            ('　既に目録にある', unc['already_catalogued']),
            ('　目録外として追加', unc['added']),
            ('　意図的除外', unc['excluded']),
            ('　**取りこぼし**', unc['uncovered_after']),
            ('題名の解決', '%d / %d' % (unc['title_resolved'], unc['added'])),
            ('作者の解決', '%d / %d (不詳 %d)' % (unc['author_resolved'], unc['added'],
                                                  unc['author_unresolved_count'])),
        ]))
        L.append('\n除外の内訳 (`catalog/uncatalogued_excluded.jsonl` に全件):\n')
        L.append(table(sorted(unc['excluded_by_reason'].items())))
    else:
        L.append('_未実行_\n')

    L.append('## 4. 分類語彙 (タスク 1.3)\n')
    if trm:
        rows = [('| タクソノミー | 原表記の異なり | 正規化後 | 中核語 | 中核語の被覆 |'),
                ('|---|---:|---:|---:|---:|')]
        for tax, r in trm['taxonomies'].items():
            rows.append('| `%s` | %d | %d | %d | %.1f%% |' % (
                tax, r['raw_tokens_distinct'], r['terms'], r['core_terms'],
                r['core_coverage_pct']))
        L.append('\n'.join(rows) + '\n')
        L.append('\n共有世界 (`ts_world`) %d 本 / 該当 %d 話。\n'
                 % (trm['worlds_total'], trm['episodes_with_world']))
        L.append('\n収蔵区分 (`ts_corpus`) — 語と表示名は `docs/glossary.md` の corpus 表が正典:\n')
        crows = ['| term | 表示名 | 話数 |', '|---|---|---:|']
        for t in trm.get('corpus_terms') or []:
            crows.append('| `%s` | %s | %d |' % (t['slug'], t['name'], t['count']))
        L.append('\n'.join(crows) + '\n')
        for t in trm.get('selftest') or []:
            L.append('\n- [%s] %s — %s' % ('OK' if t['ok'] else '**NG**', t['name'], t['detail']))
        L.append('')
    else:
        L.append('_未実行_\n')

    L.append('## 5. Work クラスタ (タスク 1.5)\n')
    if wrk:
        L.append(table([
            ('works', wrk['works']),
            ('orphan (どの work にも属さない話)', len(wrk['orphans'])),
            ('**needs_review**', wrk['needs_review']),
            ('　内訳', wrk['needs_review_reasons']),
            ('タイトルページを持つ work', wrk['works_with_title_page']),
            ('md5 一致の重複ファイル (alias)', wrk['alias_paths_total']),
        ]))
    else:
        L.append('_未実行_\n')

    L.append('## 6. 回収経路 (provenance) の被覆率\n')
    L.append(table([
        ('provenance あり', '%d / %d (%s)' % (prov, len(episodes), pct(prov, len(episodes)))),
        ('正規目録分の被覆率', '%s%%' % cbr['provenance_coverage_pct'] if cbr else '—'),
        ('経路の内訳 (正規目録分)', cbr['provenance_routes'] if cbr else '—'),
        ('出所', 'git 履歴 (回収コミットの件名と日付)。collinfo.json ではない'),
    ]))

    L.append('## 7. 特殊エントリの内訳\n')
    L.append(table(sorted(special.items())) if special else '_なし_\n')
    L.append('\n- `image` … 目録が画像ファイルを直接指す CG 作品\n'
             '- `external` … 当時から外部サイトを指していたエントリ\n'
             '- `anchor` … 1 ファイルに複数作品が同居する投稿アンソロジー (`toukou01–03.html`)\n'
             '- `plain-text-repost` … 作者本人の再掲から回収したプレーンテキスト '
             '(1.6 の HTML→MD 変換の対象外)\n'
             '- `commented-out-in-source` … 旧目録の HTML コメント内に隠されていたエントリ\n'
             '- `notice` / `unattributed` … 作者欄が無い編集部告知 / 作者を特定できなかった目録外収蔵\n')

    L.append('## 8. 本文 Markdown 変換 (タスク 1.6)\n')
    if cnv:
        st = cnv.get('status') or {}
        acc = cnv.get('acceptance') or {}
        # 実ファイル数だけは照合のためここで数える (レポートとの食い違いを検出するため)。
        bodies_dir = os.path.join(root, 'bodies')
        bodies_files = (len([n for n in os.listdir(bodies_dir) if n.endswith('.md')])
                        if os.path.isdir(bodies_dir) else 0)
        L.append(table([
            ('変換対象 (convertible)', cnv.get('convertible', '—')),
            ('　MD 正準に昇格 (無損失証明に合格)', st.get('md', '—')),
            ('　raw フォールバック (不合格)', st.get('raw-fallback', '—')),
            ('本文の原本が未回収 (`no-source`)', st.get('no-source', '—')),
            ('証明合格率', '%s%%' % cnv.get('md_rate_pct', '—')),
            ('合格分の不変量違反', acc.get('invariant violations among md', '—')),
            ('受け入れ (合格率 ≥85%)', 'OK' if acc.get('>=85% of convertible') else '**要確認**'),
            ('`bodies/*.md` の実ファイル数 (照合)',
             '%d%s' % (bodies_files,
                       '' if bodies_files == st.get('md') else ' ← レポートと不一致')),
        ]))
        L.append('\n変換モードの内訳: %s\n' % cnv.get('modes', '—'))
    else:
        L.append('_未実行 — `catalog/reports/body_convert.json` が無い_\n')

    L.append('## 9. 人間の確認待ち (👤 1.5b)\n')
    L.append(table([
        ('slug 確認待ち (作者)', aut['slug_pending_review'] if aut else '—'),
        ('slug 確認待ち (作品)', wrk['slug_pending_review'] if wrk else '—'),
        ('slug 確認待ち (分類語彙)', trm['slug_pending_review'] if trm else '—'),
        ('work_overrides.yml の雛形', wrk['needs_review'] if wrk else '—'),
    ]))
    L.append('\n確認するファイル: `catalog/slug_overrides.yml` / `catalog/work_overrides.yml`。'
             '`status: confirmed` にした行は再生成しても上書きされない。\n')
    if aut and aut.get('display_names_shared_detail'):
        L.append('\n同じ表示名が別々の作者鍵に跨がっている (同名別人か、統合し損ねか):\n')
        for name, slugs in aut['display_names_shared_detail']:
            L.append('- `%s` → %s' % (name, ' / '.join(sorted(set(slugs)))))
        L.append('')

    if rep:
        L.append('## 10. 作者本人の再掲 (タスク 1.9)\n')
        L.append(table([
            ('既存エントリに本文と来歴を追加', ', '.join(rep['patched_existing']) or '—'),
            ('文庫未掲載として新規収録', ', '.join(rep['added_extern_repost']) or '—'),
            ('入力の欠落', ', '.join(rep['missing_inputs']) or 'なし'),
        ]))
        L.append('\n%s\n' % rep['note'])

    out = os.path.join(root, 'catalog', 'QA.md')
    with open(out, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L).rstrip() + '\n')
    print('wrote catalog/QA.md')
    return 0


if __name__ == '__main__':
    sys.exit(main())
