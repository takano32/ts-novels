#!/usr/bin/env python3
"""本番 .htaccess の ts-library マーカーブロック管理 (タスク 2.6。Fable 所管 — 本番を触るコード)。

やること:
  - 恒久 noindex 層 (/boards/ /dojo/ — v1.5: 公開ゲートではなく第三者配慮) の X-Robots-Tag
  - takedown/denylist.yml に url: 行があれば 410 (Redirect gone)
  - 上記だけを BEGIN/END マーカー間のブロックとして生成し、サーバ上で差し替える

安全則 (台帳 2.6・心得 7):
  - 実行前にサーバ上で .htaccess.bak-YYYYMMDD を必ず作る
  - 既存 .htaccess の中身は**メモリ内でのみ**扱い、ローカル保存・表示・コミットしない
    (CloudSecure のログイン URL 変更値が含まれるため。diff も出さない)
  - 差し替え後に curl 検証 (無関係 URL の 200 + /dojo/ の X-Robots-Tag)。失敗時は .bak を戻す

使い方:
  python3 scripts/wp/htaccess_block.py            # ブロックを表示するだけ (dry-run)
  python3 scripts/wp/htaccess_block.py --install  # サーバに適用 + 検証
"""
import re
import subprocess
import sys
import time
from pathlib import Path

HOST = 'novels'
REMOTE = '~/novels.xwp.jp/public_html/.htaccess'
BEGIN = '# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)'
END = '# END ts-library'
ROOT = Path(__file__).resolve().parents[2]


def denylist_410_lines():
    """takedown/denylist.yml の url: 行から Redirect gone を作る (現状 0 件想定)。"""
    path = ROOT / 'takedown' / 'denylist.yml'
    lines = []
    if path.is_file():
        for line in path.read_text(encoding='utf-8').splitlines():
            m = re.match(r'\s*(?:-\s*)?url:\s*["\']?(/[^"\'#\s]+)', line)
            if m:
                lines.append(f'Redirect gone {m.group(1)}')
    return lines


def build_block():
    gone = denylist_410_lines()
    parts = [
        BEGIN,
        '# 恒久 noindex 層: /boards/ /dojo/ (第三者ハンドルが載る層。設計 v1.5)',
        '<IfModule mod_setenvif.c>',
        'SetEnvIf Request_URI "^/(boards|dojo)(/|$)" TS_NOINDEX',
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '# mod_rewrite の内部リダイレクト後は REDIRECT_ 接頭辞が付くので両方みる',
        'Header always set X-Robots-Tag "noindex, follow" env=TS_NOINDEX',
        'Header always set X-Robots-Tag "noindex, follow" env=REDIRECT_TS_NOINDEX',
        '</IfModule>',
        f'# takedown 410 ({len(gone)} 件。takedown/denylist.yml の url: から生成)',
        *gone,
        END,
    ]
    return '\n'.join(parts) + '\n'


def ssh(cmd, **kw):
    return subprocess.run(['ssh', HOST, cmd], check=True, capture_output=True,
                          text=True, **kw)


def splice(current, block):
    """マーカー間を差し替える。無ければ先頭に足す (既存ブロック群の前で害がない)。"""
    pat = re.compile(re.escape(BEGIN) + r'.*?' + re.escape(END) + r'\n?', re.S)
    if pat.search(current):
        return pat.sub(block, current, count=1)
    return block + '\n' + current


def curl_head(url):
    r = subprocess.run(['curl', '-s', '-o', '/dev/null', '-D', '-', url],
                       capture_output=True, text=True, timeout=30)
    status = re.search(r'HTTP/\S+\s+(\d+)', r.stdout)
    return (int(status.group(1)) if status else 0), r.stdout


def main():
    block = build_block()
    if '--install' not in sys.argv:
        print(block)
        print('(dry-run。適用は --install)')
        return

    stamp = time.strftime('%Y%m%d')
    print(f'[1/4] バックアップ {REMOTE}.bak-{stamp}')
    ssh(f'cp -n {REMOTE} {REMOTE}.bak-{stamp} && wc -c < {REMOTE}')

    print('[2/4] マーカーブロックを差し替え (中身は表示しない)')
    current = ssh(f'cat {REMOTE}').stdout
    updated = splice(current, block)
    ssh(f'cat > {REMOTE}.ts-new && mv {REMOTE}.ts-new {REMOTE}', input=updated)
    print(f'  {len(current)} -> {len(updated)} bytes (ブロック {len(block)} bytes)')

    print('[3/4] 検証 curl')
    cb = int(time.time())
    ok = True
    st, hdr = curl_head(f'https://novels.xwp.jp/novel/yaji-trans11/?_cb={cb}')
    tagged = 'x-robots-tag' in hdr.lower()
    print(f'  無関係 URL: {st} (X-Robots-Tag {"あり!" if tagged else "なし"})')
    ok &= (st == 200 and not tagged)
    st, hdr = curl_head(f'https://novels.xwp.jp/dojo/?_cb={cb}')
    tagged = 'noindex' in hdr.lower()
    print(f'  /dojo/   : {st} (noindex {"あり" if tagged else "なし!"})')
    ok &= tagged  # /dojo/ は投稿 0 でも 200/404 いずれでもヘッダは付くはず

    if not ok:
        print(f'[4/4] 検証 NG — .bak-{stamp} を復元します')
        ssh(f'cp {REMOTE}.bak-{stamp} {REMOTE}')
        sys.exit(1)
    print('[4/4] OK。バックアップはサーバに残置')


if __name__ == '__main__':
    main()
