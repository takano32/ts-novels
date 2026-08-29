#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Probe the Memento TimeTravel aggregator (timetravel.mementoweb.org) for
captures of given URLs across many archives (archive.today, NDL WARP, etc.).

Input: text file of original URLs, one per line.
Output: <out>.json mapping URL -> memento URIs (non-IA sources first)."""
import urllib.request, json, time, sys

def main(listfile, outfile, since='2008'):
    urls = [l.strip() for l in open(listfile) if l.strip() and not l.startswith('#')]
    hits = {}
    for i, u in enumerate(urls):
        api = f'http://timetravel.mementoweb.org/api/json/{since}/' + u
        try:
            with urllib.request.urlopen(api, timeout=30) as r:
                d = json.load(r)
                mem = d.get('mementos', {}).get('list', [])
                non_ia = [m for m in mem if 'archive.org' not in m.get('uri', '')]
                if mem:
                    hits[u] = [m['uri'] for m in (non_ia or mem)][:5]
                    print('HIT', u, '->', hits[u][0], flush=True)
        except Exception:
            pass
        if (i + 1) % 25 == 0:
            print(f'  probed {i+1}/{len(urls)}, hits={len(hits)}', flush=True)
        time.sleep(1.0)
    json.dump(hits, open(outfile, 'w'), indent=1)
    print(f'DONE probed={len(urls)} hits={len(hits)}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], *(sys.argv[3:4] or []))
