#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Integrate staged raw fetches into the repo.

  --place <stage>   copy staged files into repo (skips existing, filters CGI
                    error stubs and Wayback junk), print what was placed
  --convert <list>  convert the listed repo files (one path/line) to UTF-8
                    and rewrite absolute family links to relative repo paths

Conventions follow earlier integration passes: raw bytes are committed first,
conversion is a separate commit."""
import os, re, sys, json, shutil
from urllib.parse import urlsplit, unquote

ROOT = '/home/takano32/GitHub/ts-novels'

ERROR_MARKERS = [b'ERROR!!', 'ERROR!!'.encode('cp932'),
                 '指定した作品が見つかりません'.encode('cp932'),
                 '該当する記事は存在しません'.encode('cp932'),
                 '該当記事が見つかりません'.encode('cp932'),
                 '投稿されていません'.encode('cp932'),
                 b'Wayback Machine has not archived']

HOST_TOP = {
    'ts.novels.jp': '', 'novels.jp': '', 'www.novels.jp': '',
    'www14.big.or.jp/~yays': '~yays', 'www2.tomato.ne.jp/~ezpe': '~ezpe',
    'ts.novels.name': 'ts.novels.name', 'www.ts.novels.name': 'ts.novels.name',
    'kirika.novels.name': 'kirika.novels.name', 'www.kirika.novels.name': 'kirika.novels.name',
    'www.novels.name': 'www.novels.name', 'novels.name': 'www.novels.name',
    'utanotuki.novels.name': 'utanotuki.novels.name',
    'ts.raa0121.info': 'ts.raa0121.info',
    'ts-novels.jp': 'ts-novels.jp', 'www.ts-novels.jp': 'ts-novels.jp',
    'www2.sts.co.jp/~yaji': 'www2.sts.co.jp/~yaji',
    'www2.sts.co.jp/~ts': 'www2.sts.co.jp/~ts',
}

def is_error_stub(data, path):
    if len(data) > 1200 or not path.lower().endswith(('.cgi', '.html', '.htm')):
        return False
    return any(m in data for m in ERROR_MARKERS)

def place(stage):
    placed, skipped, stubs, collide = [], 0, 0, []
    for dirpath, dirnames, filenames in os.walk(stage):
        for fn in filenames:
            if fn.startswith('_'):
                continue
            sp = os.path.join(dirpath, fn)
            rel = os.path.relpath(sp, stage)
            dst = os.path.join(ROOT, rel)
            if os.path.exists(dst):
                skipped += 1
                continue
            data = open(sp, 'rb').read()
            if is_error_stub(data, rel):
                stubs += 1
                continue
            try:
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                shutil.copyfile(sp, dst)
                placed.append(rel)
            except (NotADirectoryError, FileExistsError, OSError) as e:
                collide.append(f'{rel}: {e}')
    print(f'placed={len(placed)} skipped_existing={skipped} error_stubs={stubs} collisions={len(collide)}')
    for c in collide[:20]:
        print('  COLLIDE', c)
    with open(os.path.join(stage, '_placed.txt'), 'w') as f:
        f.write('\n'.join(placed))

# ---------- conversion ----------

def decode_best(data):
    for enc in ('utf-8', 'cp932', 'euc_jp'):
        try:
            return data.decode(enc), enc
        except (UnicodeDecodeError, LookupError):
            continue
    a = data.decode('cp932', 'replace')
    b = data.decode('euc_jp', 'replace')
    return (a, 'cp932~') if a.count('�') <= b.count('�') else (b, 'euc_jp~')

META_RE = re.compile(r'<meta[^>]*charset\s*=\s*["\']?[\w_-]+["\']?[^>]*>', re.I)
ATTR_RE = re.compile(r'''(?:href|src|action|background)\s*=\s*(?:"([^"<>]*)"|'([^'<>]*)'|([^\s>"'<>]+))''', re.I)
AD_RE = re.compile(r'<script[^>]*(?:adsbygoogle|google-analytics|googlesyndication|geov2|/js/gg\.js|cdn-cgi)[^>]*>\s*</script>|<ins[^>]*adsbygoogle[^>]*>.*?</ins>', re.I | re.S)

def mangle_q(path, query):
    d, base = os.path.split(path)
    if '.' in base:
        stem, ext = base.rsplit('.', 1)
        ext = '.' + ext
    else:
        stem, ext = base, ''
    q = unquote(query).replace('%', '').replace('=', '_').replace('&', '_').replace('/', '_').replace('?', '_')
    return (d + '/' if d else '') + f'{stem}@{q}{ext}'

def repo_target(url):
    """absolute family URL -> repo path, else None"""
    try:
        sp = urlsplit(url)
    except ValueError:
        return None
    if sp.scheme not in ('http', 'https'):
        return None
    h = sp.netloc.lower().split(':')[0]
    p = unquote(sp.path).replace('%7E', '~').replace('%7e', '~')
    for key in sorted(HOST_TOP, key=len, reverse=True):
        if '/' in key:
            kh, kp = key.split('/', 1)
            if h == kh and (p == '/' + kp or p.startswith('/' + kp + '/')):
                rest = p[len(kp) + 1:].lstrip('/')
                top = HOST_TOP[key]
                break
        elif h == key:
            rest = p.lstrip('/')
            top = HOST_TOP[key]
            break
    else:
        return None
    if rest == '' or p.endswith('/'):
        rest = rest + ('index.html' if not rest or rest.endswith('/') else '')
    rp = (top + '/' + rest).lstrip('/') if top else rest
    if sp.query:
        rp = mangle_q(rp, sp.query)
    return os.path.normpath(rp)

def rewrite_links(text, rel):
    reldir = os.path.dirname(rel)
    out = []
    def relhref(target):
        r = os.path.relpath(target, reldir or '.')
        return r.replace(os.sep, '/')
    def sub(m):
        gi = next(i for i, g in enumerate(m.groups()) if g is not None)
        url = m.groups()[gi]
        t = repo_target(url.strip())
        new = None
        if t and os.path.isfile(os.path.join(ROOT, t)):
            new = relhref(t)
        elif '?' in url and not url.startswith(('http:', 'https:', 'mailto:', 'javascript:')):
            # relative link with query -> mangled filename
            base, q = url.split('?', 1)
            if base and q:
                cand = mangle_q(os.path.normpath(os.path.join(reldir, unquote(base))), q)
                if os.path.isfile(os.path.join(ROOT, cand)):
                    new = relhref(cand)
        if new is None:
            return m.group(0)
        s, e = m.span(gi + 1)
        whole = m.group(0)
        off = m.start(0)
        return whole[:s - off] + new + whole[e - off:]
    return ATTR_RE.sub(sub, text)

def convert(listfile):
    n = ch = 0
    for line in open(listfile):
        rel = line.strip()
        if not rel or not rel.lower().endswith(('.htm', '.html', '.cgi', '.shtml', '.txt', '.css', '.js')):
            continue
        fp = os.path.join(ROOT, rel)
        if not os.path.isfile(fp):
            continue
        data = open(fp, 'rb').read()
        text, enc = decode_best(data)
        if rel.lower().endswith(('.htm', '.html', '.cgi', '.shtml')):
            text = AD_RE.sub('', text)
            if META_RE.search(text):
                text = META_RE.sub('<meta charset="utf-8">', text, count=1)
            else:
                text = re.sub(r'(<head[^>]*>)', r'\1<meta charset="utf-8">', text, count=1, flags=re.I)
            text = rewrite_links(text, rel)
        open(fp, 'w', encoding='utf-8').write(text)
        n += 1
        if enc != 'utf-8':
            ch += 1
    print(f'converted={n} reencoded={ch}')

if __name__ == '__main__':
    if sys.argv[1] == '--place':
        place(sys.argv[2])
    elif sys.argv[1] == '--convert':
        convert(sys.argv[2])
