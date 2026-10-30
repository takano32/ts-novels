#!/usr/bin/env python3
"""本番 .htaccess の ts-library マーカーブロック管理 (T.8.6。高品位なモデルとエフォートの所管 — 本番を触るコード)。

やること (specs/S.119.md):
  - 恒久 noindex 層 (/boards/ /dojo/ — S.42: 公開ゲートではなく第三者配慮) の X-Robots-Tag
  - WordPress を通らない資産 (/assets/annex-img/ の画像など) の 410。denylist の kind: annex_path から作る
  - 上記だけを BEGIN/END マーカー間のブロックとして生成し、サーバ上で差し替える
書かないもの (S.119):
  - WordPress の URL (/novel/…) の Redirect gone — 取り下げの 410 は WordPress が返す
    (includes/visibility.php。ログイン中の運営委員には帯つきで見せるため、.htaccess では塞がない)
  - 旧 URL → 新 URL の転送 (301 は使わない。S.221)

安全則 (specs/S.186.md・specs/S.10.md の 7):
  - 実行前に原本 .htaccess.orig (初回のみ) と直前状態 .bak-<時刻> を必ず作る
  - 既存 .htaccess の中身は**メモリ内でのみ**扱い、ローカル保存・表示・コミットしない
    (CloudSecure のログイン URL 変更値が含まれるため。diff も出さない)
  - 差し替え後に curl 検証 (無関係 URL の 200 + /dojo/ の X-Robots-Tag + 取り下げ URL が 410
    (WordPress 由来) + 取り下げた画像が 410)。失敗時は .bak を戻す
  - ブロックの組み立て (dry-run) はサーバに接続しない。接続するのは --install のときだけ

使い方:
  python3 scripts/wp/htaccess_block.py            # ブロックを表示するだけ (dry-run)
  python3 scripts/wp/htaccess_block.py --install  # サーバに適用 + 検証
"""
import re
import subprocess
import sys
import time
from pathlib import Path

HOST = 'novels'   # 宛先表記用。実際の接続先は scripts/wp/novels-ssh が持つ
RSH = str(Path(__file__).resolve().parent / 'novels-ssh')
REMOTE_BASE = '~/novels.xwp.jp'
REMOTE = f'{REMOTE_BASE}/public_html/.htaccess'
BEGIN = '# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)'
END = '# END ts-library'
ROOT = Path(__file__).resolve().parents[2]


def takedown_urls():
    """取り下げ済みの WordPress の URL (--install の検証用。.htaccess には書かない)。

    denylist.yml の target は work_slug / source_path などの**内部の鍵**であって URL ではない。
    URL への解決は importer が持っているので、`wp ts apply-takedown` が記録した
    option を `wp ts export-takedown-urls` 経由で読む。取得できないときは None。"""
    try:
        r = subprocess.run(
            [RSH, f'cd {REMOTE_BASE}/public_html && wp ts export-takedown-urls'],
            capture_output=True, text=True, timeout=120)
    except (subprocess.SubprocessError, OSError) as e:
        print(f'!! 取り下げ URL を取得できませんでした ({e})')
        return None
    if r.returncode != 0:
        print(f'!! wp ts export-takedown-urls が失敗 (rc={r.returncode})')
        return None
    return [u.strip() for u in r.stdout.splitlines() if u.strip().startswith('/')]


def annex_values(path=None):
    """denylist の kind: annex_path の value を順に返す。"""
    path = path or (ROOT / 'takedown' / 'denylist.yml')
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
            out.append(m.group(1).strip())
            kind = None
    return out


def asset_410_lines(values=None):
    """denylist の kind: annex_path から、配信中の画像などの 410 を作る。

    投稿を非公開にしてもファイル本体は直接 URL で取れる (実測)。WordPress を経由しない
    /assets/annex-img/ 配下は .htaccess でしか塞げないのでここで扱う。"""
    out = []
    for v in (annex_values() if values is None else values):
        if v.endswith('*'):
            out.append('RedirectMatch gone "^/assets/annex-img/%s"' % re.escape(v[:-1]))
        else:
            out.append('Redirect gone /assets/annex-img/%s' % v)
    return out


def asset_probe_urls(values=None):
    """--install の検証で 410 を確かめる画像の URL (annex_path ごとに、配備される実在ファイルを 1 つ)。

    deploy.sh は git 管理の画像を /assets/annex-img/ に置くので、git ls-files から選ぶ。"""
    urls = []
    for v in (annex_values() if values is None else values):
        prefix = v[:-1] if v.endswith('*') else v
        r = subprocess.run(['git', '-C', str(ROOT), 'ls-files', '--', prefix + ('*' if v.endswith('*') else '')],
                           capture_output=True, text=True)
        files = [f for f in r.stdout.splitlines() if f.startswith(prefix)]
        if files:
            urls.append('/assets/annex-img/' + sorted(files)[0])
    return urls


def build_block(annex=None):
    """マーカーブロックを組み立てる (サーバに接続しない)。annex は試験用に annex_path の値を渡せる。"""
    gone = asset_410_lines(annex)
    parts = [
        BEGIN,
        '# 恒久 noindex 層: /boards/ /dojo/ (第三者ハンドルが載る層。設計 S.42)',
        '<IfModule mod_setenvif.c>',
        'SetEnvIf Request_URI "^/(boards|dojo)(/|$)" TS_NOINDEX',
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '# mod_rewrite の内部リダイレクト後は REDIRECT_ 接頭辞が付くので両方みる',
        'Header always set X-Robots-Tag "noindex, follow" env=TS_NOINDEX',
        'Header always set X-Robots-Tag "noindex, follow" env=REDIRECT_TS_NOINDEX',
        '</IfModule>',
        f'# 取り下げた画像などの 410 ({len(gone)} 件。takedown/denylist.yml の kind: annex_path から)。',
        '# WordPress の URL の 410 は WordPress が返すのでここには書かない (specs/S.119.md)',
        *gone,
        END,
    ]
    return '\n'.join(parts) + '\n', gone


def ssh(cmd, **kw):
    return subprocess.run([RSH, cmd], check=True, capture_output=True,
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

    # 検証に使う取り下げ URL は先に取る (取れないなら書き換えない — 410 を確かめられないため)
    urls = takedown_urls()
    if urls is None:
        print('!! 取り下げ URL を確かめられないので中止します (.htaccess は変更していません)')
        sys.exit(2)

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
    # 取り下げ URL は WordPress が 410 を返す (ログインなしの curl で確かめる。S.119)
    for url in urls[:3]:
        st, _ = curl_head(f'https://novels.xwp.jp{url}?_cb={cb}')
        print(f'  取り下げ (WordPress) {url}: {st}')
        ok &= (st == 410)
    if not urls:
        print('  取り下げ URL が 0 件 — wp ts apply-takedown を先に流したか確認してください')
        ok = False
    for url in asset_probe_urls():              # 画像の 410 は .htaccess が返す
        st, _ = curl_head(f'https://novels.xwp.jp{url}?_cb={cb}')
        print(f'  取り下げ (画像) {url}: {st}')
        ok &= (st == 410)

    if not ok:
        print(f'[4/4] 検証 NG — .bak-{stamp} を復元します')
        ssh(f'cp {REMOTE}.bak-{stamp} {REMOTE}')
        sys.exit(1)
    print('[4/4] OK。バックアップはサーバに残置')


if __name__ == '__main__':
    main()
