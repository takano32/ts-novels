#!/usr/bin/env python3
"""T9.3 — 無作為 100 話の原本併読 QA (独立検算)。

specs/S12.md の所管規則の例外にあたる「独立検算」なので、**本体の変換系とは別の書き手・別の実装**で
書いてある。`body_convert.py` / `payload_build.py` の正規化関数は意図的に一切使わない
(同じバグを同じway で再現してしまうと検算にならないため)。

やること:
  1. `catalog/episodes.jsonl` のうち **payload を持つ話** (= converted / converted-text /
     raw-fallback の 3,646 話) から `random.seed(20260830)` で 100 話を抽出
  2. 各話について
       (a) 本番ページを取得 (キャッシュバスター付き) し、本文領域のテキストを抽出
       (b) リポジトリの原本 (source_path。無ければ reposts/<episode_id>.txt) のテキストを抽出
  3. **空白を全部落とした文字列**同士を比較し、
     「本番の本文が原本テキストの連続した一部分になっているか」を見る。
     連続部分列であれば 欠落なし・順序保存・文字化けなし が同時に言える
  4. ずれた場合は difflib で差分区間を取り、原本側にしか無い区間を
     **設計どおりの除去 (nav_trimmed / chrome_removed)** かどうかで仕分ける

判定 (verdict):
  ok             本番本文が原本の連続部分列。設計どおりの除去のみ
  ok-trimmed     同上だが、除去区間が nav/chrome の記録と完全一致しない軽微な差 (定型ナビ等)
  ng-missing     本文とみられる区間が落ちている
  ng-extra       原本に無い文字列が本番に入っている (混入・文字化け)
  ng-order       共通部分はあるが順序が入れ替わっている
  error          取得・復号に失敗

使い方:
  python3 scripts/wp/qa_phase3_check.py --urls <ep_urls.tsv> --out <result.json> [--n 100]

抽出と判定 (TextExtractor・decode_bytes・preclean・visible_text・squeeze・canon_punct・page_body_html・
classify・covered_by_allowed・load_allowed・judge)、標本の抽出 (sample_episodes)、画像作品の判定
(is_image_source・judge_image)、原本側のテキストの選び方 (source_text)、集計 (summarize) は
scripts/wp/tslib/qa_phase3.py。このファイルは本番の取得 (fetch)・読み書きと表示だけ。
"""
from __future__ import annotations

import argparse
import json
import os
import random
import sys
import time
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tslib import paths, qa_phase3 as qa  # noqa: E402
from tslib.fileio import read_jsonl, write_json  # noqa: E402

SEED = 20260830
ROOT = paths.REPO_ROOT


# ----------------------------------------------------------------- main
def fetch(url: str, tries: int = 3) -> str:
    last = None
    for i in range(tries):
        try:
            u = f"{url}?_cb={random.randint(1, 10**9)}"
            req = urllib.request.Request(u, headers={"User-Agent": "ts-novels-qa/1.0"})
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.read().decode("utf-8", "replace")
        except Exception as e:  # noqa: BLE001
            last = e
            time.sleep(1 + i)
    raise RuntimeError(f"fetch failed: {last}")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--urls", required=True, help="episode_id<TAB>url<TAB>status の TSV")
    ap.add_argument("--out", required=True)
    ap.add_argument("--n", type=int, default=100)
    args = ap.parse_args()

    urls, statuses = {}, {}
    for line in open(args.urls, encoding="utf-8"):
        parts = line.rstrip("\n").split("\t")
        if len(parts) >= 3:
            urls[parts[0]], statuses[parts[0]] = parts[1], parts[2]

    episodes = read_jsonl(paths.episodes_path(ROOT))
    report = {r["episode_id"]: r for r in read_jsonl(paths.convert_report_path(ROOT))}

    payloads = paths.payloads_dir(ROOT)
    eligible = {e["episode_id"]: e for e in episodes
                if os.path.exists(os.path.join(payloads, e["episode_id"] + ".html"))}
    sample = [eligible[eid] for eid in qa.sample_episodes(list(eligible), args.n, SEED)]

    results = []
    for i, ep in enumerate(sample, 1):
        eid = ep["episode_id"]
        rec = {"episode_id": eid, "status": statuses.get(eid), "url": urls.get(eid),
               "source_path": ep.get("source_path")}
        try:
            src = os.path.join(ROOT, ep.get("source_path") or "")
            if not (ep.get("source_path") and os.path.isfile(src)):
                alt = os.path.join(ROOT, "reposts", eid + ".txt")
                if os.path.isfile(alt):
                    src = alt
                else:
                    rec.update(verdict="error", issues=[{"kind": "no-source-file"}])
                    results.append(rec)
                    continue
            page = fetch(urls[eid])
            # 画像作品は、ページが原本を指す img を持つかで判定する
            if qa.is_image_source(ep.get("source_path")):
                verdict, issues = qa.judge_image(page, ep["source_path"])
                rec.update(verdict=verdict, issues=issues)
                results.append(rec)
                print(f"  [{i:3d}/{args.n}] {rec['verdict']:12s} {eid[:60]}", flush=True)
                continue

            raw = open(src, "rb").read()
            text = qa.decode_bytes(raw)
            orig = qa.squeeze(qa.source_text(text, src))

            bh = qa.page_body_html(page)
            if bh is None:
                rec.update(verdict="error", issues=[{"kind": "no-entry-content"}])
                results.append(rec)
                continue
            body = qa.squeeze(qa.visible_text(bh))

            allowed = qa.load_allowed(eid, report)
            verdict, issues = qa.judge(orig, body, allowed)
            rec.update(verdict=verdict, issues=issues,
                       orig_chars=len(orig), body_chars=len(body))
        except Exception as e:  # noqa: BLE001
            rec.update(verdict="error", issues=[{"kind": "exception", "msg": str(e)[:200]}])
        results.append(rec)
        print(f"  [{i:3d}/{args.n}] {rec['verdict']:12s} {eid[:60]}", flush=True)

    summary = qa.summarize(results, SEED)
    write_json(args.out, {"summary": summary, "results": results}, indent=1, newline=False)
    print(json.dumps(summary, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())
