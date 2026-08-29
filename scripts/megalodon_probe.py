#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Probe ウェブ魚拓 (megalodon.jp) for captures of given URLs.

Input: text file of original URLs, one per line. For each, GET
megalodon.jp/?url=<URL> and scan the response for /YYYY-MMDD-HHMM-SS/ capture
links. Hits are printed and written to <out>.json. ~1.2s between requests."""
import sys, re, time, json, http.client, urllib.parse

CAP_RE = re.compile(r'(?:megalodon\.jp)?/(20\d\d-\d{4}-\d{4}-\d\d)/([^"\'<> ]+)')

def main(listfile, outfile):
    urls = [l.strip() for l in open(listfile) if l.strip() and not l.startswith('#')]
    conn = None
    hits = {}
    for i, u in enumerate(urls):
        for attempt in range(3):
            try:
                if conn is None:
                    conn = http.client.HTTPSConnection('megalodon.jp', timeout=45)
                conn.request('GET', '/?url=' + urllib.parse.quote(u, safe=''),
                             headers={'User-Agent': 'Mozilla/5.0 (mirror-restore)'})
                r = conn.getresponse()
                body = r.read().decode('utf-8', 'replace')
                caps = sorted(set(CAP_RE.findall(body)))
                caps = [c for c in caps if c[1].split('/')[0] in u]
                if caps:
                    hits[u] = [f'https://megalodon.jp/{ts}/{rest}' for ts, rest in caps]
                    print(f'HIT {u} -> {len(caps)} captures', flush=True)
                break
            except Exception as e:
                try:
                    conn.close()
                except Exception:
                    pass
                conn = None
                time.sleep(5)
        if (i + 1) % 50 == 0:
            print(f'  probed {i+1}/{len(urls)}, hits={len(hits)}', flush=True)
        time.sleep(1.15)
    json.dump(hits, open(outfile, 'w'), indent=1)
    print(f'DONE probed={len(urls)} hits={len(hits)}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
