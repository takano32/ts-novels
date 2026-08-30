#!/usr/bin/env python3
"""タスク 3.3 — 無作為 100 話の原本併読 QA (独立検算)。

台帳の所管規則の例外にあたる「独立検算」なので、**本体の変換系とは別の書き手・別の実装**で
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
"""
from __future__ import annotations

import argparse
import difflib
import html
import json
import os
import random
import re
import sys
import time
import unicodedata
import urllib.request
from html.parser import HTMLParser

SEED = 20260830
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


# ----------------------------------------------------------------- テキスト抽出
class TextExtractor(HTMLParser):
    """標準ライブラリの html.parser でテキストだけを拾う。

    本体側 (html5lib / 自前の正規表現) とは別系統のパーサを使うのが狙い。
    script/style/rt(ルビの読み) の扱いを選べる。
    """

    SKIP = {"script", "style", "title"}

    def __init__(self, skip_rt: bool = False, only_id: str | None = None):
        super().__init__(convert_charrefs=True)
        self.buf: list[str] = []
        self._skip_depth = 0
        self.skip_rt = skip_rt

    def handle_starttag(self, tag, attrs):
        if tag in self.SKIP or (self.skip_rt and tag in ("rt", "rp")):
            self._skip_depth += 1

    def handle_endtag(self, tag):
        if tag in self.SKIP or (self.skip_rt and tag in ("rt", "rp")):
            self._skip_depth = max(0, self._skip_depth - 1)

    def handle_data(self, data):
        if self._skip_depth == 0:
            self.buf.append(data)

    def text(self) -> str:
        return "".join(self.buf)


def decode_bytes(raw: bytes) -> str:
    """meta charset を見てから、日本語サイトで実際に使われている順に試す。"""
    head = raw[:4096].decode("ascii", "replace").lower()
    m = re.search(r'charset\s*=\s*["\']?\s*([\w-]+)', head)
    order = []
    if m:
        enc = m.group(1)
        alias = {"shift_jis": "cp932", "shift-jis": "cp932", "sjis": "cp932",
                 "x-sjis": "cp932", "euc-jp": "euc_jp", "utf-8": "utf-8", "utf8": "utf-8"}
        order.append(alias.get(enc, enc))
    order += ["utf-8", "cp932", "euc_jp", "iso-2022-jp"]
    for enc in order:
        try:
            return raw.decode(enc)
        except (UnicodeDecodeError, LookupError):
            continue
    return raw.decode("utf-8", "replace")


def preclean(src: str) -> str:
    """ブラウザ等価の前処理。原本には次の 2 つの地雷がある (台帳の「目録パースの地雷」):

    - bogus comment `<!　…!!` (没稿隠し) — ブラウザは `>` までをコメント扱いで捨てる
    - 地の文の裸の `<` (例 `ち<ゃんと`) — タグとして解釈されない

    ここは本体と同じ「現象」に対処するが、実装は独立に書いている。
    """
    # 正規のコメントを先に落とす
    src = re.sub(r"<!--.*?-->", "", src, flags=re.S)
    # bogus comment: <! のあとが -- でないもの
    src = re.sub(r"<!(?!--)[^>]*>", "", src, flags=re.S)
    # 裸の `<` を実体参照に逃がす。
    # HTML5 のトークナイザの規則そのまま: `<` の直後が ASCII 英字 (または `/`+英字) の
    # ときだけタグの開始で、それ以外は文字としての `<`。
    # タグ名が不正でも (`<FONTCOLOR="#FFD700">` や Word の `<o:p>`) ブラウザはタグとして
    # 読み捨てるので、ここで文字に逃がしてはいけない。
    src = re.sub(r"<(?!/?[A-Za-z])", "&lt;", src)
    return src


def visible_text(src_html: str, skip_rt: bool = False) -> str:
    p = TextExtractor(skip_rt=skip_rt)
    try:
        p.feed(preclean(src_html))
        p.close()
    except Exception:
        pass
    return p.text()


def squeeze(s: str) -> str:
    """比較用の正規化。**空白と制御文字を全部落として NFC** にするだけ。

    本体の無損失証明も似た発想だが、こちらは実装を共有していない。
    全角空白・改行・タブ・NBSP をまとめて除去する。
    """
    s = unicodedata.normalize("NFC", s)
    return re.sub(r"[\s 　​﻿]+", "", s)


