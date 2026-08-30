#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Reverse-map audit missing targets (repo paths) to their original URLs.

Handles the known @-mangle families (bbs log/res, noteky, weblink, manage2);
unrecognized @ names and junk classes are skipped. Output TSV: url<TAB>repo_path"""
import sys, re, json

TREE_HOST = [
    ('~yays/', 'http://www14.big.or.jp/~yays/'),
    ('~ezpe/', 'http://www2.tomato.ne.jp/~ezpe/'),
    ('~ts/', 'http://www.novels.jp/~ts/'),
    ('~yaji/', 'http://www.novels.jp/~yaji/'),
    ('~bbs/', 'http://www.novels.jp/~bbs/'),
    ('~yaopinion/', 'http://www.novels.jp/~yaopinion/'),
    ('www2.sts.co.jp/', 'http://www2.sts.co.jp/'),
    ('ts.novels.name/', 'http://ts.novels.name/'),
    ('kirika.novels.name/', 'http://kirika.novels.name/'),
    ('www.novels.name/', 'http://www.novels.name/'),
    ('utanotuki.novels.name/', 'http://utanotuki.novels.name/'),
    ('ts.raa0121.info/', 'http://ts.raa0121.info/'),
    ('ts-novels.jp/', 'http://ts-novels.jp/'),
]
JUNK = re.compile(r'Local Settings|files/|^C:|[<>()"]|^//|�|^cdn-cgi|^pagead|gaJsHost')

def unmangle(path):
    """repo filename -> original path(+query) for known families; None if opaque"""
    if '@' not in path:
        return path
    m = re.match(r'^(.*/)?([^/@]+)@([^/]*)\.(\w+)$', path)
    if not m:
        return None
    d, stem, q, ext = m.group(1) or '', m.group(2), m.group(3), m.group(4)
    base = f'{d}{stem}.{ext}'
    for pat, sub in [
        (r'^log_([A-Za-z0-9_\-]+)$', r'log=\1'),
        (r'^res_([0-9]+)_log_([A-Za-z0-9_\-]+)$', r'res=\1&log=\2'),
        (r'^res_([0-9]+)$', r'res=\1'),
        (r'^log_([A-Za-z0-9_\-]+)_res_([0-9]+)$', r'log=\1&res=\2'),
        (r'^karte_([A-Za-z0-9_]+?)_p_([0-9]+)$', r'karte=\1&p=\2'),
        (r'^c_noteread_f_([A-Za-z0-9]*)_id_([A-Za-z0-9]+)_ff_([a-z]+)$', r'c=noteread&f=\1&id=\2&ff=\3'),
        (r'^ran\+([0-9]+)$', r'ran+\1'),
        (r'^mode_([a-z]+)$', r'mode=\1'),
        (r'^pg_([0-9]+)$', r'pg=\1'),
        (r'^page_([0-9]+)$', r'page=\1'),
    ]:
        m2 = re.match(pat, q)
        if m2:
            return base + '?' + m2.expand(sub)
    return None

def main(missing_json, out_tsv, min_refs=1):
    d = json.load(open(missing_json))
    rows = []
    for t, info in d.items():
        if info['n'] < min_refs or JUNK.search(t):
            continue
        for prefix, host in TREE_HOST:
            if t.startswith(prefix):
                rest = t[len(prefix):] if not prefix.startswith('www2.sts') else t
                up = unmangle(rest)
                if up is not None:
                    rows.append((host + up, t))
                break
        else:
            if not t.startswith('~'):
                up = unmangle(t)
                if up is not None:
                    rows.append(('http://ts.novels.jp/' + up, t))
    with open(out_tsv, 'w') as f:
        for u, t in sorted(set(rows)):
            f.write(f'{u}\t{t}\n')
    print(f'reversed={len(set(rows))} skipped_opaque_or_junk={len(d)-len(set(rows))}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], int(sys.argv[3]) if len(sys.argv) > 3 else 1)
