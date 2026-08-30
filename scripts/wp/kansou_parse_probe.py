#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Feasibility probe: parse the ~ts/kansou MiniBBS/Crocus snapshots (bbs@log_*.cgi
and bbs@res_*.cgi) into structured posts, to size a WordPress board-archive
import. Reports per-file parse rates and the deduplicated post count."""
import os, re, sys, html, collections

ROOT = '/home/takano32/GitHub/ts-novels'
KANSOU = os.path.join(ROOT, '~ts', 'kansou')

# post header: [no] subject then ▽ date ▽ poster
HEAD_RE = re.compile(
    r'\[(\d+)\]</font>\s*<font[^>]*>\s*([^<]*?)\s*</font>.{0,200}?'
    r'▽(?:</strong>)?\s*(\d{4}/\d{2}/\d{2})\s*\(([^)]+)\)\s*(\d{2}:\d{2}:\d{2})\s*(?:<strong>)?▽(?:</strong>)?\s*([^<]+)',
    re.S | re.I)

def parse_file(fp):
    try:
        t = open(fp, encoding='utf-8', errors='strict').read()
    except UnicodeDecodeError:
        return None, 'not-utf8'
    if '記事はありません' in t:
        return [], 'empty-board'
    posts = []
    for m in HEAD_RE.finditer(t):
        no, subj, d, wd, tm, poster = m.groups()
        posts.append({'no': int(no), 'subject': html.unescape(subj.strip()),
                      'date': f'{d} {tm}', 'poster': html.unescape(poster.strip())})
    return posts, 'ok'

def main():
    logs = sorted(f for f in os.listdir(KANSOU) if f.startswith('bbs@log_') and f.endswith('.cgi'))
    ress = sorted(f for f in os.listdir(KANSOU) if f.startswith('bbs@res_'))
    log_posts = res_posts = 0
    zero_logs, boards = 0, 0
    uniq = set()
    for f in logs:
        posts, st = parse_file(os.path.join(KANSOU, f))
        if st == 'empty-board':
            continue
        boards += 1
        if not posts:
            zero_logs += 1
            continue
        log_posts += len(posts)
        for p in posts:
            uniq.add((p['date'], p['poster']))
    res_ok = 0
    for f in ress:
        posts, st = parse_file(os.path.join(KANSOU, f))
        if posts:
            res_ok += 1
            res_posts += len(posts)
            for p in posts:
                uniq.add((p['date'], p['poster']))
    print(f'log files={len(logs)} boards_with_posts={boards} zero_parse_logs={zero_logs} log_posts={log_posts}')
    print(f'res files={len(ress)} res_parsed={res_ok} res_posts={res_posts}')
    print(f'unique posts (date+poster dedup)={len(uniq)}')
    # sample output
    posts, _ = parse_file(os.path.join(KANSOU, 'bbs@log_johdan.cgi'))
    for p in posts[:3]:
        print('  sample:', p)

if __name__ == '__main__':
    main()
