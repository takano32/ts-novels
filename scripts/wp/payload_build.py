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
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from tslib import paths, payloads, bodies  # noqa: E402
from tslib.fileio import read_jsonl, read_json, read_text_guess, read_yaml_required, write_json  # noqa: E402
from tslib.mail import has_leak  # noqa: E402

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
            episodes, works, authors, read_yaml_required(ROOT / 'takedown' / 'denylist.yml'))
    except payloads.DenylistError as e:
        sys.exit(str(e))
    images = payloads.image_inventory(ROOT)

    run = payloads.PayloadRun()
    # 取り下げ対象は payload を作らず、前回の payload も消す (論理削除)
    for eid in sorted(takedown):
        run.meta[eid] = 'takedown'
        f = OUT / f'{eid}.html'
        if f.exists():
            f.unlink()
            run.removed.append(eid)
    md_ids = [p.name[:-3] for p in sorted(BODIES.glob('*.md'))]
    steps, run.skipped = payloads.plan_payloads(
        md_ids, episodes, raw_fallbacks, takedown,
        repost_exists=lambda eid: (ROOT / 'reposts' / f'{eid}.txt').is_file())

    for eid, status in steps:
        ep = episodes.get(eid)
        if status == 'converted':
            src_dir = os.path.dirname(ep['source_path']) if ep else ''
            out, ok, refs = payloads.md_payload((BODIES / f'{eid}.md').read_text(encoding='utf-8'), src_dir,
                                                images, run.stats, run.warn, run.mail_n)
            for em in refs:
                run.eyecatch.setdefault(eid, []).append(em)
        elif status == 'converted-text':
            txt = (ROOT / 'reposts' / f'{eid}.txt').read_text(encoding='utf-8')
            out, ok = payloads.text_payload(txt, run.stats, run.mail_n)
        else:                                   # raw-fallback
            raw, _enc = read_text_guess(str(ROOT / ep['source_path']))
            src_dir = os.path.dirname(ep['source_path'])
            out = payloads.raw_payload(raw, bodies.extract_body(raw), src_dir, images, run.warn, run.mail_n)
            ok = True                           # 原本をそのまま出すので無損失検査はしない
            run.raw_written += 1
        if not ok:
            run.violations.append(eid)
        (OUT / f'{eid}.html').write_text(out, encoding='utf-8')
        run.meta[eid] = status
        run.written += 1

    write_json(OUT / '_meta.json', run.meta, indent=0, newline=False)
    # 出荷前の自己検査: 生アドレスが 1 つでも残っていたら失敗させる (鉄則の機械化)
    leaked = [f.name for f in sorted(OUT.glob('*.html'))
              if has_leak(f.read_text(encoding='utf-8'))]
    report = payloads.report_of(run, raw_fallbacks, takedown, takedown_entries, leaked)
    rp = ROOT / 'catalog' / 'reports' / 'payload_build.json'
    write_json(rp, report, indent=2, newline=False)
    print(f"payloads: {run.written} 本 (raw {run.raw_written}) blocks={run.stats}")
    print(f"取り下げで飛ばした話: {len(takedown)} 話 (本文あり md {run.skipped['md']} / 再掲 "
          f"{run.skipped['repost']} / raw {run.skipped['raw']}、前回の payload を消した {len(run.removed)} 本)")
    for t in takedown_entries:
        print(f"  {t['id']} {t['kind']}={t['value']}: {t['episodes']} 話")
    print(f"アドレス除去: mailto {run.mail_n['mailto']} / 生 {run.mail_n['bare']} 件、残存 {len(set(leaked))} 本")
    print(f"無損失検査: {'OK' if not run.violations else 'NG %d 件' % len(run.violations)}"
          f" / 画像未解決 {len(set(run.warn['missing']))} / 外部 {len(set(run.warn['external']))}")
    print(f"report: {rp}")
    if leaked:
        print(f"!! アドレスが残っているペイロード {len(set(leaked))} 本: {sorted(set(leaked))[:5]}")
    if run.violations or leaked:
        sys.exit(1)


if __name__ == '__main__':
    main()
