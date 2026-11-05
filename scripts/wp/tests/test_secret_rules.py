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


if __name__ == '__main__':
    unittest.main()
