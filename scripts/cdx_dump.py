#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Dump Wayback CDX indexes for all site-family hosts (uncollapsed, paginated
via resumeKey). Output: one .cdx text file per target in the given out dir.
Fields: urlkey original timestamp statuscode mimetype length"""
import http.client, sys, os, time, urllib.parse

TARGETS = [
    ('novels_jp',      'novels.jp',                 'domain'),
    ('novels_name',    'novels.name',               'domain'),
    ('yays',           'www14.big.or.jp/~yays',     'prefix'),
    ('ezpe',           'www2.tomato.ne.jp/~ezpe',   'prefix'),
    ('raa0121',        'ts.raa0121.info',           'host'),
    ('ts_novels_jp',   'ts-novels.jp',              'domain'),
    ('sts_yaji',       'www2.sts.co.jp/~yaji',      'prefix'),
    ('sts_ts',         'www2.sts.co.jp/~ts',        'prefix'),
]
FL = 'urlkey,original,timestamp,statuscode,mimetype,length'

def fetch(conn_holder, path, retries=6):
    for attempt in range(retries):
        try:
            if conn_holder[0] is None:
                conn_holder[0] = http.client.HTTPSConnection('web.archive.org', timeout=120)
            conn_holder[0].request('GET', path, headers={'User-Agent': 'ts-novels-mirror-audit'})
            r = conn_holder[0].getresponse()
            body = r.read()
            if r.status == 200:
                return body
            print(f'  HTTP {r.status}, retry {attempt+1}', flush=True)
        except Exception as e:
            print(f'  {type(e).__name__}: {e}, retry {attempt+1}', flush=True)
            try: conn_holder[0].close()
            except Exception: pass
            conn_holder[0] = None
        time.sleep(15 * (attempt + 1))
    return None

def main(outdir):
    os.makedirs(outdir, exist_ok=True)
    conn = [None]
    for name, url, mt in TARGETS:
        out = os.path.join(outdir, name + '.cdx')
        if os.path.exists(out) and os.path.getsize(out) > 0:
            print(f'{name}: exists, skip', flush=True)
            continue
        lines = 0
        resume = None
        with open(out + '.tmp', 'w') as f:
            while True:
                q = {'url': url, 'matchType': mt, 'output': 'text', 'fl': FL,
                     'limit': '150000', 'showResumeKey': 'true'}
                if resume:
                    q['resumeKey'] = resume
                path = '/cdx/search/cdx?' + urllib.parse.urlencode(q)
                body = fetch(conn, path)
                if body is None:
                    print(f'{name}: FAILED page after retries', flush=True)
                    sys.exit(1)
                text = body.decode('utf-8', 'replace')
                chunk = text.rstrip('\n').split('\n')
                # resumeKey is separated from data by a blank line
                resume = None
                if len(chunk) >= 2 and chunk[-2] == '':
                    resume = chunk[-1]
                    chunk = chunk[:-2]
                for ln in chunk:
                    if ln:
                        f.write(ln + '\n')
                        lines += 1
                print(f'{name}: {lines} lines so far, resume={bool(resume)}', flush=True)
                if not resume:
                    break
                time.sleep(2)
        os.rename(out + '.tmp', out)
        print(f'{name}: DONE {lines} lines', flush=True)
        time.sleep(3)

if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'cdx_dumps')
