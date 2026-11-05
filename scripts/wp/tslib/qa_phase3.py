# -*- coding: utf-8 -*-
"""qa_phase3.py — 無作為 100 話の原本併読 QA (T9.3) の抽出と判定 (独立検算)。

**独立検算の決まり (specs/S12.md)**: このモジュールは本体の変換系 (tslib/bodies.py・
tslib/payloads.py・tslib/htmltext.py) を import しない。同じバグを同じ形で再現してしまうと
検算にならないため、テキストの抽出・正規化は標準ライブラリの html.parser で別に書いてある。
共有してよいのは読み書き (tslib/fileio.py・tslib/paths.py・tslib/gitinfo.py) だけ。

本番の取得 (fetch) と読み書きは scripts/wp/qa_phase3_check.py に残す。ここは文字列を受けて
判定を返すだけ (標本の抽出・画像作品の判定・集計も)。判定 (verdict) の意味は
scripts/wp/qa_phase3_check.py の先頭に書いてある。
"""
from __future__ import annotations

import difflib
import json
import random
import re
import unicodedata
from html.parser import HTMLParser

from tslib.gitinfo import IMAGE_EXT_RE


# ----------------------------------------------------------------- テキスト抽出
class TextExtractor(HTMLParser):
    """標準ライブラリの html.parser でテキストだけを拾う。

    本体側 (html5lib / 自前の正規表現) とは別系統のパーサを使うのが狙い。
    script/style/rt(ルビの読み) の扱いを選べる。
    """

    SKIP = {"script", "style", "title"}

    def __init__(self, skip_rt: bool = False):
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
    """ブラウザ等価の前処理。原本には次の 2 つの地雷がある (specs/S11.md の「目録パースの地雷」):

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


def judge(orig: str, body: str, allowed: list[str]) -> tuple[str, list[dict]]:
    """classify に、WP の表示フィルタの字面差だけなら ok-texturize とする再判定を足したもの。"""
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
    return verdict, issues


# ----------------------------------------------------------------- 標本・画像作品・集計
def sample_episodes(ids: list[str], n: int, seed: int) -> list[str]:
    """episode_id を整列して、seed で n 話を無作為に抜く (random.seed(seed) の後の random.sample と同じ並び)。
    大域の乱数の状態は変えない。"""
    return random.Random(seed).sample(sorted(ids), n)


def is_image_source(path: str | None) -> bool:
    """画像作品 (原本そのものが .jpg/.gif 等)。本文が figure 1 枚なのでテキスト比較の対象にならない。"""
    return bool(IMAGE_EXT_RE.search(path or ""))


def judge_image(page: str, source_path: str) -> tuple[str, list[dict]]:
    """画像作品の判定: ページが原本を指す img (/assets/annex-img/<source_path>) を持つか。"""
    want = "/assets/annex-img/" + source_path
    ok = want in page
    return ("ok-image" if ok else "ng-image"), [{"kind": "image-entry", "expect": want, "found": ok}]


def source_text(text: str, path: str) -> str:
    """原本側のテキスト。.txt なら生テキストのまま、それ以外は HTML として可視テキストを取る (squeeze は呼び出し側)。"""
    return text if path.endswith(".txt") else visible_text(text)


def summarize(results: list[dict], seed: int) -> dict:
    """verdict ごとの件数と、ng-* と error の割合 (%)。鍵の順は seed・n・counts・problem_rate_pct。"""
    counts: dict[str, int] = {}
    for r in results:
        counts[r["verdict"]] = counts.get(r["verdict"], 0) + 1
    ng = sum(v for k, v in counts.items() if k.startswith("ng") or k == "error")
    return {"seed": seed, "n": len(results), "counts": counts,
            "problem_rate_pct": round(100.0 * ng / max(1, len(results)), 2)}
