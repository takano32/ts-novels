#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""slugs.py — ローマ字 slug の候補生成と `catalog/slug_overrides.yml` の往復。

slug は**恒久 URL** になる (`/genre/gakuen/` `/authors/johdan/` `/works/johdan-d-upboy/`)。
日本語の読みは機械には確定できない (pykakasi は 城弾→shirodan、実際の板 id は johdan、
華代ちゃん→hanayochan、実際は kayo_chan) ので、このモジュールは**候補**しか作らない。
確定は人間 (進行台帳 1.5b の 👤 ゲート)。

`catalog/slug_overrides.yml` の構造:

    terms_genre:
      学園:
        slug: gakuen
        status: candidate      # candidate = 未確認 / confirmed = 人間が確認済み
        source: pykakasi       # pykakasi / ascii / kansou-board / fallback
    authors:
      城弾: {slug: johdan, status: confirmed, source: kansou-board}

再実行しても **status: confirmed の行は決して上書きしない**。
candidate 行の slug は再生成で変わりうる (アルゴリズム改善時に追随させるため)。
"""
import os
import re
import unicodedata

try:
    import yaml
except ImportError:                                     # pragma: no cover
    yaml = None

_KAKASI = None
_KAKASI_TRIED = False


def kakasi():
    """pykakasi のインスタンス (未導入なら None)。"""
    global _KAKASI, _KAKASI_TRIED
    if not _KAKASI_TRIED:
        _KAKASI_TRIED = True
        try:
            import pykakasi
            _KAKASI = pykakasi.kakasi()
        except ImportError:
            _KAKASI = None
    return _KAKASI


def slugify_ascii(text):
    """既に ASCII の語をそのまま slug 化する。"""
    s = unicodedata.normalize('NFKC', text).lower()
    s = re.sub(r"[^a-z0-9]+", '-', s).strip('-')
    return s


def is_ascii_word(text):
    s = unicodedata.normalize('NFKC', text)
    return bool(re.fullmatch(r"[\x20-\x7e]+", s)) and bool(re.search(r'[A-Za-z0-9]', s))


def romanize(text):
    """(slug, source)。source = ascii / pykakasi / fallback。"""
    if not text:
        return '', 'fallback'
    if is_ascii_word(text):
        s = slugify_ascii(text)
        if s:
            return s, 'ascii'
    k = kakasi()
    if k is None:
        return '', 'fallback'
    body = unicodedata.normalize('NFKC', text)
    roman = ''.join(part['hepburn'] for part in k.convert(body))
    s = slugify_ascii(roman)
    return (s, 'pykakasi') if s else ('', 'fallback')


def unique_slug(base, used, fallback_prefix='term'):
    """使用済み集合に対して一意な slug を返す (`-2`, `-3` …)。"""
    base = base or ''
    if not base:
        n = 1
        while '%s-%d' % (fallback_prefix, n) in used:
            n += 1
        base = '%s-%d' % (fallback_prefix, n)
    slug, n = base, 2
    while slug in used:
        slug = '%s-%d' % (base, n)
        n += 1
    used.add(slug)
    return slug


# --------------------------------------------------------------------------- overrides

HEADER = """\
# 恒久 URL になる slug の確認台帳 (自動生成 + 人手確定)。
#
#   status: candidate  … 機械が作った候補。**まだ確定していない**
#   status: confirmed  … 人間が確認済み。再生成しても上書きされない
#   source: kansou-board … 当時の感想板 id (最も信頼できる。確認不要)
#           ascii        … 原表記が ASCII なのでそのまま (確認不要)
#           pykakasi     … 機械の読み。**幻覚しうるので要確認**
#           fallback     … 読みが取れず連番を振った。要確認
#
# 直したいときは slug を書き換えて status を confirmed にしてください。
# 進行台帳 docs/wp-implementation-tasks.md の 👤 1.5b がこのファイルの確認タスクです。
#
# authors セクションで使える追加の欄:
#   note: …   裁定の根拠。人間が読むためのもの (生成器は触らない)
#   role: not-an-author
#             **この表示名では作者を作らない**。目録の作者欄に人名でない値が
#             入っていた場合 (「シェアワールド」= シリーズ目次ページ) に使う。
#             authors_build.py が作者化をやめ、話は entry_role / ts_world 側で拾う
#
# 複数の表示名に**同じ slug** を書くと、authors_build.py はそれを
# **同一人物の併合の指示**として読む (根室　眞琴 と 根室　眞琴改め… → slents、
# 麗香 → marie、ぽぽ → popo)。統合された表示名は authors.json の
# display_variants に残り、URL は 1 本になる。
#
# --- 同名別 slug の裁定 (2026-08-30 Fable。実装済み: catalog/episode_overrides.yml) ---
# 1) 神川綾乃: kamikawa_ayano_ は実在しない板 (CGI の hidden log 値が kamikawa_ayano)。
#    kamikawa_ayano に統合し、novel/200104/15172112/angels_12.html を kamikawa_ayano へ。
# 2) コーディー: jersey_red (ジャージレッド) とは別人。コーディーの正は kohdhi。
#    jersey_red の display_variants から「コーディー」を外し、
#    novel/200101/05064051/ao2.html の作者を kohdhi に付け替える (lib61.html の誤植の上書き)。
"""


def load_overrides(path):
    if not os.path.exists(path):
        return {}
    if yaml is None:
        raise RuntimeError('PyYAML が必要です (pip install pyyaml)')
    with open(path, encoding='utf-8') as fh:
        return yaml.safe_load(fh) or {}


def merge_section(existing, section, entries):
    """1 セクション分を差し替える。confirmed 行は既存値を優先して残す。"""
    out = dict(existing)
    prev = out.get(section) or {}
    merged = {}
    for key, rec in entries.items():
        old = prev.get(key)
        if isinstance(old, dict) and old.get('status') == 'confirmed':
            merged[key] = old
        else:
            merged[key] = rec
    # 確定済みなのに今回の入力に現れなかった行は消さない (取りこぼし防止)
    for key, old in prev.items():
        if key not in merged and isinstance(old, dict) and old.get('status') == 'confirmed':
            merged[key] = dict(old, stale=True)
    out[section] = merged
    return out


def save_overrides(path, data, notes=None):
    if yaml is None:
        raise RuntimeError('PyYAML が必要です (pip install pyyaml)')
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(HEADER)
        for line in (notes or []):
            fh.write('# %s\n' % line)
        fh.write('\n')
        yaml.safe_dump(data, fh, allow_unicode=True, sort_keys=True, default_flow_style=False)


def pending_count(data, section=None):
    """確認待ち (status != confirmed) の件数。"""
    total = 0
    for name, entries in (data or {}).items():
        if section and name != section:
            continue
        for rec in (entries or {}).values():
            if isinstance(rec, dict) and rec.get('status') != 'confirmed':
                total += 1
    return total
