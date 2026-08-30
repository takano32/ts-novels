#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Sweep ALL CommonCrawl collections for given URLs via direct zipnum access
(no index.commoncrawl.org API): HTTP-range binary search on each collection's
cluster.idx, then range-GET only the cdx-*.gz blocks covering our domains.

Input: TSV url<TAB>repo_path. Output: JSONL of {url, repo_path, coll, filename,
offset, length, timestamp, status} for status-200 hits."""
import sys, os, re, json, gzip, time, urllib.request, urllib.parse
from urllib.parse import urlsplit, parse_qsl

DATA = 'https://data.commoncrawl.org/cc-index/collections'

def http(url, headers=None, timeout=90):
    req = urllib.request.Request(url, headers=headers or {})
    return urllib.request.urlopen(req, timeout=timeout)

def surt_domain(host):
    h = host.lower().split(':')[0]
    h = re.sub(r'^www\d*\.', '', h)
    return ','.join(reversed(h.split('.')))

def group_key(url):
    return surt_domain(urlsplit(url).netloc)

class RemoteIdx:
    """binary search over a sorted text file via HTTP ranges"""
    def __init__(self, url):
        self.url = url
        r = http(url, {'Range': 'bytes=0-0'})
        cr = r.headers.get('Content-Range', '')
        self.size = int(cr.split('/')[-1])
        self.cache = {}
    def read(self, off, ln):
        key = (off, ln)
        if key not in self.cache:
            r = http(self.url, {'Range': f'bytes={off}-{min(off+ln, self.size)-1}'})
            self.cache[key] = r.read()
        return self.cache[key]
    def line_at(self, pos, win=16384):
        off = max(0, pos - win // 2)
        buf = self.read(off, win)
        rel = pos - off
        s = buf.rfind(b'\n', 0, rel) + 1
        e = buf.find(b'\n', rel)
        if e == -1:
            e = len(buf)
        return buf[s:e].decode('utf-8', 'replace'), off + s
    def bisect(self, key):
        """byte offset of first line with linekey >= key"""
        lo, hi = 0, self.size
        while hi - lo > 8192:
            mid = (lo + hi) // 2
            line, start = self.line_at(mid)
            lk = line.split('\t')[0] if line else ''
            if lk < key:
                lo = mid
            else:
                hi = mid
        return max(0, lo - 4096)

def blocks_for_prefix(idx, prefix):
    """cdx blocks whose key-range may contain the prefix span"""
    start = idx.bisect(prefix)
    blocks, prev = [], None
    off = start
    guard = 0
    while off < idx.size and guard < 4000:
        buf = idx.read(off, 65536)
        for raw in buf.split(b'\n'):
            line = raw.decode('utf-8', 'replace')
            p = line.split('\t')
            if len(p) >= 4:
                k = p[0].split(' ')[0]
                blk = (p[1], int(p[2]), int(p[3]))
                if k > prefix + '~':
                    if prev:
                        blocks.append(prev)
                    return blocks
                if k >= prefix or (prev and prev != blk):
                    if prev and prev not in blocks:
                        blocks.append(prev)
                if k >= prefix and blk not in blocks:
                    blocks.append(blk)
                prev = blk
            guard += 1
        off += 65536
    if prev and prev not in blocks:
        blocks.append(prev)
    return blocks

def match_line(line, targets):
    """cdx line 'SURT TS {json}' vs target dict path->[(url,repo,qs)]"""
    sp = line.find(' {')
    if sp == -1:
        return None
    try:
        rec = json.loads(line[sp + 1:])
    except json.JSONDecodeError:
        return None
    if rec.get('status') != '200':
        return None
    u = urlsplit(rec.get('url', ''))
    cands = targets.get(u.path.lower())
    if not cands:
        return None
    got_q = sorted(parse_qsl(u.query, keep_blank_values=True))
    for (turl, repo, tq) in cands:
        tu = urlsplit(turl)
        if re.sub(r'^www\d*\.', '', u.netloc.lower().split(':')[0]) != \
           re.sub(r'^www\d*\.', '', tu.netloc.lower().split(':')[0]):
            continue
        if got_q == tq:
            return {'url': turl, 'repo_path': repo, 'timestamp': rec.get('timestamp'),
                    'status': rec.get('status'), 'filename': rec.get('filename'),
                    'offset': rec.get('offset'), 'length': rec.get('length')}
    return None

def main(tsv, outfile, coll_filter=None):
    targets_by_group = {}
    for line in open(tsv):
        url, repo = line.rstrip('\n').split('\t')
        g = group_key(url)
        u = urlsplit(url)
        tq = sorted(parse_qsl(u.query, keep_blank_values=True))
        targets_by_group.setdefault(g, {}).setdefault(u.path.lower(), []).append((url, repo, tq))
    with http('https://index.commoncrawl.org/collinfo.json') as r:
        colls = [c['id'] for c in json.load(r)]
    if coll_filter:
        colls = [c for c in colls if re.search(coll_filter, c)]
    print(f'groups={list(targets_by_group)} collections={len(colls)}', flush=True)
    out = open(outfile, 'a')
    for ci, coll in enumerate(colls):
        try:
            idx = RemoteIdx(f'{DATA}/{coll}/indexes/cluster.idx')
        except Exception as e:
            print(f'{coll}: no idx ({e})', flush=True)
            continue
        hits = 0
        for g, targets in targets_by_group.items():
            try:
                blocks = blocks_for_prefix(idx, g + ')')
            except Exception as e:
                print(f'{coll} {g}: idx err {e}', flush=True)
                continue
            for (fn, off, ln) in blocks[:12]:
                try:
                    r = http(f'{DATA}/{coll}/indexes/{fn}',
                             {'Range': f'bytes={off}-{off+ln-1}'})
                    text = gzip.decompress(r.read()).decode('utf-8', 'replace')
                except Exception:
                    continue
                for cl in text.splitlines():
                    h = match_line(cl, targets)
                    if h:
                        h['coll'] = coll
                        out.write(json.dumps(h, ensure_ascii=False) + '\n')
                        out.flush()
                        hits += 1
                        print(f'HIT {coll} {h["repo_path"]}', flush=True)
        print(f'[{ci+1}/{len(colls)}] {coll}: hits={hits}', flush=True)
        time.sleep(0.3)
    print('DONE', flush=True)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3] if len(sys.argv) > 3 else None)
