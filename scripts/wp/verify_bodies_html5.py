#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""bodies/*.md の無損失性を html5lib で検算する (body_convert.py の第二実装による突き合わせ)

なぜ要るか:
  body_convert.py は正規表現で HTML を解釈し、無損失証明も同じ正規表現で書かれている。
  「両側を同じ思い込みで間違えている」可能性が残る。そこで **html5lib**
  (HTML5 のパース仕様どおりに解釈する = 当時のブラウザと同じ木を作る) を
  完全に独立した第二実装として使い、変換結果を検算する。書き直しではなく差分検出。

比較のしかた:
  原本 HTML を html5lib でパース → <body> 配下の可視テキスト (script/style/comment 除外) →
  「空白と山括弧を除き NFC 正規化」した文字列 ORIG を作る。
  bodies/<id>.md からも同じ規則で CONV を作る。

  ただし body_convert.py は無損失証明を通したあとで
    (a) クローム除去 strip_chrome() — 冒頭/末尾の定型ナビ帯 (report の chrome_removed)
    (b) 定型ナビ行 trim_nav_lines() — (report の nav_trimmed)
  を落としている。落とした断片は report に**切り詰めて**記録されており、
  完全な復元はできない。そこで足し戻すのではなく **CONV が ORIG の連続部分文字列か**
  を判定する。これは (a)(b) が冒頭と末尾からしか削らないことから等価であり、
  かつ「本文の内部で 1 文字でも失われた/増えた」を必ず捕まえる。
  加えて、はみ出した前置き prefix と後置き suffix を取り出して、
  それが本当にナビ定型だけか (=地の文を巻き込んでいないか) を検査する。

出力:
  catalog/reports/verify_html5.json   件数サマリ + 不一致の全一覧

使い方:
  .venv/bin/python scripts/wp/verify_bodies_html5.py --limit 300   # まず 300 話
  .venv/bin/python scripts/wp/verify_bodies_html5.py               # 全数
  .venv/bin/python scripts/wp/verify_bodies_html5.py --id <episode_id>
