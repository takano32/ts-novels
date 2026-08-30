#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""One-shot: dump Wayback CDX for a single host/prefix and build a wb_fetch
manifest that places files under <topdir>/ with the repo naming conventions.
Used for newly-discovered family hosts (e.g. aetherworks.org).

Usage: cdx_single_host.py <host-or-prefix> <topdir> <out_manifest.json>
       [--match=domain|host|prefix] [--min-html=500]"""
import sys, os, json, urllib.request, urllib.parse
from urllib.parse import urlsplit, unquote

def main(target, topdir, outfile, match='domain', min_html=500):
    q = urllib.parse.urlencode({
        'url': target, 'matchType': match, 'output': 'text', 'limit': '200000',
        'fl': 'urlkey,original,timestamp,statuscode,mimetype,length'})
    with urllib.request.urlopen(f'https://web.archive.org/cdx/search/cdx?{q}',
                                timeout=300) as r:
        lines = r.read().decode('utf-8', 'replace').splitlines()
    best = {}
    for line in lines:
        f = line.split()
        if len(f) < 6 or f[3] != '200':
            continue
        _, url, ts, st, mime, ln = f[:6]
        try:
            ln = int(ln)
        except ValueError:
            ln = 0
        sp = urlsplit(url)
        p = unquote(sp.path)
        if p.endswith('/') or p == '':
            p += 'index.html'
        rp = topdir.rstrip('/') + '/' + p.lstrip('/')
        if sp.query:
            d, b = os.path.split(rp)
            stem, ext = (b.rsplit('.', 1) + ['html'])[:2] if '.' in b else (b, 'html')
            qq = (sp.query.replace('%', '').replace('=', '_').replace('&', '_')
                  .replace('/', '_').replace('?', '_'))
            rp = f'{d}/{stem}@{qq}.{ext}'
        good = 1 if ln > min_html else 0
        cur = best.get(rp)
        if cur is None or (good, ts) > (cur[0], cur[1]):
            best[rp] = (good, ts, url, mime, ln)
    man = [{'path': p, 'ts': v[1], 'url': v[2], 'mime': v[3], 'length': v[4], 'q': v[0]}
           for p, v in sorted(best.items())]
    json.dump(man, open(outfile, 'w'), ensure_ascii=False, indent=1)
    print(f'cdx_lines={len(lines)} manifest={len(man)}')

if __name__ == '__main__':
    kw = {}
    args = []
    for a in sys.argv[1:]:
        if a.startswith('--match='):
            kw['match'] = a.split('=', 1)[1]
        elif a.startswith('--min-html='):
            kw['min_html'] = int(a.split('=', 1)[1])
        else:
            args.append(a)
    main(*args, **kw)