#: WordPress が **表示のときだけ** 掛ける整形フィルタ (`the_content` の
#: `wptexturize` / `convert_smilies` / `capital_P_dangit`) が変える文字。
#: DB の中身は原本のままなので「本文の欠落」ではないが、
#: **読者が見る字面は原本と変わる**ので独立に数える。
SMILEY = "🙂🙁😀😉😐😮😎😡😢😆😛😯😳❤"


def canon_punct(s: str) -> str:
    """ダッシュ・三点リーダ・引用符の *見た目の違い* を潰した比較用の形。

    原本側と本番側の**両方**に同じ変換を掛けるので、この正規化で本文が消えることはない。
    """
    s = (s.replace("…", "...").replace("—", "-").replace("–", "-")
           .replace("“", '"').replace("”", '"').replace("„", '"')
           .replace("‘", "'").replace("’", "'")
           .replace("′", "'").replace("″", '"'))
    s = re.sub(r"-{1,3}", "-", s)
    s = re.sub(r"\.{2,3}", "...", s)
    for ch in SMILEY:
        s = s.replace(ch, "")
    return s


ENTRY_RE = re.compile(
    r'<div[^>]*class="[^"]*wp-block-post-content[^"]*"[^>]*>(.*?)'
    r'(?:<footer|<div[^>]*class="[^"]*wp-block-post-navigation|</main>)',
    re.S | re.I)


def page_body_html(page: str) -> str | None:
    m = ENTRY_RE.search(page)
    return m.group(1) if m else None


# ----------------------------------------------------------------- 比較
def classify(orig: str, body: str, allowed: list[str]) -> tuple[str, list[dict]]:
    """orig(原本の可視テキスト) と body(本番本文) を突き合わせる。"""
    if not body:
        return "ng-missing", [{"kind": "empty-body"}]
    idx = orig.find(body)
    if idx >= 0:
        # 連続部分列 = 欠落・順序破壊・文字化けなし。落ちた前後が設計どおりか見る
        head, tail = orig[:idx], orig[idx + len(body):]
        issues = []
        for where, seg in (("head", head), ("tail", tail)):
            if seg and not covered_by_allowed(seg, allowed):
                issues.append({"kind": "trimmed", "where": where,
                               "len": len(seg), "sample": seg[:120]})
        return ("ok" if not issues else "ok-trimmed"), issues

    sm = difflib.SequenceMatcher(None, orig, body, autojunk=False)
    ops = sm.get_opcodes()
    missing, extra = [], []
    for tag, i1, i2, j1, j2 in ops:
        if tag in ("delete", "replace") and (i2 - i1) > 0:
            missing.append({"len": i2 - i1, "sample": orig[i1:i2][:120]})
        if tag in ("insert", "replace") and (j2 - j1) > 0:
            extra.append({"len": j2 - j1, "sample": body[j1:j2][:120]})
    real_missing = [m for m in missing
                    if m["len"] >= 12 and not covered_by_allowed(m["sample"], allowed)]
    real_extra = [e for e in extra if e["len"] >= 12]
    matched = sum(b.size for b in sm.get_matching_blocks())
    if real_extra:
        return "ng-extra", [{"kind": "extra", **e} for e in real_extra[:5]]
    if real_missing:
        return "ng-missing", [{"kind": "missing", **m} for m in real_missing[:5]]
    if matched < len(body) * 0.98:
        return "ng-order", [{"kind": "low-match", "matched": matched, "body": len(body)}]
    return "ok-trimmed", [{"kind": "minor", "missing": len(missing), "extra": len(extra)}]


def covered_by_allowed(seg: str, allowed: list[str]) -> bool:
    """落ちた区間が「設計どおりの除去」で説明できるか。

    nav_trimmed / chrome_removed に記録された文字列を順に取り除いて、
    残りが定型ナビ・カウンタ・感想リンク等だけになれば許容とみなす。
    """
    rest = seg
    for a in allowed:
        if a:
            rest = rest.replace(a, "")
    rest = re.sub(CHROME_PAT, "", rest)
    return len(rest) < 12


