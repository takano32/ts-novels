#!/usr/bin/env python3
"""bodies/*.md → Gutenberg ブロック HTML (T9.1 + T9.2 の src 書換。高品位なモデルとエフォートの所管)。

入力:
  bodies/<episode_id>.md            … body_convert.py の成果 (3,642 本)
  catalog/episodes.jsonl            … source_path (画像の相対参照の基準ディレクトリ)
  catalog/convert_report.jsonl      … raw-fallback 2 件の検出
  catalog/works.jsonl・authors.json … 取り下げ対象の解決
  takedown/denylist.yml             … 取り下げ対象 (status が reinstated でないもの)
  git ls-files novel                … 画像実体の存在検査

出力:
  payloads/<episode_id>.html        … `wp ts import --bodies=payloads` が post_content に流す
  payloads/_meta.json               … episode_id → body_status (importer が _ts_body_status に書く)
  catalog/reports/payload_build.json

ブロック対応・無損失検査・取り下げの範囲の決まりは scripts/wp/tslib/payloads.py (specs/S243.md)。
このファイルは読み書きと表示だけ。
"""
import os
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from tslib import paths, payloads, bodies  # noqa: E402
from tslib.fileio import read_jsonl, read_json, read_yaml, write_json  # noqa: E402

ROOT = Path(paths.REPO_ROOT)
BODIES = ROOT / 'bodies'
OUT = ROOT / 'payloads'


def main():
    OUT.mkdir(exist_ok=True)
    episodes = {ep['episode_id']: ep for ep in read_jsonl(ROOT / 'catalog' / 'episodes.jsonl')}
    raw_fallbacks = [r['episode_id'] for r in read_jsonl(ROOT / 'catalog' / 'convert_report.jsonl')
                     if r.get('status') == 'raw-fallback']
    works = read_jsonl(ROOT / 'catalog' / 'works.jsonl')
    authors = read_json(ROOT / 'catalog' / 'authors.json')
    if isinstance(authors, dict):
        authors = authors['authors']
    try:
        takedown, takedown_entries = payloads.takedown_episodes(
            episodes, works, authors, read_yaml(ROOT / 'takedown' / 'denylist.yml'))
    except payloads.DenylistError as e:
        sys.exit(str(e))
    images = payloads.image_inventory(ROOT)

    stats = payloads.new_stats()
    warn = {'missing': [], 'external': [], 'junk': []}
    meta, violations, eyecatch = {}, [], {}
    written = 0

    mail_n = {'mailto': 0, 'bare': 0}
    # 取り下げ対象は payload を作らず、前回の payload も消す (論理削除)
    removed = []
    for eid in sorted(takedown):
        meta[eid] = 'takedown'
        f = OUT / f'{eid}.html'
        if f.exists():
            f.unlink()
            removed.append(eid)
    skipped = {'md': 0, 'repost': 0, 'raw': 0}
    body_md = {p.name[:-3] for p in BODIES.glob('*.md')}

    for md_path in sorted(BODIES.glob('*.md')):
        eid = md_path.name[:-3]
        if eid in takedown:
            skipped['md'] += 1
            continue
        ep = episodes.get(eid)
        src_dir = os.path.dirname(ep['source_path']) if ep else ''
        out, ok, refs = payloads.md_payload(md_path.read_text(encoding='utf-8'), src_dir, images,
                                            stats, warn, mail_n)
        if not ok:
            violations.append(eid)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        for em in refs:
            eyecatch.setdefault(eid, []).append(em)
        meta[eid] = 'converted'
        written += 1

    # T7.8 の作者再掲ぶん (S179): md が無く reposts/<episode_id>.txt がある話はプレーンテキストを段落化
    for eid, ep in episodes.items():
        txt_path = ROOT / 'reposts' / f'{eid}.txt'
        if eid in takedown:
            if eid not in body_md and txt_path.is_file():
                skipped['repost'] += 1
            continue
        if eid in meta:
            continue
        if not txt_path.is_file():
            continue
        out, ok = payloads.text_payload(txt_path.read_text(encoding='utf-8'), stats, mail_n)
        if not ok:
            violations.append(eid)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        meta[eid] = 'converted-text'
        written += 1

    raw_written = 0
    for eid in raw_fallbacks:
        ep = episodes.get(eid)
        if not ep:
            continue
        if eid in takedown:
            skipped['raw'] += 1
            continue
        raw, _enc = bodies.read_text_any(str(ROOT / ep['source_path']))
        src_dir = os.path.dirname(ep['source_path'])
        out = payloads.raw_payload(raw, bodies.extract_body(raw), src_dir, images, warn, mail_n)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        meta[eid] = 'raw-fallback'
        written += 1
        raw_written += 1

    write_json(OUT / '_meta.json', meta, indent=0, newline=False)
    # 出荷前の自己検査: 生アドレスが 1 つでも残っていたら失敗させる (鉄則の機械化)
    leaked = [f.name for f in sorted(OUT.glob('*.html'))
              if payloads.has_leak(f.read_text(encoding='utf-8'))]
    report = payloads.report_of(written, raw_written, raw_fallbacks, takedown, skipped, removed,
                                takedown_entries, stats, violations, warn, meta, eyecatch, mail_n, leaked)
    rp = ROOT / 'catalog' / 'reports' / 'payload_build.json'
    write_json(rp, report, indent=2, newline=False)
    print(f"payloads: {written} 本 (raw {raw_written}) blocks={stats}")
    print(f"取り下げで飛ばした話: {len(takedown)} 話 (本文あり md {skipped['md']} / 再掲 "
          f"{skipped['repost']} / raw {skipped['raw']}、前回の payload を消した {len(removed)} 本)")
    for t in takedown_entries:
        print(f"  {t['id']} {t['kind']}={t['value']}: {t['episodes']} 話")
    print(f"アドレス除去: mailto {mail_n['mailto']} / 生 {mail_n['bare']} 件、残存 {len(set(leaked))} 本")
    print(f"無損失検査: {'OK' if not violations else 'NG %d 件' % len(violations)}"
          f" / 画像未解決 {len(set(warn['missing']))} / 外部 {len(set(warn['external']))}")
    print(f"report: {rp}")
    if leaked:
        print(f"!! アドレスが残っているペイロード {len(set(leaked))} 本: {sorted(set(leaked))[:5]}")
    if violations or leaked:
        sys.exit(1)


if __name__ == '__main__':
    main()
