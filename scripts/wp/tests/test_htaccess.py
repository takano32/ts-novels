# -*- coding: utf-8 -*-
"""tslib/htaccess.py: 本番 .htaccess のマーカーブロックの組み立て (サーバに触らない部分)。"""
import unittest

from tslib import htaccess as ht

DENYLIST = ('entries:\n'
            '  - id: S1\n    target:\n      kind: annex_path\n      value: "novel/a/*"  # 全部\n'
            '  - id: S2\n    target:\n      kind: work\n      value: w\n'
            '#    kind: annex_path\n'
            '  - id: S3\n    target:\n      kind: annex_path\n      value: novel/b.gif\n')

BLOCK = '\n'.join([
    '# BEGIN ts-library (scripts/wp/htaccess_block.py が管理。中を手で編集しない)',
    '# 恒久 noindex 層: /boards/ /dojo/ (第三者ハンドルが載る層。設計 S42)',
    '<IfModule mod_setenvif.c>',
    'SetEnvIf Request_URI "^/(boards|dojo)(/|$)" TS_NOINDEX',
    '</IfModule>',
    '<IfModule mod_headers.c>',
    '# mod_rewrite の内部リダイレクト後は REDIRECT_ 接頭辞が付くので両方みる',
    'Header always set X-Robots-Tag "noindex, follow" env=TS_NOINDEX',
    'Header always set X-Robots-Tag "noindex, follow" env=REDIRECT_TS_NOINDEX',
    '</IfModule>',
    '# 取り下げた画像などの 410 (2 件。takedown/denylist.yml の kind: annex_path から)。',
    '# WordPress の URL の 410 は WordPress が返すのでここには書かない (specs/S119.md)',
    'RedirectMatch gone "^/assets/annex-img/novel/a/"',
    'Redirect gone /assets/annex-img/novel/b.gif',
    '# END ts-library']) + '\n'


class HtaccessTest(unittest.TestCase):
    def test_annex_values(self):
        self.assertEqual(ht.annex_values(DENYLIST), ['novel/a/*', 'novel/b.gif'])
        self.assertEqual(ht.annex_values(''), [])

    def test_asset_410_lines(self):
        self.assertEqual(ht.asset_410_lines(['novel/a b/*', 'novel/b.gif']),
                         ['RedirectMatch gone "^/assets/annex-img/novel/a\\ b/"', 'Redirect gone /assets/annex-img/novel/b.gif'])

    def test_build_block_full_text(self):
        block, gone = ht.build_block(ht.annex_values(DENYLIST))
        self.assertEqual(block, BLOCK)
        self.assertEqual(len(gone), 2)
        self.assertIn('(0 件。', ht.build_block([])[0])

    def test_splice(self):
        self.assertEqual(ht.splice('x\n', 'B\n'), 'B\n\nx\n')
        current = 'head\n' + ht.BEGIN + '\nold\n' + ht.END + '\nrest\n'
        self.assertEqual(ht.splice(current, BLOCK), 'head\n' + BLOCK + 'rest\n')

    def test_parse_curl_head(self):
        self.assertEqual(ht.parse_curl_head('HTTP/2 410\r\nx: y\r\n'), (410, 'HTTP/2 410\r\nx: y\r\n'))
        self.assertEqual(ht.parse_curl_head(''), (0, ''))


if __name__ == '__main__':
    unittest.main()
