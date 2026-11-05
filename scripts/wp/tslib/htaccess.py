# -*- coding: utf-8 -*-
"""htaccess.py — 本番 .htaccess の ts-library マーカーブロックの組み立て (T8.6、specs/S119.md)。

サーバに触らない部分だけ: denylist の kind: annex_path の読み取り、410 の行、ブロック全体、
マーカー間の差し替え、curl の応答ヘッダの読み取り。ssh・curl・バックアップ・復元の流れは
scripts/wp/htaccess_block.py に残す (本番を触るコード)。
"""
import re

BEGIN = '# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)'
END = '# END ts-library'


def annex_values(text):
    """denylist.yml の中身 (文字列) → kind: annex_path の value を順に返す。"""
    out, kind = [], None
    for line in text.splitlines():
        if re.match(r'\s*#', line):
            continue
        m = re.match(r'\s+kind:\s*([a-z_]+)', line)
        if m:
            kind = m.group(1)
            continue
        m = re.match(r'\s+value:\s*["\']?([^"\'#]+)', line)
        if m and kind == 'annex_path':
            out.append(m.group(1).strip())
            kind = None
    return out


def asset_410_lines(values):
    """denylist の kind: annex_path から、配信中の画像などの 410 を作る。

    投稿を非公開にしてもファイル本体は直接 URL で取れる (実測)。WordPress を経由しない
    /assets/annex-img/ 配下は .htaccess でしか塞げないのでここで扱う。"""
    out = []
    for v in values:
        if v.endswith('*'):
            out.append('RedirectMatch gone "^/assets/annex-img/%s"' % re.escape(v[:-1]))
        else:
            out.append('Redirect gone /assets/annex-img/%s' % v)
    return out


def build_block(annex):
    """マーカーブロックを組み立てる。annex は annex_path の値の列。戻り値 (ブロック, 410 の行)。"""
    gone = asset_410_lines(annex)
    parts = [
        BEGIN,
        '# 恒久 noindex 層: /boards/ /dojo/ (第三者ハンドルが載る層。設計 S42)',
        '<IfModule mod_setenvif.c>',
        'SetEnvIf Request_URI "^/(boards|dojo)(/|$)" TS_NOINDEX',
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '# mod_rewrite の内部リダイレクト後は REDIRECT_ 接頭辞が付くので両方みる',
        'Header always set X-Robots-Tag "noindex, follow" env=TS_NOINDEX',
        'Header always set X-Robots-Tag "noindex, follow" env=REDIRECT_TS_NOINDEX',
        '</IfModule>',
        f'# 取り下げた画像などの 410 ({len(gone)} 件。takedown/denylist.yml の kind: annex_path から)。',
        '# WordPress の URL の 410 は WordPress が返すのでここには書かない (specs/S119.md)',
        *gone,
        END,
    ]
    return '\n'.join(parts) + '\n', gone


def splice(current, block):
    """マーカー間を差し替える。無ければ先頭に足す (既存ブロック群の前で害がない)。"""
    pat = re.compile(re.escape(BEGIN) + r'.*?' + re.escape(END) + r'\n?', re.S)
    if pat.search(current):
        return pat.sub(block, current, count=1)
    return block + '\n' + current


def parse_curl_head(stdout):
    """`curl -s -o /dev/null -D -` の標準出力 → (HTTP の状態コード, ヘッダの全文)。読めなければ 0。"""
    status = re.search(r'HTTP/\S+\s+(\d+)', stdout)
    return (int(status.group(1)) if status else 0), stdout
