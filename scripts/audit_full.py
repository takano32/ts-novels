#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Full link audit v2: enumerate internal links, resolve to repo path,
report resolved vs missing, dump missing targets with referrer counts."""
import os, re, sys, json, html, collections
from urllib.parse import urlsplit, unquote

ROOT = '/home/takano32/GitHub/ts-novels'

# host(+path prefix) -> repo top dir ('' = repo root)
HOSTS = {
    'ts.novels.jp': '', 'novels.jp': '', 'www.novels.jp': '',
    'www14.big.or.jp/~yays': '~yays',
    'www2.tomato.ne.jp/~ezpe': '~ezpe',
    'ts.novels.name': 'ts.novels.name',
    'kirika.novels.name': 'kirika.novels.name',
    'ts.raa0121.info': 'ts.raa0121.info',
    'www.novels.name': 'www.novels.name',
    'novels.name': 'www.novels.name',
    'ts-novels.jp': 'ts-novels.jp', 'www.ts-novels.jp': 'ts-novels.jp',
    'www2.sts.co.jp/~yaji': 'www2.sts.co.jp/~yaji',
}
TOPDIRS = ['~yays', '~ezpe', '~ts', 'ts.novels.name', 'kirika.novels.name',
           'ts.raa0121.info', 'www.novels.name', 'ts-novels.jp',
           'www2.sts.co.jp/~yaji', 'ts.raa0121.info']

ATTR_RE = re.compile(r'''(?:href|src|action|background)\s*=\s*(?:"([^"<>]*)"|'([^'<>]*)'|([^\s>"'<>]+))''', re.I)
EMAIL_RE = re.compile(r'^[\w.+-]+@[\w-]+(\.[\w-]+)+$')

def mangle_query(path, query):
    d, base = os.path.split(path)
    if '.' in base:
        stem, ext = base.rsplit('.', 1)
        ext = '.' + ext
    else:
        stem, ext = base, ''
    q = query.replace('%', '').replace('=', '_').replace('&', '_')
    return os.path.join(d, f'{stem}@{q}{ext}')

def exists(repopath):
    p = os.path.join(ROOT, repopath)
    if repopath.endswith('/'):
        return (os.path.isfile(p + 'index.html') or os.path.isfile(p + 'index.htm')
                or os.path.isdir(p.rstrip('/')))
    if os.path.isfile(p):
        return True
    if os.path.isdir(p):
        return True  # dir link without trailing slash
    return False

def toptree(rel):
    """which host tree a repo file belongs to ('' = ts.novels.jp root)"""
    for t in TOPDIRS:
        if rel == t or rel.startswith(t + '/'):
            return t
    return ''

def host_lookup(netloc, path, query):
    hostpath = netloc.lower() + unquote(path).replace('%7e', '~').replace('%7E', '~')
    for hp, top in sorted(HOSTS.items(), key=lambda x: -len(x[0])):
        if '/' in hp:
            if hostpath == hp or hostpath.startswith(hp + '/'):
                rest = hostpath[len(hp):].lstrip('/')
            else:
                continue
        else:
            if netloc.lower() != hp:
                continue
            rest = unquote(path).lstrip('/')
        repopath = os.path.join(top, rest) if top else rest
        if (path.endswith('/') or not rest) and not query:
            repopath = repopath.rstrip('/') + '/'
        repopath = os.path.normpath(repopath) + ('/' if repopath.endswith('/') else '')
        if query:
            repopath = mangle_query(repopath.rstrip('/'), query)
        return ('internal', repopath)
    return ('external', None)

def classify(url, reldir, tree):
    u = html.unescape(url.strip())
    if not u or u.startswith('#'):
        return ('skip', None)
    low = u.lower()
    if low.startswith(('mailto:', 'javascript:', 'data:', 'about:', 'irc:', 'news:', 'ftp:', 'file:')):
        return ('skip', None)
    if EMAIL_RE.match(u):
        return ('skip', None)
    try:
        sp = urlsplit(u)
    except ValueError:
        return ('external', None)
    if sp.scheme in ('http', 'https') or (not sp.scheme and sp.netloc):
        return host_lookup(sp.netloc, sp.path, sp.query)
    if sp.scheme:
        return ('skip', None)
    path = unquote(sp.path)
    if not path:
        return ('skip', None)
    if path.startswith('/'):
        # root-relative within the file's host tree
        if tree in ('~yays', '~ezpe', 'www2.sts.co.jp/~yaji'):
            # server root is above the user dir; only /~user/... maps inside
            p = path.replace('%7E', '~').replace('%7e', '~')
            userbit = '/' + os.path.basename(tree) + '/'
            if p.startswith(userbit):
                target = os.path.join(tree, p[len(userbit):])
            else:
                return ('external', None)
        else:
            target = os.path.join(tree, path.lstrip('/')) if tree else path.lstrip('/')
        target = os.path.normpath(target) + ('/' if path.endswith('/') else '')
    else:
        target = os.path.normpath(os.path.join(reldir, path))
        if path.endswith('/'):
            target += '/'
        if target.startswith('..'):
            return ('external', None)
    if sp.query:
        target = mangle_query(target.rstrip('/'), sp.query)
    return ('internal', target)

def main():
    scanned = 0
    resolved = 0
    missing = collections.Counter()
    missing_refs = collections.defaultdict(list)
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d != '.git']
        for fn in filenames:
            if not fn.lower().endswith(('.htm', '.html', '.cgi', '.shtml')):
                continue
            fp = os.path.join(dirpath, fn)
            rel = os.path.relpath(fp, ROOT)
            reldir = os.path.dirname(rel)
            tree = toptree(rel)
            try:
                data = open(fp, 'rb').read()
            except OSError:
                continue
            try:
                text = data.decode('utf-8')
            except UnicodeDecodeError:
                try:
                    text = data.decode('cp932', 'replace')
                except Exception:
                    text = data.decode('latin-1')
            scanned += 1
            for m in ATTR_RE.finditer(text):
                url = next(g for g in m.groups() if g is not None)
                kind, target = classify(url, reldir, tree)
                if kind != 'internal':
                    continue
                if exists(target):
                    resolved += 1
                else:
                    missing[target] += 1
                    if len(missing_refs[target]) < 3:
                        missing_refs[target].append(rel)
    print(f'scanned={scanned} resolved={resolved} missing_links={sum(missing.values())} missing_targets={len(missing)}')
    out = {t: {'n': n, 'refs': missing_refs[t]} for t, n in missing.most_common()}
    with open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'missing_targets.json'), 'w') as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
    for t, n in missing.most_common(50):
        print(f'{n:6d}  {t}   <- {missing_refs[t][0]}')

if __name__ == '__main__':
    main()
