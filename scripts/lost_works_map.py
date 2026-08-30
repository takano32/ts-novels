#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Build the 'lost works by author' map: for every audit-missing novel/ page
target, find its catalog entry (lib*.html) and extract title / author /
homepage / kansou slug. Output JSON grouped by author."""
import os, re, sys, json, html

ROOT = '/home/takano32/GitHub/ts-novels'

def main(missing_json, outfile):
    d = json.load(open(missing_json))
    targets = [t for t in d
               if t.startswith('novel/') and re.search(r'\.(html?|txt)$', t)
               and 'Local Settings' not in t]
    # index catalog tables by contained hrefs
    lib_files = [f for f in os.listdir(ROOT)
                 if re.match(r'lib(\d|-index|rary).*\.html$|library\.html$', f)]
    entries = []  # (hrefs_set, title, author, homepage, kansou, libfile)
    for fn in lib_files:
        t = open(os.path.join(ROOT, fn), encoding='utf-8', errors='replace').read()
        for tab in re.findall(r'<TABLE BORDER=1.*?</TABLE>', t, re.S | re.I):
            hrefs = {h.split('#')[0] for h in re.findall(r'href="(novel/[^"]+)"', tab, re.I)}
            if not hrefs:
                continue
            tm = re.search(r'<A href="novel/[^"]+"[^>]*>(.*?)</a>', tab, re.I | re.S)
            am = re.search(r'<B>(?:<A href="mailto:[^"]*">)?([^<]+)(?:</a>)?</B>\s*さん', tab, re.I)
            hm = re.search(r'href="(https?://[^"]+)"[^>]*>\s*Homepage', tab, re.I)
            km = re.search(r'bbs@log_([A-Za-z0-9_\-]+)\.cgi', tab)
            entries.append((hrefs, html.unescape((tm.group(1) if tm else '').strip()),
                            html.unescape((am.group(1) if am else '').strip()),
                            hm.group(1) if hm else None,
                            km.group(1) if km else None, fn))
    by_author = {}
    unmatched = []
    for tgt in targets:
        hit = None
        for (hrefs, title, author, hp, ks, fn) in entries:
            if tgt in hrefs:
                hit = (title, author, hp, ks, fn)
                break
        if not hit:
            # index pages: 2-col rows
            base = os.path.basename(tgt)
            for fn in lib_files:
                if 'index' not in fn:
                    continue
                t = open(os.path.join(ROOT, fn), encoding='utf-8', errors='replace').read()
                m = re.search(r'<A HREF="' + re.escape(tgt) + r'"[^>]*>(.*?)</A></TD><TD[^>]*>(?:<[^>]+>)*([^<]+)',
                              t, re.I | re.S)
                if m:
                    hit = (html.unescape(m.group(1).strip()), html.unescape(m.group(2).strip()),
                           None, None, fn)
                    break
        if hit:
            title, author, hp, ks, fn = hit
            a = by_author.setdefault(author or '?', {'author': author, 'kansou_slug': None,
                                                     'homepages': [], 'lost': []})
            if ks and not a['kansou_slug']:
                a['kansou_slug'] = ks
            if hp and hp not in a['homepages']:
                a['homepages'].append(hp)
            a['lost'].append({'target': tgt, 'title': title, 'refs': d[tgt]['n'], 'lib': fn})
        else:
            unmatched.append(tgt)
    out = sorted(by_author.values(), key=lambda a: -len(a['lost']))
    json.dump({'authors': out, 'unmatched': unmatched}, open(outfile, 'w'),
              ensure_ascii=False, indent=1)
    print(f'catalog-linked lost pages={sum(len(a["lost"]) for a in out)} '
          f'authors={len(out)} unmatched(not in catalog)={len(unmatched)}')
    for a in out[:15]:
        print(f'  {a["author"]:14s} lost={len(a["lost"]):3d} hp={bool(a["homepages"])} slug={a["kansou_slug"]}')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
