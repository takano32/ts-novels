#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""bcwiki_fetch.py — bc-cafe.net の BC-Wiki (old data library) を回収する。

きりか進ノ介さんの現行サイト `https://bc-cafe.net/bcwiki.old/` は、リポジトリに
既収蔵の `kirika.novels.name/wiki`(青秋桜Wiki) の**後継**で、いまも公開されている
(`/bcwiki/` の方は Basic 認証で 401)。ページ数は旧ミラーの 88 に対して 175 あり、
文庫関連の未収蔵ページ(ホーリーメイデンズ外伝・青秋桜のオフ 第10〜23回 ほか)を含む。

出力はリポジトリ規約名 `bc-cafe.net/bcwiki.old/index@<クエリ>.html`
(kirika.novels.name/wiki と同じ、`%` 除去・`=`/`&`/`/` → `_` の写像)。
添付ファイルは `?plugin=attach&pcmd=open&...` 経由でのみ取得できる
(`attach/` 直下は 403)。既定では本文ページのみを取り、添付は --attach で
拡張子ホワイトリスト (テキスト・画像) に限って取る。

  python3 scripts/bcwiki_fetch.py <stagedir> [--attach] [--limit N]

取ったものは place_convert.py --place → git commit(raw) → --convert の順で収める。
"""
import argparse
import gzip
import os
import re
import sys
import time
import urllib.parse
import urllib.request

BASE = 'https://bc-cafe.net/bcwiki.old/index.php'
UA = 'Mozilla/5.0 (compatible; ts-novels-archive/1.0)'
ATTACH_OK = ('.txt', '.htm', '.html', '.gif', '.png', '.jpg', '.jpeg')
# --media で追加する層。作者自身の作曲・自演 (文庫作品のイメージソング) と
# 青秋桜カードゲームの配布物。第三者著作物 (.flv の初音ミク動画) は入れない。
MEDIA_OK = ('.mp3', '.mid', '.xls', '.lzh')


def http(url, timeout=60):
    req = urllib.request.Request(url, headers={'User-Agent': UA,
                                               'Accept-Encoding': 'gzip'})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        data = r.read()
        if r.headers.get('Content-Encoding') == 'gzip':
            data = gzip.decompress(data)
        return data, r.getcode()


def mangle(query):
    """repo convention: strip %, '='/'&'/'/' -> '_' (kirika.novels.name/wiki と同型)"""
    return (query.replace('%', '').replace('=', '_')
                 .replace('&', '_').replace('/', '_').replace('?', '_'))


def page_list():
    data, _ = http(BASE + '?cmd=list')
    t = data.decode('euc-jp', 'replace')
    out = {}
    for m in re.finditer(r'index\.php\?([^"&\s>]+)"', t):
        q = m.group(1)
        if q.startswith(('cmd=', 'plugin=')):
            continue
        out[q] = urllib.parse.unquote(q, encoding='euc-jp', errors='replace')
    return out


def attach_list():
    """[(refer_query, filename_quoted, display_name, note)] — pcmd=open のリンクから。
    href は絶対 URL で `file=` が `refer=` より先に来る (PukiWiki 1.4.7 の attach プラグイン)。
    title 属性に「登録日時 サイズ」が入っているので控えておく。"""
    data, _ = http(BASE + '?plugin=attach&pcmd=list')
    t = data.decode('euc-jp', 'replace')
    out, seen = [], set()
    for m in re.finditer(
            r'index\.php\?plugin=attach&amp;pcmd=open&amp;file=([^"&]+)&amp;refer=([^"&]+)"'
            r'(?:\s+title="([^"]*)")?', t):
        fn, refer, note = m.group(1), m.group(2), m.group(3) or ''
        if (refer, fn) in seen:
            continue
        seen.add((refer, fn))
        out.append((refer, fn, urllib.parse.unquote(fn, encoding='euc-jp', errors='replace'), note))
    return out


def save(stage, rel, data):
    dst = os.path.join(stage, rel)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    with open(dst, 'wb') as fh:
        fh.write(data)


RE_HREF = re.compile(r'''(?:href|src)\s*=\s*"([^"]+)"''', re.I)


def relink(root, top='bc-cafe.net/bcwiki.old'):
    """収めたあとに、ページ内の絶対 URL (https://bc-cafe.net:443/bcwiki.old/index.php?…)
    を、同じミラー内の相対パスへ書き換える。place_convert.py の一般の写像は
    クエリを unquote してしまい EUC-JP の %XX 名と食い違うので、ここでは
    回収時に決めた URL→ファイル名の対応表をそのまま使う。"""
    base = os.path.join(root, top)
    page, att = {}, {}
    for q in page_list():
        page[q] = 'index@%s.html' % mangle(q)
    for refer, fn, disp, _note in attach_list():
        att[(refer, fn)] = 'index@plugin_attach_pcmd_open_refer_%s_file_%s' % (
            mangle(refer), mangle(fn))

    def target(url):
        if 'bc-cafe.net' not in url or '/bcwiki.old/index.php?' not in url:
            return None
        q = url.split('/bcwiki.old/index.php?', 1)[1]
        q = q.replace('&amp;', '&')
        frag = ''
        if '#' in q:
            q, frag = q.split('#', 1)
            frag = '#' + frag
        if q in page:
            return page[q] + frag
        parts = dict(p.split('=', 1) for p in q.split('&') if '=' in p)
        if parts.get('plugin') == 'attach' and parts.get('pcmd') == 'open':
            return att.get((parts.get('refer', ''), parts.get('file', '')))
        return None

    n = 0
    for fn in sorted(os.listdir(base)):
        if not fn.endswith('.html'):
            continue
        p = os.path.join(base, fn)
        text = open(p, encoding='utf-8').read()

        def sub(m):
            t = target(m.group(1))
            if t and os.path.exists(os.path.join(base, t.split('#')[0])):
                return m.group(0).replace(m.group(1), t)
            return m.group(0)
        new = RE_HREF.sub(sub, text)
        if new != text:
            open(p, 'w', encoding='utf-8').write(new)
            n += 1
    print('relinked_files=%d' % n, flush=True)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('stage')
    ap.add_argument('--attach', action='store_true', help='添付も取る (テキスト/画像)')
    ap.add_argument('--media', action='store_true',
                    help='さらに音源 (MP3/MID)・xls/lzh も取る (第三者著作物の .flv は除く)')
    ap.add_argument('--relink', action='store_true',
                    help='収めたあと <stage> をリポジトリ根とみなして絶対 URL を相対化する')
    ap.add_argument('--limit', type=int, default=0)
    ap.add_argument('--sleep', type=float, default=1.0)
    args = ap.parse_args(argv)
    top = 'bc-cafe.net/bcwiki.old'

    if args.relink:
        relink(args.stage, top)
        return 0

    pages = page_list()
    print('pages=%d' % len(pages), flush=True)
    n = 0
    for q, name in sorted(pages.items(), key=lambda kv: kv[1]):
        rel = '%s/index@%s.html' % (top, mangle(q))
        if os.path.exists(os.path.join(args.stage, rel)):
            continue
        try:
            data, code = http('%s?%s' % (BASE, q))
        except Exception as e:                                  # noqa: BLE001
            print('ERR %s: %s' % (name, e), flush=True)
            continue
        if code != 200 or len(data) < 500:
            print('SKIP %s (code=%s len=%d)' % (name, code, len(data)), flush=True)
            continue
        save(args.stage, rel, data)
        n += 1
        print('[%d] %s -> %s' % (n, name, rel), flush=True)
        if args.limit and n >= args.limit:
            break
        time.sleep(args.sleep)
    print('pages_fetched=%d' % n, flush=True)

    if not args.attach:
        return 0
    m = 0
    ok = ATTACH_OK + (MEDIA_OK if args.media else ())
    for refer, fn, disp, note in attach_list():
        if not disp.lower().endswith(ok):
            print('SKIP attach %s (%s)' % (disp, note), flush=True)
            continue
        rel = '%s/index@plugin_attach_pcmd_open_refer_%s_file_%s' % (
            top, mangle(refer), mangle(fn))
        if os.path.exists(os.path.join(args.stage, rel)):
            continue
        url = '%s?plugin=attach&pcmd=open&refer=%s&file=%s' % (BASE, refer, fn)
        try:
            data, code = http(url)
        except Exception as e:                                  # noqa: BLE001
            print('ERR attach %s: %s' % (disp, e), flush=True)
            continue
        if code != 200 or len(data) < 10:
            continue
        save(args.stage, rel, data)
        m += 1
        print('[a%d] %s (%s) -> %s' % (m, disp, note, rel), flush=True)
        time.sleep(args.sleep)
    print('attachments_fetched=%d' % m, flush=True)
    return 0


if __name__ == '__main__':
    sys.exit(main())
