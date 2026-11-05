#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""qa_phase3_rejudge.py — 原本併読 QA (T9.3) の判定を、旧版の関数と新版の tslib/qa_phase3.py で
同じ入力から出し直して比べる (T45.2.5.5)。本番には接続しない。

  .venv/bin/python scripts/wp/parity/qa_phase3_rejudge.py ROOT BASE_TREE
    ROOT       比較先のツリー (catalog/reports/qa_phase3_check.json・catalog/convert_report.jsonl・
               payloads/・原本を読む)
    BASE_TREE  基準のツリー (tree.sh で展開したもの。その scripts/wp/qa_phase3_check.py の関数を使う)

既存のレポートの results の各話について、原本 (source_path、無ければ reposts/<id>.txt) を原本側に、
本番ページの代わりに payloads/<id>.html (投入した本文) を本文側にして、旧版と新版で判定する。
画像の話と payload の無い話は飛ばす。最後の行に件数と、旧版と新版で判定が違った数 (diff) を出す。
"""
import importlib.util, json, os, re, sys
W, BASE = sys.argv[1], sys.argv[2]
sys.path.insert(0, os.path.join(W, 'scripts', 'wp'))
from tslib import qa_phase3 as new
spec = importlib.util.spec_from_file_location('old', os.path.join(BASE, 'scripts', 'wp', 'qa_phase3_check.py'))
old = importlib.util.module_from_spec(spec); spec.loader.exec_module(old)

def old_judge(orig, body, allowed):
    verdict, issues = old.classify(orig, body, allowed)
    if verdict.startswith("ng") or verdict == "ok-trimmed":
        v2, i2 = old.classify(old.canon_punct(orig), old.canon_punct(body), [old.canon_punct(a) for a in allowed])
        if v2 == "ok" and verdict != "ok":
            verdict, issues = "ok-texturize", [{"kind": "wp-display-filter", "note": "字面のみ WP の the_content フィルタが変更 (DB は原本どおり)"}]
        elif v2 != verdict:
            verdict, issues = v2, i2
    return verdict, issues

rep = json.load(open(os.path.join(W, 'catalog/reports/qa_phase3_check.json'), encoding='utf-8'))
report = {}
for l in open(os.path.join(W, 'catalog/convert_report.jsonl'), encoding='utf-8'):
    r = json.loads(l); report[r['episode_id']] = r
same = diff = skipped = stored_same = 0
for r in rep['results']:
    eid, sp = r['episode_id'], r.get('source_path') or ''
    src = os.path.join(W, sp)
    if not (sp and os.path.isfile(src)):
        alt = os.path.join(W, 'reposts', eid + '.txt')
        if not os.path.isfile(alt):
            skipped += 1; continue
        src = alt
    pay = os.path.join(W, 'payloads', eid + '.html')
    if re.search(r"\.(jpe?g|gif|png|bmp)$", sp, re.I) or not os.path.isfile(pay):
        skipped += 1; continue
    raw = open(src, 'rb').read()
    page = open(pay, encoding='utf-8').read()
    res = []
    for m, judge in ((old, old_judge), (new, new.judge)):
        text = m.decode_bytes(raw)
        orig = m.squeeze(text if src.endswith('.txt') else m.visible_text(text))
        body = m.squeeze(m.visible_text(page))
        res.append(judge(orig, body, m.load_allowed(eid, report)))
    if res[0] == res[1]: same += 1
    else: diff += 1; print('DIFF', eid, res[0][0], res[1][0])
    if res[1][0] == r['verdict']: stored_same += 1
print(f'results={len(rep["results"])} judged={same + diff} same={same} diff={diff} skipped={skipped} stored_verdict_equal={stored_same}')
