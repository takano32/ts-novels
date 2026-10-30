#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""secret_scan.py — 配信される原本に CGI のソースや秘密値が混じっていないかを走査する
(T.16.3、規則は specs/S.111.md、背景は specs/S.110.md)。

対象
  `git ls-files` の全ファイルから scripts/ mu-plugins/ themes/ specs/ catalog/ takedown/
  content/ .github/ を除いたもの (= 配信される原本)。--root がリポジトリでなければ、その下の
  全ファイル (.git を除く) を同じ除外で走査する (試験用)。

検査 (rule の番号は specs/S.111.md の検査の番号。**値は出力しない**)
  1. 先頭 2 バイトが `#!`
  2. 行頭の Perl/シェル構文 (`use strict` / `use CGI` / `require "…"` / `sub 名 {` / `$ENV{` /
     `print "Content-type` / `open(IN|OUT|FH|DAT|LOG`)
  3. `<input type=hidden …>` で name が pass|pw|passwd|password|admin|adminpass|delkey|secret、
     value が非空。value が数字のみ、または英小文字 1 語 (6 字以下) で他ファイルに通常語として
     50 回以上現れるものは除外
  4. `password=` `passwd=` `pass=` の直後に 4 字以上の値 (HTML 属性 `type=password` は除外)
  5. ファイル名が .htaccess / .htpasswd / wp-config* / *.env / *.pem / id_rsa*

出力
  catalog/reports/secret_scan.json (--root 指定時は <root>/catalog/reports/) =
  {"scanned": N, "hits": [{"rule": 3, "path": "…", "line": 46}], "generated": "<ISO 8601>"}
  と標準出力の要約。path と行番号だけで、行の内容は書かない。
  検証済みの許容は scripts/wp/secret_scan_allow.yml に `path:行` と理由を書いて除外する。

終了コード
  hits が 0 なら 0、1 件以上なら 1。

使い方
  python3 scripts/wp/secret_scan.py            … 走査してレポートを書く
  python3 scripts/wp/secret_scan.py --check    … 走査だけ (レポートを書かない。make check 用)
  python3 scripts/wp/secret_scan.py --root DIR … 別のツリーを走査する (試験用)
