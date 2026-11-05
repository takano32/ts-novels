# -*- coding: utf-8 -*-
"""docs.py — 運営文書・前史・ギャラリーを ts_doc 用ペイロードにするロジック (T10.5)。

方針: 運営文書は物語本文と違い無損失証明の機構は使わず、原本 HTML を丸ごと
wp:html に包む (存在をそのまま見せる)。相対リンク・画像は原本アーカイブの
絶対 URL に書き換え、mailto はアドレスを DB に入れない鉄則に従い剥がす。

入出力は scripts/wp/docs_build.py。render_* は (slug, HTML, manifest の 1 行) を返すだけで書かない。
"""
import os
import re
from pathlib import Path

from tslib.bodies import extract_body
from tslib.fileio import read_text_guess
from tslib.gitinfo import ls_files
from tslib.mail import MAIL_KEEP_DOCS_RE, redact_bare
from tslib.paths import DEFAULT_ANNEX_BASE

ANNEX = DEFAULT_ANNEX_BASE
# (区分の鍵, 表示名, 原本のディレクトリ (root からの相対), glob)
SOURCE_SPECS = [
    ('comittee', '運営委員会', 'comittee', '*.html'),
    ('columns', 'コラム', 'columns', '*.html'),
    ('dialy', '運営日誌', 'dialy', '*.html'),
    ('prehistory', '文庫前史 (1999)', os.path.join('~ezpe', 'yasai'), '*.html'),
]
GALLERY_IMAGE_RE = re.compile(r'\.(jpe?g|gif|png)$', re.I)


def doc_sources(root):
    """原本の一覧 [(section, label, [Path])]。ファイル名順。"""
    root = Path(root)
    return [(section, label, sorted((root / rel).glob(pattern)))
            for section, label, rel, pattern in SOURCE_SPECS]


def doc_slug(section, path):
    stem = re.sub(r'[^a-z0-9]+', '-', Path(path).stem.lower()).strip('-') or 'index'
    return f'{section}-{stem}'


def rewrite_links(html, base_dir, annex=ANNEX):
    """相対 href/src → 原本アーカイブの絶対 URL。mailto は剥がす (アドレス禁輸)。"""
    def sub(m):
        attr, q, url = m.group(1), m.group(2), m.group(3)
        if re.match(r'(?i)^(https?:)?//', url) or url.startswith('#'):
            return m.group(0)
        if url.lower().startswith('mailto:'):
            return f'{attr}={q}#{q}'
        rel = os.path.normpath(os.path.join(base_dir, url.replace('\\', '/'))).replace('\\', '/')
        return f'{attr}={q}{annex}{rel}{q}'
    return re.sub(r'(?i)\b(href|src)\s*=\s*(["\'])([^"\']+)\2', sub, html)


def strip_mailto_text(html):
    """本文中に生のメールアドレスが書かれていたら伏せる (DB に入れない)。
    ファイル名やプレースホルダは巻き添えにしない (dialy の設定例が壊れる)。"""
    return redact_bare(html, MAIL_KEEP_DOCS_RE)


def title_of(raw, fallback):
    m = re.search(r'(?is)<title[^>]*>(.*?)</title>', raw)
    t = re.sub(r'\s+', ' ', m.group(1)).strip() if m else ''
    return t or fallback


def wrap(block_html):
    return '<!-- wp:html -->\n' + block_html + '\n<!-- /wp:html -->\n'


def esc(s):
    return (str(s).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
            .replace('"', '&quot;'))


def render_doc(section, label, f, root, annex=ANNEX):
    """運営文書 1 本 → (slug, ペイロード HTML, manifest の行)。"""
    root = Path(root)
    raw, _ = read_text_guess(str(f))
    body = extract_body(raw)
    rel_dir = str(f.parent.relative_to(root)).replace('\\', '/')
    body = strip_mailto_text(rewrite_links(body, rel_dir, annex))
    slug = doc_slug(section, f)
    entry = {'title': title_of(raw, f.stem), 'section': section, 'section_label': label,
             'source_path': str(f.relative_to(root)).replace('\\', '/')}
    return slug, wrap(body), entry


def gallery_cg_files(root):
    """git 管理下のギャラリー CG (~yays/gallery/cg/ の画像) を gallery/ からの相対で。"""
    out = ls_files(root, '~yays/gallery/cg', check=False)
    return set(re.sub(r'^~yays/gallery/', '', p) for p in out if GALLERY_IMAGE_RE.search(p))


def render_gallery(root, all_cg, annex=ANNEX):
    """art*.html を束ねて 1 枚のギャラリーページに (画像は assets、物語は原本アーカイブへ)。
    戻り値 (ペイロード HTML, manifest の行, 画像の数)。all_cg は gallery_cg_files の結果。"""
    root = Path(root)
    parts = ['<p>2002 年版ライブラリのギャラリー「ギャラリー少年少女」の収蔵品です。'
             'イラストに寄せて書かれた献呈ストーリーは原本アーカイブで読めます。</p>']
    gdir = root / '~yays' / 'gallery'
    n_img = 0
    shown = set()

    def fig(src, alt, caption=''):
        nonlocal n_img
        n_img += 1
        shown.add(src)
        cap = '<figcaption>%s</figcaption>' % esc(caption) if caption else ''
        return ('<figure><img loading="lazy" src="/assets/annex-img/~yays/gallery/%s" alt="%s">%s</figure>'
                % (esc(src), esc(alt), cap))

    for f in sorted(gdir.glob('art*.html')):
        raw, _ = read_text_guess(str(f))
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
                         % (annex, esc(href), esc(label_txt or '献呈ストーリー')))
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
            rel = str(p.relative_to(root)).replace('\\', '/')
            date = re.sub(r'^(\d{4})(\d{2})(\d{2}).*', r'\1-\2-\3', p.stem)
            parts.append('<figure><img loading="lazy" src="/assets/annex-img/%s" alt="投稿アルバム %s">'
                         '<figcaption>%s</figcaption></figure>' % (esc(rel), esc(date), esc(date)))
            n_img += 1
        parts.append('</div>')
    entry = {'title': 'ギャラリー少年少女', 'section': 'gallery',
             'section_label': 'ギャラリー', 'source_path': '~yays/gallery/'}
    return wrap('\n'.join(parts)), entry, n_img


def render_handwritten(f):
    """content/docs/*.html (手書きの解題) 1 本 → (slug, ペイロード HTML, manifest の行)。
    先頭の `<!-- title: … -->` が題名。"""
    html = Path(f).read_text(encoding='utf-8')
    m = re.search(r'<!--\s*title:\s*(.+?)\s*-->', html)
    title = m.group(1) if m else Path(f).stem
    body = re.sub(r'^<!--\s*title:.*?-->\s*', '', html, flags=re.S)
    entry = {'title': title, 'section': 'notes', 'section_label': '解題',
             'source_path': f'content/docs/{Path(f).name}'}
    return Path(f).stem, wrap(body.strip()), entry


def handwritten_files(root):
    return sorted((Path(root) / 'content' / 'docs').glob('*.html'))
