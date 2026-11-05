#!/usr/bin/env python3
"""運営文書・前史・ギャラリーを ts_doc 用ペイロードにする (T10.5。高品位なモデルとエフォートの所管)。

入力 (git 管理下の原本):
  comittee/*.html (21) ・ columns/*.html (2) ・ dialy/index.html (1)   … 運営文書
  ~ezpe/yasai/*.html (8)                                              … 文庫前史 (1999)
  ~yays/gallery/art*.html (65) + cg/ + album/                          … ギャラリー
  content/docs/*.html (手書きの解題など)                                … そのまま収録

出力:
  payloads-docs/<slug>.html            … wp:html 1 ブロック (原本のまま。リンクは原本アーカイブへ)
  payloads-docs/_manifest.json         … slug → {title, section, source_path}
  catalog/reports/docs_build.json

区分と変換の決まりは scripts/wp/tslib/docs.py (specs/S243.md)。このファイルは読み書きだけ。
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from tslib import paths, docs  # noqa: E402
from tslib.fileio import write_json  # noqa: E402

ROOT = Path(paths.REPO_ROOT)
OUT = ROOT / 'payloads-docs'


def main():
    OUT.mkdir(exist_ok=True)
    manifest = {}
    report = {'docs': 0, 'handwritten': 0, 'gallery_images': 0}
    for section, label, files in docs.doc_sources(ROOT):
        for f in files:
            slug, html, entry = docs.render_doc(section, label, f, ROOT)
            (OUT / f'{slug}.html').write_text(html, encoding='utf-8')
            manifest[slug] = entry
            report['docs'] += 1
    html, entry, n_img = docs.render_gallery(ROOT, docs.gallery_cg_files(ROOT))
    (OUT / 'gallery.html').write_text(html, encoding='utf-8')
    manifest['gallery'] = entry
    report['gallery_images'] = n_img
    for f in docs.handwritten_files(ROOT):
        slug, html, entry = docs.render_handwritten(f)
        (OUT / f'{slug}.html').write_text(html, encoding='utf-8')
        manifest[slug] = entry
        report['handwritten'] += 1
    write_json(OUT / '_manifest.json', manifest, indent=1, newline=False)
    report['total'] = len(manifest)
    write_json(ROOT / 'catalog' / 'reports' / 'docs_build.json', report, indent=2, newline=False)
    print('docs payloads:', report)


if __name__ == '__main__':
    main()