"""
import argparse
import datetime
import fnmatch
import json
import os
import re
import subprocess
import sys

try:
    import yaml
except ImportError:                                       # pragma: no cover
    yaml = None

DEFAULT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DEFAULT_ALLOW = os.path.join(DEFAULT_ROOT, 'scripts', 'wp', 'secret_scan_allow.yml')

# 配信されない (原本ではない) 置き場。specs/S.111.md の対象の定義どおり
# (2026-10-26 までの docs/ は specs/ に読み替えた。いまの docs/ は走査する)。
EXCLUDE_DIRS = ('scripts/', 'mu-plugins/', 'themes/', 'specs/', 'catalog/',
                'takedown/', 'content/', '.github/')

# 検査 2: 行頭 (字下げは許す) の Perl/シェル構文
RE_SCRIPT = re.compile(
    rb'^[ \t]*(?:use[ \t]+strict\b'
    rb'|use[ \t]+CGI\b'
    rb'|require[ \t]+"'
    rb'|sub[ \t]+[A-Za-z_][A-Za-z0-9_]*[ \t]*\{'
    rb'|\$ENV\{'
    rb'|print[ \t]+"Content-type'
    rb'|open[ \t]*\([ \t]*(?:IN|OUT|FH|DAT|LOG)\b)',
    re.M)
SCRIPT_KEYS = (b'use', b'require', b'sub', b'$ENV{', b'print', b'open')

# 検査 3: hidden 入力
RE_INPUT = re.compile(rb'<input\b[^>]*>', re.I)
RE_ATTR = re.compile(rb'([A-Za-z_:][-A-Za-z0-9_:.]*)\s*(?:=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?')
SECRET_NAMES = {b'pass', b'pw', b'passwd', b'password', b'admin', b'adminpass', b'delkey', b'secret'}
RE_DIGITS = re.compile(rb'[0-9]+')
RE_SHORT_WORD = re.compile(rb'[a-z]{1,6}')
COMMON_WORD_MIN = 50

# 検査 4: password= / passwd= / pass= の直後に 4 字以上の値
RE_PASS_ASSIGN = re.compile(
    rb'(?<![A-Za-z0-9_])(password|passwd|pass)=(["\']?)([^\s"\'&<>;,)]{4,})', re.I)
RE_TYPE_BEFORE = re.compile(rb'type\s*=\s*["\']?$', re.I)

# 検査 5: ファイル名
NAME_PATTERNS = ('.htaccess', '.htpasswd', 'wp-config*', '*.env', '*.pem', 'id_rsa*')


def list_files(root):
    """走査対象の相対パス (posix) の一覧。"""
    paths = None
    if os.path.isdir(os.path.join(root, '.git')) or os.path.isfile(os.path.join(root, '.git')):
        r = subprocess.run(['git', '-C', root, 'ls-files', '-z'], capture_output=True)
        if r.returncode == 0:
            paths = [p.decode('utf-8', 'surrogateescape') for p in r.stdout.split(b'\0') if p]
    if paths is None:
        paths = []
        for d, dirs, files in os.walk(root):
            dirs[:] = [x for x in dirs if x != '.git']
            for f in files:
                paths.append(os.path.relpath(os.path.join(d, f), root).replace(os.sep, '/'))
    out = []
    for p in paths:
        if p.startswith(EXCLUDE_DIRS):
            continue
        if not os.path.isfile(os.path.join(root, p)):   # 作業ツリーで消したもの・submodule
            continue
        out.append(p)
    return sorted(out)


def line_of(data, pos):
    return data.count(b'\n', 0, pos) + 1


def parse_attrs(tag):
    attrs = {}
    body = tag[len(b'<input'):-1]
    for m in RE_ATTR.finditer(body):
        k = m.group(1).lower()
        v = m.group(2)
        if v is None:
            v = b''
        elif v[:1] in (b'"', b"'") and v[-1:] == v[:1] and len(v) >= 2:
            v = v[1:-1]
        attrs.setdefault(k, v)
    return attrs


class WordCounter:
    """英小文字 1 語が「通常語として」他ファイルに何回現れるか (検査 3 の除外)。遅延計算でキャッシュする。"""

    def __init__(self, contents):
        self.contents = contents
        self.cache = {}

    def count_excluding(self, word, path):
        if word not in self.cache:
            rx = re.compile(rb'(?<![A-Za-z0-9_])' + re.escape(word) + rb'(?![A-Za-z0-9_])')
            self.cache[word] = {p: len(rx.findall(d)) for p, d in self.contents.items() if word in d}
        per = self.cache[word]
        return sum(n for p, n in per.items() if p != path)


def scan(root, files):
    contents = {}
    for p in files:
        with open(os.path.join(root, p), 'rb') as f:
            contents[p] = f.read()
    words = WordCounter(contents)
    hits = []
    for p in files:
        data = contents[p]
        base = p.rsplit('/', 1)[-1]
        # 5. ファイル名
        if any(fnmatch.fnmatchcase(base, pat) for pat in NAME_PATTERNS):
            hits.append({'rule': 5, 'path': p, 'line': 0})
        # 1. shebang
        if data[:2] == b'#!':
            hits.append({'rule': 1, 'path': p, 'line': 1})
        low = data.lower()
        # 2. Perl/シェル構文 (語が無いファイルは正規表現を回さない)
        if any(k in data for k in SCRIPT_KEYS):
            script_hits = RE_SCRIPT.finditer(data)
        else:
            script_hits = ()
        for m in script_hits:
            hits.append({'rule': 2, 'path': p, 'line': line_of(data, m.start())})
        # 3. hidden 入力
        for m in (RE_INPUT.finditer(data) if b'hidden' in low else ()):
            attrs = parse_attrs(m.group(0))
            if attrs.get(b'type', b'').strip().lower() != b'hidden':
                continue
            if attrs.get(b'name', b'').strip().lower() not in SECRET_NAMES:
                continue
            value = attrs.get(b'value', b'').strip()
            if not value:
                continue
            if RE_DIGITS.fullmatch(value):
                continue
            if RE_SHORT_WORD.fullmatch(value) and words.count_excluding(value, p) >= COMMON_WORD_MIN:
                continue
            hits.append({'rule': 3, 'path': p, 'line': line_of(data, m.start())})
        # 4. password= / passwd= / pass=
        for m in (RE_PASS_ASSIGN.finditer(data) if b'pass' in low else ()):
            head = data[max(0, m.start() - 16):m.start()]
            if RE_TYPE_BEFORE.search(head):
                continue
            hits.append({'rule': 4, 'path': p, 'line': line_of(data, m.start())})
    hits.sort(key=lambda h: (h['path'], h['line'], h['rule']))
    return hits


def load_allow(path):
    """scripts/wp/secret_scan_allow.yml: `allow:` の下に {at: "path:行", reason: "…"} を並べる。"""
    if not path or not os.path.exists(path):
        return []
    if yaml is None:
        raise SystemExit('secret_scan: 許容リストを読むには PyYAML が要る (make venv)')
    with open(path, encoding='utf-8') as f:
        doc = yaml.safe_load(f) or {}
    out = []
    for i, ent in enumerate(doc.get('allow') or []):
        at = str(ent.get('at', ''))
        reason = str(ent.get('reason', '')).strip()
        m = re.fullmatch(r'(.+):([0-9]+)', at)
        if not m or not reason:
            raise SystemExit(f'secret_scan: {path} の {i + 1} 件目は at: "path:行" と reason が要る')
        out.append({'path': m.group(1), 'line': int(m.group(2)), 'reason': reason, 'used': 0})
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--allow', default=DEFAULT_ALLOW, help='許容リスト (既定: scripts/wp/secret_scan_allow.yml)')
    ap.add_argument('--check', action='store_true', help='レポートを書かない')
    args = ap.parse_args()
    root = os.path.abspath(args.root)

    files = list_files(root)
    raw = scan(root, files)
    allow = load_allow(args.allow)
    hits = []
    for h in raw:
        ok = False
        for a in allow:
            if a['path'] == h['path'] and a['line'] == h['line']:
                a['used'] += 1
                ok = True
        if not ok:
            hits.append(h)

    report = {
        'scanned': len(files),
        'hits': hits,
        'allowed': len(raw) - len(hits),
        'generated': datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0).isoformat(),
    }
    if not args.check:
        out = os.path.join(root, 'catalog', 'reports', 'secret_scan.json')
        os.makedirs(os.path.dirname(out), exist_ok=True)
        with open(out, 'w', encoding='utf-8') as f:
            json.dump(report, f, ensure_ascii=False, indent=1)
            f.write('\n')

    by_rule = {}
    for h in hits:
        by_rule[h['rule']] = by_rule.get(h['rule'], 0) + 1
    print(f'secret_scan: 走査 {len(files)} ファイル / 該当 {len(hits)} 件 (許容で除外 {len(raw) - len(hits)} 件)'
          + (' ' + ' '.join(f'検査{r}={n}' for r, n in sorted(by_rule.items())) if by_rule else ''))
    for h in hits:
        print(f'  検査 {h["rule"]}: {h["path"]}:{h["line"]}')
    for a in allow:
        if not a['used']:
            print(f'  [注意] 許容リストの {a["path"]}:{a["line"]} はどの該当にも当たらなかった')
    print('OK' if not hits else 'NG — 該当あり (specs/S.111.md)')
    return 0 if not hits else 1


if __name__ == '__main__':
    sys.exit(main())
