# -*- coding: utf-8 -*-
"""tslib/checks.py: `[OK ]` / `[NG ]` の行とレポートの形。"""
import unittest

from tslib import checks


class ChecksTest(unittest.TestCase):
    def test_mark_and_line(self):
        self.assertEqual(checks.mark(True), 'OK ')
        self.assertEqual(checks.mark(False), 'NG ')
        self.assertEqual(checks.format_line('件数', True, '3 件'), '  [OK ] 件数 3 件')
        self.assertEqual(checks.format_line('件数', False, '', indent=''), '[NG ] 件数 ')

    def test_checks_bundle(self):
        c = checks.Checks()
        self.assertTrue(c.check('a', 1, 'x'))
        self.assertFalse(c.check('b', [], 'y'))
        self.assertFalse(c.ok)
        self.assertEqual(c.as_report(), [{'name': 'a', 'ok': True, 'detail': 'x'},
                                         {'name': 'b', 'ok': False, 'detail': 'y'}])
        self.assertEqual(list(c.as_report()[0]), ['name', 'ok', 'detail'])
        self.assertEqual(c.results, [('a', True, 'x'), ('b', False, 'y')])
        self.assertEqual(c.lines(), ['  [OK ] a x', '  [NG ] b y'])

    def test_empty_is_ok(self):
        self.assertTrue(checks.Checks().ok)

    def test_report_lines(self):
        rep = [{'name': 'a', 'ok': True, 'detail': 'x'}, {'name': 'b', 'ok': False, 'detail': ''}]
        self.assertEqual(checks.report_lines(rep, indent=''), ['[OK ] a x', '[NG ] b '])


if __name__ == '__main__':
    unittest.main()
