#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fix wrong-depth / aliased internal links left by earlier conversion passes.

For every internal link whose computed repo target does not exist, try a small
set of structured corrections; rewrite the href in the file ONLY when exactly
one correction resolves to an existing file. Run with --apply to write changes
(default is dry-run)."""
import os, re, sys, html, collections
from urllib.parse import urlsplit, unquote

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from audit_full import ROOT, ATTR_RE, classify, exists, toptree, mangle_query

def dedup_segments(path):
    """collapse one adjacent duplicated segment: a/novel/novel/b -> a/novel/b"""
    parts = path.split('/')
    for i in range(len(parts) - 1):
        if parts[i] and parts[i] == parts[i + 1]:
            return '/'.join(parts[:i] + parts[i + 1:])
    return None

def candidates_for(target):
    """alternate repo paths that might be the real location of a missing target"""
    cands = set()
    d = dedup_segments(target)
    if d:
        cands.add(d)
    if target.startswith('cgi-bin/novel/'):
        cands.add(target[len('cgi-bin/'):])
    if target.startswith('cgi-bin/library'):
        cands.add(target[len('cgi-bin/'):])
    if target.startswith('~ts/kansou/novel/'):
        cands.add(target[len('~ts/kansou/'):])
    if target.startswith('~ts/novel/'):
        cands.add(target[len('~ts/'):])
    if target.startswith('~yaji/') or target == '~yaji/':
        cands.add('www2.sts.co.jp/' + target.rstrip('/') + '/')
        cands.add('www2.sts.co.jp/' + target)
    if target.startswith('~yays/') and not target.startswith('~yays/library/'):
        cands.add('~yays/library/' + target[len('~yays/'):])
    if '_amp_' in target:
        cands.add(target.replace('_amp_', '_'))
    if 'charset_UTF-8' in target:
        cands.add(target.replace('charset_UTF-8', 'charset_Shift_JIS'))
        cands.add(target.replace('_amp_', '_').replace('charset_UTF-8', 'charset_Shift_JIS'))
    # depth slip: try dropping ONE leading directory component
    parts = target.split('/')
    if len(parts) > 2:
        cands.add('/'.join(parts[1:]))
    cands.discard(target)
    return [c for c in cands if c and not c.startswith('..')]

def relhref(from_rel_file, to_repo_path):
    """relative href from a repo file to a repo target path"""
    base = os.path.dirname(from_rel_file)
    r = os.path.relpath(to_repo_path.rstrip('/'), base or '.')
    if to_repo_path.endswith('/'):
        r += '/'
    return r.replace(os.sep, '/')

def main(apply=False):
    n_files = n_links = 0
    fixed = collections.Counter()
    ambiguous = 0
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in ('.git', 'scripts')]
        for fn in filenames:
            if not fn.lower().endswith(('.htm', '.html', '.cgi', '.shtml')):
                continue
            fp = os.path.join(dirpath, fn)
            rel = os.path.relpath(fp, ROOT)
            reldir = os.path.dirname(rel)
            tree = toptree(rel)
            try:
                text = open(fp, encoding='utf-8').read()
            except (UnicodeDecodeError, OSError):
                continue  # only fix converted (UTF-8) files
            repls = []  # (start, end, new_url_text)
            for m in ATTR_RE.finditer(text):
                gi = next(i for i, g in enumerate(m.groups()) if g is not None)
                url = m.groups()[gi]
                kind, target = classify(url, reldir, tree)
                if kind != 'internal' or exists(target):
                    continue
                hits = [c for c in candidates_for(target) if exists(c)]
                if len(hits) != 1:
                    ambiguous += len(hits) > 1
                    continue
                sp = urlsplit(html.unescape(url.strip()))
                new = relhref(rel, hits[0])
                if sp.fragment:
                    new += '#' + sp.fragment
                s, e = m.span(gi + 1)
                repls.append((s, e, new))
                fixed[f'{target} -> {hits[0]}'] += 1
                n_links += 1
            if repls:
                n_files += 1
                if apply:
                    for s, e, new in sorted(repls, reverse=True):
                        text = text[:s] + new + text[e:]
                    open(fp, 'w', encoding='utf-8').write(text)
    print(f'files_changed={n_files} links_fixed={n_links} ambiguous_skipped={ambiguous} apply={apply}')
    for k, v in fixed.most_common(30):
        print(f'  {v:4d}  {k}')

if __name__ == '__main__':
    main(apply='--apply' in sys.argv)
