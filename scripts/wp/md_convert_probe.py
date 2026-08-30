#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Feasibility probe: convert novel body HTML to Markdown-ish paragraphs and
measure (a) how many files convert using only known constructs, (b) whether the
non-whitespace character stream survives identically (lossless proof), and
(c) how often hardwrap reflow fires.

Usage: md_convert_probe.py <N sample size> [seed]"""
import os, re, sys, html, random, unicodedata

ROOT = '/home/takano32/GitHub/ts-novels'

TAG_RE = re.compile(r'<[^>]+>')
SCRIPT_RE = re.compile(r'<script.*?</script>|<style.*?</style>|<!--.*?-->', re.S | re.I)
BOGUS_RE = re.compile(r'<![^>]*>', re.S)          # browser-equivalent bogus comment (<! ... up to first >)
FORM_RE = re.compile(r'<form.*?</form>', re.S | re.I)  # kansou footer forms = chrome
def preclean(body):
    """browser-equivalent pre-parse, applied identically to both sides"""
    body = SCRIPT_RE.sub('', body)
    body = BOGUS_RE.sub('', body)
    body = FORM_RE.sub('', body)
    return body
BODY_RE = re.compile(r'<body[^>]*>(.*?)</body>', re.S | re.I)
KNOWN_INLINE = re.compile(r'^(br|b|strong|i|em|font[^>]*|span[^>]*|center|div[^>]*|p[^>]*|hr[^>]*|a[^>]*|img[^>]*|ruby|rb|rt|rp|small|big|u|s|strike|blink|marquee[^>]*|body[^>]*|html|head|title|meta[^>]*|link[^>]*|!doctype[^>]*|blockquote[^>]*|h[1-6][^>]*|basefont[^>]*|tt|pre|dd|dl|dt|ul|ol|li|nobr|wbr|sup|sub|address|caption)$', re.I)
PUNCT_END = tuple('。！？」』…―‐-!?.）)]々〉》』】♪☆★ 　')

def normalize_chars(s):
    s = html.unescape(html.unescape(s))
    s = TAG_RE.sub('', s)
    s = ''.join(ch for ch in s if not ch.isspace())
    return unicodedata.normalize('NFC', s)

def convert(path):
    """returns (markdown, flags) or (None, reason)"""
    raw = open(path, encoding='utf-8', errors='strict').read()
    m = BODY_RE.search(raw)
    body = m.group(1) if m else raw
    body = preclean(body)
    # unknown-construct scan: tables/frames/forms => flag as manual
    unknown = set()
    for t in re.findall(r'<\s*([a-zA-Z!/][^>\s]*)', body):
        if not KNOWN_INLINE.match(t.lstrip('/').lower()):
            unknown.add(t.lower().strip('/'))
    # line-ize on <br> and <p>
    txt = re.sub(r'(?i)</p\s*>', '\n\n', body)
    txt = re.sub(r'(?i)<p[^>]*>', '\n\n', txt)
    txt = re.sub(r'(?i)<br[^>]*>', '\n', txt)
    txt = re.sub(r'(?i)<hr[^>]*>', '\n\n----\n\n', txt)
    txt = re.sub(r'(?is)<blockquote[^>]*>(.*?)</blockquote>', lambda m2: '\n\n' + '\n'.join('> ' + l for l in TAG_RE.sub('', m2.group(1)).strip().split('\n')) + '\n\n', txt)
    txt = re.sub(r'(?is)<h([1-6])[^>]*>(.*?)</h\1>', lambda m2: '\n\n' + '#' * int(m2.group(1)) + ' ' + TAG_RE.sub('', m2.group(2)).strip() + '\n\n', txt)
    # keep ruby/img as inline HTML, strip other tags
    keep = []
    def stash(m2):
        keep.append(m2.group(0))
        return f'\x00{len(keep)-1}\x00'
    txt = re.sub(r'(?is)<ruby.*?</ruby>|<img[^>]*>', stash, txt)
    txt = TAG_RE.sub('', txt)
    txt = html.unescape(html.unescape(txt))
    txt = re.sub(r'\x00(\d+)\x00', lambda m2: keep[int(m2.group(1))], txt)
    lines = [l.rstrip() for l in txt.split('\n')]
    # hardwrap detection: many mid-length lines not ending in punctuation
    content = [l for l in lines if l.strip()]
    if not content:
        return None, 'empty', set()
    nonpunct = sum(1 for l in content if 15 <= len(l.strip()) <= 60 and not l.strip().endswith(PUNCT_END))
    hardwrap = len(content) > 30 and nonpunct / len(content) > 0.45
    if hardwrap:
        out, buf = [], ''
        for l in lines:
            ls = l.strip()
            if not ls:
                if buf: out.append(buf); buf = ''
                out.append('')
            elif buf and not buf.endswith(PUNCT_END) and not ls.startswith(('　', '「', '『', '（', '＜', '"', "'")):
                buf += ls
            else:
                if buf: out.append(buf)
                buf = ls
        if buf: out.append(buf)
        lines = out
    # paragraphs: blank-line separated
    paras, buf = [], []
    for l in lines:
        if l.strip():
            buf.append(l.strip('\r'))
        else:
            if buf: paras.append('\n'.join(buf)); buf = []
    if buf: paras.append('\n'.join(buf))
    md = '\n\n'.join(paras)
    return md, ('hardwrap' if hardwrap else 'ok'), unknown

def main(n, seed=42):
    files = []
    for dirpath, dirnames, filenames in os.walk(os.path.join(ROOT, 'novel')):
        for fn in filenames:
            if fn.lower().endswith(('.htm', '.html')):
                fp = os.path.join(dirpath, fn)
                if os.path.getsize(fp) >= 3000:
                    files.append(fp)
    random.seed(seed)
    sample = random.sample(files, min(n, len(files)))
    stats = {'clean': 0, 'hardwrap': 0, 'flagged': 0, 'lossless_fail': 0, 'error': 0}
    flag_tags = {}
    fails = []
    for fp in sample:
        try:
            md, mode, unknown = convert(fp)
        except Exception as e:
            stats['error'] += 1; fails.append((fp, f'exc:{e}')); continue
        if md is None:
            stats['error'] += 1; continue
        raw0 = open(fp, encoding='utf-8').read()
        m0 = BODY_RE.search(raw0)
        orig_chars = normalize_chars(preclean(m0.group(1) if m0 else raw0))
        conv_chars = normalize_chars(re.sub(r'(?m)^(----|#+ |> )', '', md))
        if orig_chars != conv_chars:
            stats['lossless_fail'] += 1
            fails.append((os.path.relpath(fp, ROOT), f'diff {len(orig_chars)} vs {len(conv_chars)}'))
        if unknown:
            stats['flagged'] += 1
            for t in unknown: flag_tags[t] = flag_tags.get(t, 0) + 1
        elif mode == 'hardwrap':
            stats['hardwrap'] += 1
        else:
            stats['clean'] += 1
    print(f'sample={len(sample)} clean={stats["clean"]} hardwrap_reflowed={stats["hardwrap"]} '
          f'flagged_manual={stats["flagged"]} lossless_fail={stats["lossless_fail"]} error={stats["error"]}')
    print('flag tags:', sorted(flag_tags.items(), key=lambda x: -x[1])[:12])
    for f, r in fails[:10]:
        print('  FAIL', r, f)

if __name__ == '__main__':
    main(int(sys.argv[1]) if len(sys.argv) > 1 else 300, *(int(a) for a in sys.argv[2:3]))
