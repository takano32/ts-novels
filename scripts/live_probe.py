#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Probe a live server for files still missing from the mirror.

Input: text file of lines "<url>\t<repo-relative path>". Fetches each URL
(keep-alive, redirects followed), rejects soft-404s (adsbygoogle ad page on
www14.big.or.jp, ~5.8KB) and real errors, saves good bodies to <stage>/<path>."""
import sys, os, time, http.client
from urllib.parse import urlsplit, quote

def main(listfile, stage):
    items = []
    for line in open(listfile):
        line = line.rstrip('\n')
        if not line or line.startswith('#'):
            continue
        url, path = line.split('\t')
        items.append((url, path))
    conns = {}
    ok = bad = 0
    for i, (url, path) in enumerate(items):
        sp = urlsplit(url)
        key = (sp.scheme, sp.netloc)
        body = None
        for attempt in range(3):
            try:
                if conns.get(key) is None:
                    cls = http.client.HTTPSConnection if sp.scheme == 'https' else http.client.HTTPConnection
                    conns[key] = cls(sp.netloc, timeout=45)
                c = conns[key]
                p = quote(sp.path, safe="/:~@+,;$!*'()") + (('?' + sp.query) if sp.query else '')
                c.request('GET', p, headers={'User-Agent': 'Mozilla/5.0 (mirror-restore)'})
                r = c.getresponse()
                b = r.read()
                if r.status == 200:
                    body = b
                break
            except Exception:
                try:
                    conns[key].close()
                except Exception:
                    pass
                conns[key] = None
                time.sleep(3)
        if body and not (4500 < len(body) < 7500 and b'adsbygoogle' in body):
            out = os.path.join(stage, path)
            os.makedirs(os.path.dirname(out), exist_ok=True)
            open(out, 'wb').write(body)
            ok += 1
        else:
            bad += 1
        if (i + 1) % 100 == 0:
            print(f'  {i+1}/{len(items)} ok={ok}', flush=True)
        time.sleep(0.35)
    print(f'DONE ok={ok} miss={bad}', flush=True)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
