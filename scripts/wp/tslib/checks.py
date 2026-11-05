# -*- coding: utf-8 -*-
"""checks.py — 自己検査の結果の束。

各段は受け入れ条件をコードにして `[OK ] 名前 詳細` / `[NG ] 名前 詳細` の行で出し、
レポート JSON には `selftest` (name / ok / detail の列) と `selftest_ok` を書く。
その形をここに 1 つにまとめる。
"""


def mark(ok):
    """表示の印。`[OK ]` / `[NG ]` の中身。"""
    return 'OK ' if ok else 'NG '


def format_line(name, ok, detail='', indent='  '):
    return '%s[%s] %s %s' % (indent, mark(ok), name, detail)


class Checks:
    """`check(name, ok, detail)` を積み、まとめて表示・レポート化する。"""

    def __init__(self):
        self.results = []

    def check(self, name, ok, detail=''):
        self.results.append((name, bool(ok), detail))
        return bool(ok)

    @property
    def ok(self):
        return all(ok for _, ok, _ in self.results)

    def as_report(self):
        return [{'name': n, 'ok': ok, 'detail': d} for n, ok, d in self.results]

    def lines(self, indent='  '):
        return [format_line(n, ok, d, indent) for n, ok, d in self.results]

    def print(self, indent='  '):
        for line in self.lines(indent):
            print(line)

    def as_tuple(self):
        """従来の (ok, [(name, ok, detail), …]) の形。"""
        return self.ok, list(self.results)


def report_lines(selftest, indent='  '):
    """レポート JSON の selftest (dict の列) を表示の行にする。"""
    return [format_line(t['name'], t['ok'], t['detail'], indent) for t in selftest]