CHROME_PAT = ("|".join(re.escape(x) for x in [
    "戻る", "もどる", "感想はこちらに", "感想を書く", "感想", "ご意見ご感想",
    "少年少女文庫", "トップへ", "トップページ", "ホームへ", "HOMEへ", "Home",
    "次へ", "前へ", "つづく", "目次", "作品一覧", "短編小説のページへ",
    "この作品の感想", "掲示板", "メール", "index", "back", "next",
    "□", "■", "▲", "▼", "★", "☆", "・", "-", "―", "‥", "…",
]))


# ----------------------------------------------------------------- main
def load_allowed(episode_id: str, report: dict) -> list[str]:
    out = []
    r = report.get(episode_id) or {}
    for key in ("nav_trimmed", "chrome_removed"):
        v = r.get(key)
        if isinstance(v, str):
            try:
                v = json.loads(v.replace("'", '"'))
            except Exception:
                v = []
        for item in (v or []):
            if isinstance(item, dict):
                out.append(squeeze(str(item.get("text", ""))))
            else:
                out.append(squeeze(str(item)))
    return [x for x in out if x]


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

    episodes = [json.loads(l) for l in
                open(os.path.join(ROOT, "catalog/episodes.jsonl"), encoding="utf-8")]
    report = {}
    for l in open(os.path.join(ROOT, "catalog/convert_report.jsonl"), encoding="utf-8"):
        r = json.loads(l)
        report[r["episode_id"]] = r

    eligible = [e for e in episodes
                if os.path.exists(os.path.join(ROOT, "payloads", e["episode_id"] + ".html"))]
    eligible.sort(key=lambda e: e["episode_id"])
    random.seed(SEED)
    sample = random.sample(eligible, args.n)

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
            # 画像作品 (原本そのものが .jpg/.gif 等) は本文が figure 1 枚なので
            # テキスト比較の対象にならない。ページが原本を指す img を持つかで判定する。
            if re.search(r"\.(jpe?g|gif|png|bmp)$", ep.get("source_path") or "", re.I):
                want = "/assets/annex-img/" + ep["source_path"]
                ok = want in page
                rec.update(verdict="ok-image" if ok else "ng-image",
                           issues=[{"kind": "image-entry", "expect": want, "found": ok}])
                results.append(rec)
                print(f"  [{i:3d}/{args.n}] {rec['verdict']:12s} {eid[:60]}", flush=True)
                continue

            raw = open(src, "rb").read()
            text = decode_bytes(raw)
            orig = squeeze(text if src.endswith(".txt") else visible_text(text))

            bh = page_body_html(page)
            if bh is None:
                rec.update(verdict="error", issues=[{"kind": "no-entry-content"}])
                results.append(rec)
                continue
            body = squeeze(visible_text(bh))

            allowed = load_allowed(eid, report)
            verdict, issues = classify(orig, body, allowed)
            # 厳密比較で落ちても、WP の表示フィルタ (wptexturize / convert_smilies) の
            # 字面差だけなら「データは無損失」。両側に同じ正規化を掛けて再判定する。
            if verdict.startswith("ng") or verdict == "ok-trimmed":
                v2, i2 = classify(canon_punct(orig), canon_punct(body),
                                  [canon_punct(a) for a in allowed])
                if v2 == "ok" and verdict != "ok":
                    verdict, issues = "ok-texturize", [
                        {"kind": "wp-display-filter",
                         "note": "字面のみ WP の the_content フィルタが変更 (DB は原本どおり)"}]
                elif v2 != verdict:
                    verdict, issues = v2, i2
            rec.update(verdict=verdict, issues=issues,
                       orig_chars=len(orig), body_chars=len(body))
        except Exception as e:  # noqa: BLE001
            rec.update(verdict="error", issues=[{"kind": "exception", "msg": str(e)[:200]}])
        results.append(rec)
        print(f"  [{i:3d}/{args.n}] {rec['verdict']:12s} {eid[:60]}", flush=True)

    counts: dict[str, int] = {}
    for r in results:
        counts[r["verdict"]] = counts.get(r["verdict"], 0) + 1
    ng = sum(v for k, v in counts.items() if k.startswith("ng") or k == "error")
    summary = {"seed": SEED, "n": len(results), "counts": counts,
               "problem_rate_pct": round(100.0 * ng / max(1, len(results)), 2)}
    json.dump({"summary": summary, "results": results},
              open(args.out, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    print(json.dumps(summary, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())
