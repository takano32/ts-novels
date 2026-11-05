# -*- coding: utf-8 -*-
"""tslib/secret_rules.py: 配信される原本の秘密値の走査規則 (specs/S111.md の 5 つの検査)。"""
import unittest

from tslib import secret_rules as sr

CONTENTS = {
    'a.cgi': b'#!/usr/bin/perl\nuse strict;\n',                                       # 検査 1・2
    'b.html': (b'<input type="hidden" name="pass" value="s3cret!">\n'                   # 検査 3
               b'<input type=hidden name=pw value=12345>\n'                              # 数字だけは除外
               b'<INPUT TYPE="HIDDEN" NAME="admin" VALUE="guest">'),                    # 通常語は除外
    'c.txt': b'login: password=hunter22 and type=password\n',                            # 検査 4 (type= は除外)
    '.htaccess': b'x',                                                                   # 検査 5
    'd.html': b' guest ' * 50,
}


class ScanTest(unittest.TestCase):
    def test_five_rules(self):
        self.assertEqual(sr.scan(CONTENTS), [
            {'rule': 5, 'path': '.htaccess', 'line': 0}, {'rule': 1, 'path': 'a.cgi', 'line': 1},
            {'rule': 2, 'path': 'a.cgi', 'line': 2}, {'rule': 3, 'path': 'b.html', 'line': 1},
            {'rule': 4, 'path': 'c.txt', 'line': 1}])

    def test_clean(self):
        self.assertEqual(sr.scan({'x.html': b'<input type="password" name="pass">\nuse of strict rules\n'}), [])

    def test_hidden_common_word_threshold(self):
        # 検査 3 の通常語の除外: 英小文字 1〜6 字の値が、ほかのファイルに語として COMMON_WORD_MIN 回以上
        # 現れるなら秘密値とみなさない。自分のファイルの出現は数えない
        tag = b'<input type="hidden" name="pass" value="guest">'
        self.assertEqual(sr.COMMON_WORD_MIN, 50)
        hit = [{'rule': 3, 'path': 'a.html', 'line': 1}]
        self.assertEqual(sr.scan({'a.html': tag, 'b.txt': b' guest' * 50}), [])
        self.assertEqual(sr.scan({'a.html': tag, 'b.txt': b' guest' * 49}), hit)
        self.assertEqual(sr.scan({'a.html': tag + b' guest' * 50}), hit)
        self.assertEqual(sr.scan({'a.html': tag, 'b.txt': b' guests' * 50}), hit)        # 語の一部は数えない
        # 7 字以上・大文字を含む値は数によらず除外しない
        for value in (b'guestxx', b'Guest'):
            with self.subTest(value=value):
                self.assertEqual(sr.scan({'a.html': tag.replace(b'guest', value), 'b.txt': (b' ' + value) * 50}), hit)

    def test_parse_attrs(self):
        self.assertEqual(sr.parse_attrs(b'<input type=hidden name="PASS" value=\'x y\' checked>'),
                         {b'type': b'hidden', b'name': b'PASS', b'value': b'x y', b'checked': b''})

    def test_word_counter_excludes_own_file(self):
        wc = sr.WordCounter({'a': b'guest guest', 'b': b'guest guests', 'c': b'none'})
        self.assertEqual(wc.count_excluding(b'guest', 'a'), 1)
        self.assertEqual(wc.count_excluding(b'guest', 'z'), 3)

    def test_select_files(self):
        self.assertEqual(sr.select_files(['scripts/a', 'specs/S1.md', 'novel/b', 'x', 'gone'], lambda p: p != 'gone'),
                         ['novel/b', 'x'])
        self.assertEqual(sr.line_of(b'a\nb\nc', 4), 3)


class AllowTest(unittest.TestCase):
    def test_parse_and_apply(self):
        allow = sr.parse_allow({'allow': [{'at': 'a.cgi:1', 'reason': '検証済み'}, {'at': 'z:9', 'reason': 'r'}]}, 'f')
        hits = sr.apply_allow(sr.scan(CONTENTS), allow)
        self.assertNotIn({'rule': 1, 'path': 'a.cgi', 'line': 1}, hits)
        self.assertEqual(len(hits), 4)
        self.assertEqual([a['used'] for a in allow], [1, 0])
        self.assertEqual(sr.parse_allow({}, 'f'), [])

    def test_parse_errors(self):
        for ent in ({'at': 'a.cgi', 'reason': 'x'}, {'at': 'a.cgi:1', 'reason': ' '}):
            with self.assertRaises(ValueError) as cm:
                sr.parse_allow({'allow': [ent]}, 'f.yml')
            self.assertEqual(str(cm.exception), 'secret_scan: f.yml の 1 件目は at: "path:行" と reason が要る')


class ReportTest(unittest.TestCase):
    def test_report_of(self):
        raw = sr.scan(CONTENTS)
        allow = sr.parse_allow({'allow': [{'at': 'a.cgi:1', 'reason': 'r'}]}, 'f')
        hits = sr.apply_allow(raw, allow)
        rep = sr.report_of(['a', 'b', 'c'], raw, hits, '2026-11-05T00:00:00+00:00')
        self.assertEqual(list(rep), ['scanned', 'hits', 'allowed', 'generated'])
        self.assertEqual((rep['scanned'], rep['allowed'], rep['hits']), (3, 1, hits))

    def test_summary_lines(self):
        hits = [{'rule': 3, 'path': 'b.html', 'line': 1}, {'rule': 1, 'path': 'a.cgi', 'line': 1},
                {'rule': 3, 'path': 'c.html', 'line': 9}]
        allow = [{'path': 'a.cgi', 'line': 1, 'reason': 'r', 'used': 1}, {'path': 'z', 'line': 2, 'reason': 'r', 'used': 0}]
        rep = sr.report_of(['x'] * 10, hits + [{}], hits, 'T')
        self.assertEqual(sr.summary_lines(rep, allow), [
            'secret_scan: 走査 10 ファイル / 該当 3 件 (許容で除外 1 件) 検査1=1 検査3=2',
            '  検査 3: b.html:1', '  検査 1: a.cgi:1', '  検査 3: c.html:9',
            '  [注意] 許容リストの z:2 はどの該当にも当たらなかった',
            'NG — 該当あり (specs/S111.md)'])
        self.assertEqual(sr.summary_lines(sr.report_of([], [], [], 'T'), []),
                         ['secret_scan: 走査 0 ファイル / 該当 0 件 (許容で除外 0 件)', 'OK'])


if __name__ == '__main__':
    unittest.main()
