#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Diff CDX dumps against the repo and emit a fetch manifest of missing URLs.

Reads all *.cdx files in the dump dir (fields: urlkey original timestamp
statuscode mimetype length), maps each URL to its repo path by the site-family
conventions, and for every path not present in the repo picks the best capture:
latest 200 with length>700 for HTML, latest 200 for other types.
Output: fetch_manifest.json [{url, ts, path, mime, length}]"""
import os, re, sys, json, collections
from urllib.parse import urlsplit, unquote

ROOT = '/home/takano32/GitHub/ts-novels'

def host_map(netloc, path):
    """(netloc, path) -> repo top dir + remaining path, or None to skip"""
    h = netloc.lower().split(':')[0]
    p = unquote(path).replace('%7E', '~').replace('%7e', '~')
    if h in ('ts.novels.jp',):
        return '', p
    if h in ('www.novels.jp', 'novels.jp'):
        m = re.match(r'^/(~[a-z0-9_]+)(/.*)?$', p)
        if m:
            return m.group(1), m.group(2) or '/'
        return '', p  # root alias of ts.novels.jp
    if h == 'www14.big.or.jp' and p.startswith('/~yays'):
        return '~yays', p[len('/~yays'):] or '/'
    if h == 'www2.tomato.ne.jp' and p.startswith('/~ezpe'):
        return '~ezpe', p[len('/~ezpe'):] or '/'
    if h == 'www2.sts.co.jp':
        m = re.match(r'^/(~[a-z0-9_]+)(/.*)?$', p)
        if m:
            return 'www2.sts.co.jp/' + m.group(1), m.group(2) or '/'
        return None
    if h in ('ts.novels.name', 'www.ts.novels.name'):
        return 'ts.novels.name', p
    if h in ('kirika.novels.name', 'www.kirika.novels.name'):
        return 'kirika.novels.name', p
    if h in ('novels.name', 'www.novels.name'):
        return 'www.novels.name', p
    if h == 'utanotuki.novels.name':
        return 'utanotuki.novels.name', p
    if h == 'ts.raa0121.info':
        return 'ts.raa0121.info', p
    if h in ('ts-novels.jp', 'www.ts-novels.jp'):
        return 'ts-novels.jp', p
    return None

def mangle(top, path, query):
    if path.endswith('/') or path == '':
        path = path + 'index.html'
    rp = (top + path) if top else path.lstrip('/')
    rp = rp.lstrip('/')
    if query:
        d, base = os.path.split(rp)
        if '.' in base:
            stem, ext = base.rsplit('.', 1)
            ext = '.' + ext
        else:
            stem, ext = base, ''
        # repo convention: keep %-escapes as bare hex (no unquote), strip '%'
        q = query.replace('%', '').replace('=', '_').replace('&', '_')
        q = q.replace('/', '_')
        rp = os.path.join(d, f'{stem}@{q}{ext}')
    rp = os.path.normpath(rp)
    if rp.startswith('..') or rp.startswith('/'):
        return None
    return rp

def repo_path_for(url):
    try:
        sp = urlsplit(url)
    except ValueError:
        return None
    if '/http:/' in sp.path or '/https:/' in sp.path or sp.path.startswith('//'):
        return None  # malformed capture of a broken link
    hm = host_map(sp.netloc, sp.path)
    if hm is None:
        return None
    top, rest = hm
    base = os.path.basename(rest)
    if base == 'robots.txt' or base == 'favicon.ico':
        return None
    return mangle(top, rest, sp.query)

HTMLISH = re.compile(r'\.(html?|cgi|shtml|php|pl|txt)$|/$|@', re.I)

def main(dumpdir, outpath):
    best = {}  # repopath -> (ts, url, mime, length, quality)
    seen_urls = collections.Counter()
    for fn in sorted(os.listdir(dumpdir)):
        if not fn.endswith('.cdx'):
            continue
        for line in open(os.path.join(dumpdir, fn), encoding='utf-8', errors='replace'):
            f = line.split()
            if len(f) < 6:
                continue
            _, url, ts, status, mime, length = f[:6]
            if status != '200':
                continue
            try:
                ln = int(length)
            except ValueError:
                ln = 0
            rp = repo_path_for(url)
            if rp is None:
                continue
            seen_urls[rp] += 1
            if os.path.isfile(os.path.join(ROOT, rp)):
                continue
            htmlish = bool(HTMLISH.search(rp)) or 'html' in mime
            # quality: real content beats stub; among same quality, latest wins
            q = 1 if (not htmlish or ln > 700 or ln == 0) else 0
            cur = best.get(rp)
            cand = (q, ts, url, mime, ln)
            if cur is None or (cand[0], cand[1]) > (cur[0], cur[1]):
                best[rp] = cand
    man = [{'path': rp, 'ts': c[1], 'url': c[2], 'mime': c[3], 'length': c[4], 'q': c[0]}
           for rp, c in sorted(best.items())]
    with open(outpath, 'w') as f:
        json.dump(man, f, ensure_ascii=False, indent=1)
    print(f'urls_mapped={len(seen_urls)} missing_in_repo={len(man)}')
    bytop = collections.Counter(m['path'].split('/')[0] for m in man)
    for t, n in bytop.most_common(20):
        print(f'  {t:28s} {n}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
