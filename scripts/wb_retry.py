#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Retry failed Wayback fetches by walking each URL's full capture list
(newest first) from the CDX dumps until one replays successfully."""
import json, os, sys, collections

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from wb_fetch import fetch_one
from cdx_recover import repo_path_for

def main(dumpdir, failed_json, stage):
    failed = json.load(open(failed_json))
    want = {}
    for it in failed:
        if not os.path.isfile(os.path.join(stage, it['path'])):
            want[it['url']] = it['path']
    caps = collections.defaultdict(list)
    for fn in os.listdir(dumpdir):
        if not fn.endswith('.cdx'):
            continue
        for line in open(os.path.join(dumpdir, fn), encoding='utf-8', errors='replace'):
            f = line.split()
            if len(f) >= 6 and f[3] == '200' and f[1] in want:
                caps[f[1]].append(f[2])
    conns = {}
    ok = 0
    for url, path in want.items():
        for ts in sorted(caps.get(url, []), reverse=True):
            body, err = fetch_one(conns, ts, url)
            if body:
                out = os.path.join(stage, path)
                try:
                    os.makedirs(os.path.dirname(out), exist_ok=True)
                    open(out, 'wb').write(body)
                    ok += 1
                except OSError:
                    pass
                break
    print(f'retried={len(want)} recovered={ok}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3])
