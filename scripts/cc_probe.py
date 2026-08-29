#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Probe CommonCrawl per-collection index APIs for captures of given URLs.

Input: text file of original URLs, one per line. Collections via --colls
(comma-separated, default: a spread of late collections).
Note: index.commoncrawl.org is often slow (30s+ per query) — run in background.
Output: <out>.json mapping URL -> [(collection, warc filename, offset, length)]."""
import urllib.request, urllib.parse, json, time, sys

DEFAULT_COLLS = ['CC-MAIN-2013-48', 'CC-MAIN-2015-40', 'CC-MAIN-2017-39',
                 'CC-MAIN-2019-35', 'CC-MAIN-2020-45', 'CC-MAIN-2021-21']

def main(listfile, outfile, colls=None):
    colls = colls or DEFAULT_COLLS
    urls = [l.strip() for l in open(listfile) if l.strip() and not l.startswith('#')]
    hits = {}
    for i, u in enumerate(urls):
        dom = u.split('//', 1)[-1]
        for c in colls:
            api = (f'https://index.commoncrawl.org/{c}-index'
                   f'?url={urllib.parse.quote(dom, safe="")}&output=json&limit=3')
            try:
                with urllib.request.urlopen(api, timeout=40) as r:
                    for line in r.read().decode().splitlines():
                        rec = json.loads(line)
                        if rec.get('status') == '200':
                            hits.setdefault(u, []).append(
                                (c, rec['filename'], rec['offset'], rec['length']))
                            print('CCHIT', u, c, flush=True)
            except Exception:
                pass
            time.sleep(0.4)
        if (i + 1) % 10 == 0:
            print(f'  probed {i+1}/{len(urls)}, hits={len(hits)}', flush=True)
    json.dump(hits, open(outfile, 'w'), indent=1)
    print(f'DONE probed={len(urls)} hits={len(hits)}')

if __name__ == '__main__':
    colls = None
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    for a in sys.argv[1:]:
        if a.startswith('--colls='):
            colls = a.split('=', 1)[1].split(',')
    main(args[0], args[1], colls)
