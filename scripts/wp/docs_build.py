#!/usr/bin/env python3
"""運営文書・前史・ギャラリーを ts_doc 用ペイロードにする (タスク 4.6 後半/4.7/4.9。Fable 所管)。

入力 (git 管理下の原本):
  comittee/*.html (21) ・ columns/*.html (2) ・ dialy/index.html (1)   … 運営文書
  ~ezpe/yasai/*.html (8)                                              … 文庫前史 (1999)
  ~yays/gallery/art*.html (65) + cg/ + album/                          … ギャラリー
  content/docs/*.html (手書きの解題など)                                … そのまま収録

出力:
  payloads-docs/<slug>.html            … wp:html 1 ブロック (原本のまま。リンクは原本アーカイブへ)
  payloads-docs/_manifest.json         … slug → {title, section, source_path}
  catalog/reports/docs_build.json

方針: 運営文書は物語本文と違い無損失証明の機構は使わず、原本 HTML を丸ごと
wp:html に包む (存在をそのまま見せる)。相対リンク・画像は原本アーカイブの
絶対 URL に書き換え、mailto はアドレスを DB に入れない鉄則に従い剥がす。
"""
import json
import os
import re
import sys
import unicodedata
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import body_convert  # noqa: E402

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'payloads-docs'
ANNEX = 'https://takano32.github.io/ts-novels/'

SOURCES = [
    ('comittee', '運営委員会', sorted(Path(ROOT / 'comittee').glob('*.html'))),
    ('columns', 'コラム', sorted(Path(ROOT / 'columns').glob('*.html'))),
    ('dialy', '運営日誌', sorted(Path(ROOT / 'dialy').glob('*.html'))),
    ('prehistory', '文庫前史 (1999)', sorted(Path(ROOT / '~ezpe' / 'yasai').glob('*.html'))),
]


def doc_slug(section, path):
    stem = re.sub(r'[^a-z0-9]+', '-', path.stem.lower()).strip('-') or 'index'
    return f'{section}-{stem}'


def rewrite_links(html, base_dir):
    """相対 href/src → 原本アーカイブの絶対 URL。mailto は剥がす (アドレス禁輸)。"""
    def sub(m):
        attr, q, url = m.group(1), m.group(2), m.group(3)
        if re.match(r'(?i)^(https?:)?//', url) or url.startswith('#'):
            return m.group(0)
        if url.lower().startswith('mailto:'):
            return f'{attr}={q}#{q}'
        rel = os.path.normpath(os.path.join(base_dir, url.replace('\\', '/'))).replace('\\', '/')
        return f'{attr}={q}{ANNEX}{rel}{q}'
    return re.sub(r'(?i)\b(href|src)\s*=\s*(["\'])([^"\']+)\2', sub, html)


def strip_mailto_text(html):
    """本文中に生のメールアドレスが書かれていたら伏せる (DB に入れない)。"""
    return re.sub(r'[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}', '(メールアドレスは省略)', html)


def title_of(raw, fallback):
    m = re.search(r'(?is)<title[^>]*>(.*?)</title>', raw)
    t = re.sub(r'\s+', ' ', m.group(1)).strip() if m else ''
    return t or fallback


def wrap(block_html):
    return '<!-- wp:html -->\n' + block_html + '\n<!-- /wp:html -->\n'


def build_docs(manifest, report):
    for section, label, files in SOURCES:
        for f in files:
            raw, _ = body_convert.read_text(str(f))
            body = body_convert.extract_body(raw)
            rel_dir = str(f.parent.relative_to(ROOT)).replace('\\', '/')
            body = strip_mailto_text(rewrite_links(body, rel_dir))
            slug = doc_slug(section, f)
            title = title_of(raw, f.stem)
            (OUT / f'{slug}.html').write_text(wrap(body), encoding='utf-8')
            manifest[slug] = {'title': title, 'section': section, 'section_label': label,
                              'source_path': str(f.relative_to(ROOT)).replace('\\', '/')}
            report['docs'] += 1


