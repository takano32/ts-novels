#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Sweep every author Homepage URL found in the catalog against Wayback CDX,
and match captured files against the lost-work basenames.

Stage 1 (--collect): extract unique Homepage URLs from lib*.html into JSON.
Stage 2 (--sweep):   CDX prefix query per homepage (keep-alive), store per-site
                     url lists.
Stage 3 (--match):   compare against basenames of missing novel/ targets;
                     emit candidate (homepage, captured_url, missing_target)."""
import os, re, sys, json, time, http.client, urllib.parse

ROOT = '/home/takano32/GitHub/ts-novels'

def collect(outfile):
    urls = set()
    for fn in os.listdir(ROOT):
        if not re.match(r'lib.*\.html$|library\.html$', fn):
            continue
        t = open(os.path.join(ROOT, fn), encoding='utf-8', errors='replace').read()
        for m in re.finditer(r'href=["\']?(https?://[^"\'>\s]+)["\']?[^>]*>\s*Homepage', t, re.I):
            urls.add(m.group(1).strip())
    urls = {u for u in urls if 'novels.jp' not in u and 'archive.org' not in u}
    json.dump(sorted(urls), open(outfile, 'w'), indent=1)
    print(f'homepages={len(urls)}')

def sweep(infile, outfile):
    urls = json.load(open(infile))
    conn = [None]
    out = {}
    if os.path.exists(outfile):
        out = json.load(open(outfile))
    for i, u in enumerate(urls):
        if u in out:
            continue
        sp = urllib.parse.urlsplit(u)
        prefix = sp.netloc + sp.path
        q = urllib.parse.urlencode({'url': prefix, 'matchType': 'prefix',
                                    'output': 'text', 'collapse': 'urlkey',
                                    'fl': 'original,timestamp,statuscode,length',
                                    'filter': 'statuscode:200', 'limit': '20000'})
        for attempt in range(4):
            try:
                if conn[0] is None:
                    conn[0] = http.client.HTTPSConnection('web.archive.org', timeout=90)
                conn[0].request('GET', '/cdx/search/cdx?' + q,
                                headers={'User-Agent': 'ts-novels-author-sweep'})
                r = conn[0].getresponse()
                body = r.read().decode('utf-8', 'replace')
                if r.status == 200:
                    out[u] = [l.split() for l in body.splitlines() if l]
                    break
                time.sleep(10)
            except Exception:
                try:
                    conn[0].close()
                except Exception:
                    pass
                conn[0] = None
                time.sleep(5)
        else:
            out[u] = None
        if (i + 1) % 10 == 0:
            json.dump(out, open(outfile, 'w'))
            print(f'  {i+1}/{len(urls)} swept', flush=True)
        time.sleep(0.5)
    json.dump(out, open(outfile, 'w'))
    ok = sum(1 for v in out.values() if v)
    total = sum(len(v) for v in out.values() if v)
    print(f'DONE sites={len(out)} with_captures={ok} captured_urls={total}')

STOPWORDS = {'index.html', 'index.htm', 'title.html', 'title.htm', 'main.html',
             'top.html', 'menu.html', 'link.html', 'profile.html'}

def match(sweepfile, missingfile, outfile):
    d = json.load(open(missingfile))
    wanted = {}
    for t, info in d.items():
        if t.startswith('novel/') and re.search(r'\.(html?|txt)$', t):
            bn = os.path.basename(t).lower()
            if bn not in STOPWORDS and len(bn) > 8:
                wanted.setdefault(bn, []).append(t)
    sw = json.load(open(sweepfile))
    cands = []
    for site, rows in sw.items():
        if not rows:
            continue
        for row in rows:
            url = row[0]
            bn = os.path.basename(urllib.parse.urlsplit(url).path).lower()
            if bn in wanted:
                for t in wanted[bn]:
                    cands.append({'homepage': site, 'captured': url,
                                  'ts': row[1] if len(row) > 1 else '',
                                  'missing_target': t})
    json.dump(cands, open(outfile, 'w'), ensure_ascii=False, indent=1)
    print(f'lost_basenames={len(wanted)} candidates={len(cands)}')
    for c in cands[:25]:
        print(' ', c['missing_target'], '<=', c['captured'])

if __name__ == '__main__':
    mode = sys.argv[1]
    if mode == '--collect':
        collect(sys.argv[2])
    elif mode == '--sweep':
        sweep(sys.argv[2], sys.argv[3])
    elif mode == '--match':
        match(sys.argv[2], sys.argv[3], sys.argv[4])