"""
import os, sys, json, argparse, collections, warnings
from concurrent.futures import ProcessPoolExecutor

warnings.filterwarnings('ignore')

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import verify_html5 as vh  # noqa: E402
from tslib.fileio import read_text_guess  # noqa: E402

if vh.BeautifulSoup is None:
    sys.exit('beautifulsoup4 / html5lib が要る:  .venv/bin/pip install beautifulsoup4 html5lib\n'
             '(make venv でも入る)')

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
EPISODES = os.path.join(ROOT, 'catalog', 'episodes.jsonl')
CONVERT_REPORT = os.path.join(ROOT, 'catalog', 'convert_report.jsonl')
BODIES = os.path.join(ROOT, 'bodies')
OUT = os.path.join(ROOT, 'catalog', 'reports', 'verify_html5.json')

# 原本が HTML ではない話は突き合わせの対象外 (比較すべき原本 HTML が無い)
SKIP_MODES = {'plain-text', 'image-work'}

# 検算の規則 (normalize・html5_text・md_text・looks_like_chrome・verify) は scripts/wp/tslib/verify_html5.py


# ---------------------------------------------------------------- 1 話の食い違い

def explain(job, variant='strict'):
    """1 話の食い違いを difflib で全部並べる (body_convert を直す人向け)"""
    import difflib
    eid, src, mode, anchor = job[2], job[3], job[4], job[5]
    raw, _ = read_text_guess(os.path.join(ROOT, src))
    orig = vh.normalize(vh.html5_text(raw, **vh.VARIANT_KW.get(variant, {})))
    conv_text, markers = vh.md_text(open(os.path.join(BODIES, eid + '.md'), encoding='utf-8').read())
    conv = vh.normalize(conv_text)
    print(f'{eid}  mode={mode} anchor={anchor} variant={variant}')
    print(f'  原本(html5lib)={len(orig)} 変換(md)={len(conv)}  md 記号={dict(markers)}')
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, orig, conv,
                                                       autojunk=False).get_opcodes():
        if tag == 'equal':
            continue
        print(f'  {tag:7s} 原本-{i2-i1} 変換+{j2-j1}')
        if i2 > i1:
            print('    原本のみ:', repr(orig[i1:i2][:200]))
        if j2 > j1:
            print('    変換のみ:', repr(conv[j1:j2][:200]))


# ---------------------------------------------------------------- 実行

def load_jobs(args):
    reports = {}
    for l in open(CONVERT_REPORT, encoding='utf-8'):
        d = json.loads(l)
        reports[d['episode_id']] = d
    jobs = []
    for l in open(EPISODES, encoding='utf-8'):
        ep = json.loads(l)
        eid = ep['episode_id']
        d = reports.get(eid)
        if not d or d.get('status') != 'md':
            continue
        mode = d.get('mode')
        if mode in SKIP_MODES:
            continue
        src = ep.get('source_path') or ''
        if not src or src.lower().endswith('.txt') or src.startswith(('http://', 'https://')):
            continue
        if args.id and eid != args.id:
            continue
        jobs.append((ROOT, BODIES, eid, src, mode, ep.get('source_anchor'),
                     [c.get('text', '') for c in (d.get('chrome_removed') or [])],
                     [c.get('text', '') for c in (d.get('nav_trimmed') or [])]))
    if args.limit:
        jobs = jobs[:args.limit]
    return jobs


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int, help='先頭 N 話だけ')
    ap.add_argument('--id', help='1 話だけ (デバッグ用)')
    ap.add_argument('--jobs', type=int, default=max(1, (os.cpu_count() or 4) - 1))
    ap.add_argument('--out', default=OUT)
    ap.add_argument('--explain', metavar='EPISODE_ID',
                    help='その 1 話の食い違いを全部並べる (レポートは書かない)')
    ap.add_argument('--variant', default='strict', choices=sorted(vh.VARIANT_KW),
                    help='--explain で使う原本側の解釈 (既定 strict)')
    args = ap.parse_args()

    if args.explain:
        args.id = args.explain
        jobs = load_jobs(args)
        if not jobs:
            sys.exit(f'{args.explain} は検算対象ではない (bodies/*.md が無いか対象外の層)')
        explain(jobs[0], args.variant)
        return

    jobs = load_jobs(args)
    print(f'検算対象 {len(jobs)} 話 (jobs={args.jobs})', file=sys.stderr)

    rows = []
    if args.jobs <= 1 or len(jobs) < 8:
        for j in jobs:
            rows.append(vh.verify(j))
    else:
        with ProcessPoolExecutor(max_workers=args.jobs) as ex:
            for n, r in enumerate(ex.map(vh.verify, jobs, chunksize=8), 1):
                rows.append(r)
                if n % 250 == 0:
                    print(f'  {n}/{len(jobs)}', file=sys.stderr)

    counts = collections.Counter(r['result'] for r in rows)
    ok = ('match', 'match-empty', 'match-anchor')
    bad = [r for r in rows if r['result'] not in ok]
    bad.sort(key=lambda r: (r['result'], r['episode_id']))
    summary = {
        'checked': len(rows),
        'result': dict(counts),
        'match_rate_pct': round(sum(counts[k] for k in ok) / len(rows) * 100, 3) if rows else 0.0,
        'variant': dict(collections.Counter(r.get('variant') for r in rows if r.get('variant'))),
        'dropped_head_max': max([r.get('dropped_head', 0) for r in rows] or [0]),
        'dropped_tail_max': max([r.get('dropped_tail', 0) for r in rows] or [0]),
        # 原本で &lt;…&gt; だった地の文が md では生の '<…>' になっている話。
        # 比較は通るが WordPress で描画するとタグとして消える
        'taglike_text_episodes': sorted(
            (r['episode_id'], r['md_markers']['taglike_text']) for r in rows
            if r.get('md_markers', {}).get('taglike_text')),
        'findings': bad,
    }
    summary['taglike_text_count'] = len(summary['taglike_text_episodes'])
    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, 'w', encoding='utf-8') as f:
        json.dump(summary, f, ensure_ascii=False, indent=1)
    print(json.dumps({k: v for k, v in summary.items()
                      if k not in ('findings', 'taglike_text_episodes')},
                     ensure_ascii=False, indent=1))
    for r in bad[:15]:
        print(' ', r['result'], r['episode_id'], '|',
              str(r.get('reason') or r.get('orig_ctx') or r.get('head_text')
                  or r.get('tail_text') or '')[:100])


if __name__ == '__main__':
    main()
