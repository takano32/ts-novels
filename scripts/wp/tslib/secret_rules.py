# -*- coding: utf-8 -*-
"""secret_rules.py — 配信される原本に CGI のソースや秘密値が混じっていないかの走査の規則
(T16.3、規則は specs/S111.md)。入出力と表示は scripts/wp/secret_scan.py。

検査の番号は specs/S111.md の検査の番号。ファイルは読まず、中身の bytes を受けて該当
(rule・path・line) を返すだけ。値は返さない。
"""
import fnmatch
import re

# 配信されない (原本ではない) 置き場。specs/S111.md の対象の定義どおり
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



def select_files(paths, isfile):
    """走査対象の相対パス (posix) の一覧。置き場の除外を当て、isfile(p) が偽のもの
    (作業ツリーで消したもの・submodule) を落として整列する。"""
    out = []
    for p in paths:
        if p.startswith(EXCLUDE_DIRS):
            continue
        if not isfile(p):
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


def scan(contents):
    """contents (相対パス → 中身の bytes。走査の順に並べる) → 該当の一覧 (path・line・rule の順に整列)。"""
    words = WordCounter(contents)
    hits = []
    for p in contents:
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


def parse_allow(doc, path):
    """許容リストの YAML を読んだもの → [{path, line, reason, used}]。書式の誤りは ValueError
    (文言は path を含む)。"""
    out = []
    for i, ent in enumerate(doc.get('allow') or []):
        at = str(ent.get('at', ''))
        reason = str(ent.get('reason', '')).strip()
        m = re.fullmatch(r'(.+):([0-9]+)', at)
        if not m or not reason:
            raise ValueError(f'secret_scan: {path} の {i + 1} 件目は at: "path:行" と reason が要る')
        out.append({'path': m.group(1), 'line': int(m.group(2)), 'reason': reason, 'used': 0})
    return out


def apply_allow(raw, allow):
    """許容リストに当たった該当を除く。当たった許容の used を数える (当たらなかった許容の注意に使う)。"""
    hits = []
    for h in raw:
        ok = False
        for a in allow:
            if a['path'] == h['path'] and a['line'] == h['line']:
                a['used'] += 1
                ok = True
        if not ok:
            hits.append(h)
    return hits
