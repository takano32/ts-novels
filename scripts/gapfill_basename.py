#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Cross-generation gap fill: for internal-link targets still missing under the
root novel/ tree, look for a UNIQUE same-basename file in the yays/ezpe
generation trees and copy it into place (the same technique as the round-1
"222 direct gap fills", automated with a uniqueness guard).

Reads missing_targets.json produced by audit_full.py. Dry-run by default;
--apply copies files."""
import os, re, sys, json, shutil, collections

ROOT = '/home/takano32/GitHub/ts-novels'
DONOR_TREES = ['~yays/library/novel', '~yays/library', '~ezpe']

def build_index():
    idx = collections.defaultdict(list)
    for tree in DONOR_TREES:
        base = os.path.join(ROOT, tree)
        if not os.path.isdir(base):
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            for fn in filenames:
                idx[fn.lower()].append(os.path.join(dirpath, fn))
    return idx

def type_ok(path, data):
    ext = path.lower().rsplit('.', 1)[-1]
    if ext in ('gif',):
        return data[:4] in (b'GIF8',)
    if ext in ('jpg', 'jpeg'):
        return data[:2] == b'\xff\xd8'
    if ext in ('png',):
        return data[:4] == b'\x89PNG'
    if ext in ('htm', 'html', 'shtml', 'cgi'):
        return b'<' in data[:200]
    return True

def main(missing_json, apply=False):
    d = json.load(open(missing_json))
    idx = build_index()
    copied, ambiguous, nomatch, badtype = [], 0, 0, 0
    seen_dst = set()
    for t in d:
        if not t.startswith('novel/') or t in seen_dst:
            continue
        if os.path.exists(os.path.join(ROOT, t)):
            continue
        bn = os.path.basename(t).lower()
        if not bn or '@' in bn:
            continue
        cands = idx.get(bn, [])
        # de-dup identical content
        uniq = {}
        for c in cands:
            uniq.setdefault(os.path.getsize(c), c)
        if len(cands) == 0:
            nomatch += 1
            continue
        if len(uniq) > 1:
            ambiguous += 1
            continue
        src = cands[0]
        data = open(src, 'rb').read()
        if not type_ok(t, data):
            badtype += 1
            continue
        seen_dst.add(t)
        copied.append((t, os.path.relpath(src, ROOT)))
        if apply:
            dst = os.path.join(ROOT, t)
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            shutil.copyfile(src, dst)
    print(f'copied={len(copied)} ambiguous={ambiguous} nomatch={nomatch} badtype={badtype} apply={apply}')
    for t, s in copied[:40]:
        print(f'  {t}  <=  {s}')

if __name__ == '__main__':
    main(sys.argv[1], apply='--apply' in sys.argv)
