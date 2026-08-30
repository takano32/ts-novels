#!/usr/bin/env python3
"""本番 .htaccess の ts-library マーカーブロック管理 (タスク 2.6。Fable 所管 — 本番を触るコード)。

やること:
  - 恒久 noindex 層 (/boards/ /dojo/ — v1.5: 公開ゲートではなく第三者配慮) の X-Robots-Tag
  - 取り下げ済み URL の 410 (Redirect gone)。URL は `wp ts export-takedown-urls` に聞く
  - 上記だけを BEGIN/END マーカー間のブロックとして生成し、サーバ上で差し替える

安全則 (台帳 2.6・心得 7):
  - 実行前に原本 .htaccess.orig (初回のみ) と直前状態 .bak-<時刻> を必ず作る
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
REMOTE_BASE = '~/novels.xwp.jp'
REMOTE = f'{REMOTE_BASE}/public_html/.htaccess'
BEGIN = '# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)'
END = '# END ts-library'
ROOT = Path(__file__).resolve().parents[2]


def denylist_410_lines():
    """410 にする URL は WP に聞く (単一情報源)。

    denylist.yml の target は work_slug / source_path などの**内部の鍵**であって URL ではない。
    URL への解決は importer が持っているので、`wp ts apply-takedown` が記録した
    option を `wp ts export-takedown-urls` 経由で読む。取得できないときは
    黙って 0 件にせず、その旨を出して呼び出し側に判断させる。"""
    try:
        r = subprocess.run(
            ['ssh', HOST, f'cd {REMOTE_BASE}/public_html && wp ts export-takedown-urls'],
            capture_output=True, text=True, timeout=120)
    except (subprocess.SubprocessError, OSError) as e:
        print(f'!! 取り下げ URL を取得できませんでした ({e}) — 410 ブロックは前回のまま据え置き')
        return None
    if r.returncode != 0:
        print(f'!! wp ts export-takedown-urls が失敗 (rc={r.returncode}) — 410 は据え置き')
        return None
    urls = [u.strip() for u in r.stdout.splitlines() if u.strip().startswith('/')]
    lines = [f'Redirect gone {u}' for u in urls]
    lines += asset_410_lines()
    return lines


def asset_410_lines():
    """denylist の kind: annex_path から、配信中の画像などの 410 を作る。

    投稿を非公開にしてもファイル本体は直接 URL で取れる (実測)。WP を経由しない
    /assets/annex-img/ 配下は .htaccess でしか塞げないのでここで扱う。"""
    path = ROOT / 'takedown' / 'denylist.yml'
    if not path.is_file():
        return []
    out, kind = [], None
    for line in path.read_text(encoding='utf-8').splitlines():
        if re.match(r'\s*#', line):
            continue
        m = re.match(r'\s+kind:\s*([a-z_]+)', line)
        if m:
            kind = m.group(1)
            continue
        m = re.match(r'\s+value:\s*["\']?([^"\'#]+)', line)
        if m and kind == 'annex_path':
            v = m.group(1).strip()
            if v.endswith('*'):
                out.append('RedirectMatch gone "^/assets/annex-img/%s"' % re.escape(v[:-1]))
            else:
                out.append('Redirect gone /assets/annex-img/%s' % v)
            kind = None
    return out


def build_block():
    gone = denylist_410_lines()
    if gone is None:
        print('!! 取り下げ URL 不明のまま .htaccess を書き換えると 410 を落としかねないので中止します')
        sys.exit(2)
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
        f'# takedown 410 ({len(gone)} 件。wp ts apply-takedown が止めた URL から生成)',
        *gone,
        END,
    ]
    return '\n'.join(parts) + '\n', gone


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
    block, gone = build_block()
    if '--install' not in sys.argv:
        print(block)
        print('(dry-run。適用は --install)')
        return

    # 原本 (ts-library ブロックが入る前の姿) は 1 度だけ保存し、以後は上書きしない。
    # 日付つきの .bak を毎日作ると「ブロック入りの .bak」が最新になり原本を見失う
    print(f'[1/4] バックアップ {REMOTE}.orig (無ければ作成)')
    ssh(f'cp -n {REMOTE} {REMOTE}.orig && wc -c < {REMOTE}')
    stamp = time.strftime('%Y%m%d-%H%M%S')
    ssh(f'cp {REMOTE} {REMOTE}.bak-{stamp}')   # 直前の姿 (切り戻し用)

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
    for line in gone[:3]:                      # 410 は「実際に 410 が返るか」まで見る
        url = line.split(' ', 2)[-1]
        st, _ = curl_head(f'https://novels.xwp.jp{url}?_cb={cb}')
        print(f'  410 対象 {url}: {st}')
        ok &= (st == 410)

    if not ok:
        print(f'[4/4] 検証 NG — .bak-{stamp} を復元します')
        ssh(f'cp {REMOTE}.bak-{stamp} {REMOTE}')
        sys.exit(1)
    print('[4/4] OK。バックアップはサーバに残置')


if __name__ == '__main__':
    main()