def build_gallery(manifest, report):
    """art*.html を束ねて 1 枚のギャラリーページに (画像は assets、物語は原本アーカイブへ)。"""
    parts = ['<p>2002 年版ライブラリのギャラリー「ギャラリー少年少女」の収蔵品です。'
             'イラストに寄せて書かれた献呈ストーリーは原本アーカイブで読めます。</p>']
    gdir = ROOT / '~yays' / 'gallery'
    n_img = 0
    import subprocess
    all_cg = set(re.sub(r'^~yays/gallery/', '', p) for p in subprocess.run(
        ['git', '-C', str(ROOT), 'ls-files', '~yays/gallery/cg'],
        capture_output=True, text=True).stdout.splitlines()
        if re.search(r'\.(jpe?g|gif|png)$', p, re.I))
    shown = set()

    def fig(src, alt, caption=''):
        nonlocal n_img
        n_img += 1
        shown.add(src)
        cap = '<figcaption>%s</figcaption>' % esc(caption) if caption else ''
        return ('<figure><img loading="lazy" src="/assets/annex-img/~yays/gallery/%s" alt="%s">%s</figure>'
                % (esc(src), esc(alt), cap))

    for f in sorted(gdir.glob('art*.html')):
        raw, _ = body_convert.read_text(str(f))
        title = (re.search(r'『([^』]+)』', raw) or [None, f.stem])[1]
        artist_m = re.search(r'絵師[：:]\s*(?:</?[bA-Za-z][^>]*>|\s)*([^<\n]+)', raw)
        artist = re.sub(r'\s+', ' ', artist_m.group(1)).strip() if artist_m else ''
        imgs = re.findall(r'(?i)<img[^>]*src="(cg/[^"]+)"', raw)
        stories = re.findall(r'(?i)<a[^>]*href="(story/[^"]+)"[^>]*>(.*?)</a>', raw)
        if not imgs:
            continue
        parts.append('<div class="ts-gallery-item">')
        parts.append('<h2>%s</h2>' % esc(title))
        if artist:
            parts.append('<p class="ts-gallery-artist">絵師: %s</p>' % esc(artist))
        for src in imgs:
            parts.append(fig(src, title))
        # 同じ番号の別版 (art ページから参照されていない cg_NN*.jpg も収蔵する — 最大掲載)
        num = re.sub(r'\D', '', f.stem)
        for v in sorted(s for s in all_cg
                        if re.match(r'cg/cg_0*%s[a-z]?\.' % re.escape(num.lstrip('0') or '0'), s)
                        and s not in shown):
            parts.append(fig(v, title, '別版 (%s)' % v.split('/')[-1]))
        for href, label in stories:
            label_txt = re.sub(r'<[^>]*>', '', label).strip()
            parts.append('<p><a href="%s~yays/gallery/%s">%s (原本アーカイブ)</a></p>'
                         % (ANNEX, esc(href), esc(label_txt or '献呈ストーリー')))
        parts.append('</div>')
    leftover = sorted(all_cg - shown)
    if leftover:
        parts.append('<div class="ts-gallery-item"><h2>そのほかの収蔵 CG</h2>')
        for src in leftover:
            parts.append(fig(src, src.split('/')[-1], src.split('/')[-1]))
        parts.append('</div>')
    # album (投稿アルバム) の画像
    album_imgs = sorted((gdir / 'album' / 'images').glob('*'))
    if album_imgs:
        parts.append('<div class="ts-gallery-item"><h2>投稿アルバム</h2>')
        for p in album_imgs:
            rel = str(p.relative_to(ROOT)).replace('\\', '/')
            date = re.sub(r'^(\d{4})(\d{2})(\d{2}).*', r'\1-\2-\3', p.stem)
            parts.append('<figure><img loading="lazy" src="/assets/annex-img/%s" alt="投稿アルバム %s">'
                         '<figcaption>%s</figcaption></figure>' % (esc(rel), esc(date), esc(date)))
            n_img += 1
        parts.append('</div>')
    (OUT / 'gallery.html').write_text(wrap('\n'.join(parts)), encoding='utf-8')
    manifest['gallery'] = {'title': 'ギャラリー少年少女', 'section': 'gallery',
                           'section_label': 'ギャラリー', 'source_path': '~yays/gallery/'}
    report['gallery_images'] = n_img


def build_handwritten(manifest, report):
    for f in sorted((ROOT / 'content' / 'docs').glob('*.html')):
        html = f.read_text(encoding='utf-8')
        m = re.search(r'<!--\s*title:\s*(.+?)\s*-->', html)
        title = m.group(1) if m else f.stem
        body = re.sub(r'^<!--\s*title:.*?-->\s*', '', html, flags=re.S)
        slug = f.stem
        (OUT / f'{slug}.html').write_text(wrap(body.strip()), encoding='utf-8')
        manifest[slug] = {'title': title, 'section': 'notes', 'section_label': '解題',
                          'source_path': f'content/docs/{f.name}'}
        report['handwritten'] += 1


def esc(s):
    return (str(s).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
            .replace('"', '&quot;'))


def main():
    OUT.mkdir(exist_ok=True)
    manifest = {}
    report = {'docs': 0, 'handwritten': 0, 'gallery_images': 0}
    build_docs(manifest, report)
    build_gallery(manifest, report)
    build_handwritten(manifest, report)
    (OUT / '_manifest.json').write_text(
        json.dumps(manifest, ensure_ascii=False, indent=1), encoding='utf-8')
    leftover = [s for s, m in manifest.items() if '@' in m['source_path']]
    report['total'] = len(manifest)
    (ROOT / 'catalog' / 'reports' / 'docs_build.json').write_text(
        json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print('docs payloads:', report)


if __name__ == '__main__':
    main()
