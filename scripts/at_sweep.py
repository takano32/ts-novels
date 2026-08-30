#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Sweep archive.today wildcard listings for given URL prefixes.

For each prefix, walks https://archive.md/<prefix>* listing pages (best-effort
pagination via the dated anchors) and records snapshot code -> original URL.
Polite: ~2.5s between requests; aborts a prefix on CAPTCHA/challenge markers.
Output: JSON {original_url: [snapshot_urls]}"""
import sys, re, time, json, urllib.request

UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
HOST = 'https://archive.md'

def get(url):
    req = urllib.request.Request(url, headers={'User-Agent': UA,
                                               'Accept-Language': 'ja,en;q=0.8'})
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read().decode('utf-8', 'replace')

URL_RE = re.compile(r'(https?://[^\s"<>\\]+)')

def sweep_prefix(prefix, out, maxpages=25):
    host = prefix.split('/')[2]
    url = f'{HOST}/{prefix}*'
    pages = 0
    while url and pages < maxpages:
        try:
            t = get(url)
        except Exception as e:
            print(f'  ERR {e} on {url}', flush=True)
            return
        if re.search(r'captcha|challenge-form|just a moment', t, re.I):
            print(f'  CAPTCHA wall on {prefix}', flush=True)
            return
        found = 0
        for orig in set(URL_RE.findall(t)):
            orig = orig.rstrip('.,);\'')
            try:
                oh = orig.split('/')[2]
            except IndexError:
                continue
            if oh == host and orig.count('/') >= 3 and not orig.endswith('/*'):
                if orig not in out:
                    out[orig] = True
                    found += 1
        pages += 1
        m = re.search(r'href="(https://archive\.md/[^"]*offset=\d+[^"]*)"', t)
        nurl = m.group(1).replace('&amp;', '&') if m else None
        print(f'  {prefix} page {pages}: +{found} (total {len(out)})', flush=True)
        url = nurl
        time.sleep(2.5)

def main(outfile, prefixes):
    out = {}
    for p in prefixes:
        print(f'== {p}', flush=True)
        sweep_prefix(p, out)
        time.sleep(3)
    json.dump(out, open(outfile, 'w'), ensure_ascii=False, indent=1)
    print(f'DONE prefixes={len(prefixes)} originals={len(out)}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2:])
