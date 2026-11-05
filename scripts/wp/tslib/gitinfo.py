# -*- coding: utf-8 -*-
"""gitinfo.py — git への問い合わせ (ls-files / log)。結果の解釈は呼び出し側の純粋な関数に渡す。"""
import re
import subprocess

IMAGE_EXT_RE = re.compile(r'\.(jpe?g|gif|png|bmp)$', re.I)


def ls_files(root, *pathspecs, check=True):
    """`git -C root ls-files <pathspecs>` の行。check=False なら git が失敗しても止めずに、
    そのときの標準出力の行 (ふつうは空) を返す。"""
    r = subprocess.run(['git', '-C', str(root), 'ls-files', *pathspecs],
                       capture_output=True, text=True, check=check)
    return r.stdout.splitlines()


def ls_files_z(root):
    """`git ls-files -z` を bytes のまま復号 (ファイル名が UTF-8 でないものがあっても落ちない)。
    git が使えなければ None。"""
    r = subprocess.run(['git', '-C', root, 'ls-files', '-z'], capture_output=True)
    if r.returncode != 0:
        return None
    return [p.decode('utf-8', 'surrogateescape') for p in r.stdout.split(b'\0') if p]


def image_files(root, *pathspecs):
    """git 管理下の画像 (jpg/jpeg/gif/png/bmp) の一覧。"""
    return [p for p in ls_files(root, *pathspecs) if IMAGE_EXT_RE.search(p)]


def added_files_log(root):
    """パスごとの初回追加コミットを引くための `git log` の生出力 (parse は catalog.provenance 側)。"""
    cmd = ['git', '-C', root, 'log', '--reverse', '--no-renames',
           '--diff-filter=A', '--name-only', '--format=__C__%H|%at|%s']
    return subprocess.run(cmd, capture_output=True, text=True, check=True).stdout


def show_file(root, rev, path):
    """`git show REV:path` の中身。"""
    r = subprocess.run(['git', '-C', root, 'show', '%s:%s' % (rev, path)],
                       capture_output=True, text=True, check=True)
    return r.stdout


def short_rev(root, rev):
    return subprocess.run(['git', '-C', root, 'rev-parse', '--short', rev],
                          capture_output=True, text=True, check=True).stdout.strip()
