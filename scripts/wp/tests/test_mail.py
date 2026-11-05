# -*- coding: utf-8 -*-
"""tslib/mail.py: アドレスの検出と除去 (メールアドレスは example.com の作り物だけ)。"""
import re
import unittest

from tslib import mail


class BareMailTest(unittest.TestCase):
    def test_filenames_are_not_addresses(self):
        for s in ('index@08201937.html', 'bbs@log_x.cgi', 'bbs@log_johdan.cgi', 'a@b.jpg', 'x@y.htm'):
            self.assertIsNone(mail.RE_BARE_MAIL.search(s), s)
        self.assertIsNotNone(mail.RE_BARE_MAIL.search('taro@mail.example.com'))
        self.assertIsNotNone(mail.RE_BARE_MAIL.search('jiro@example.com'))

    # 試験のアドレスは example.com の作り物だけ。example は本文の決まりでは残す語なので、
    # 伏せる側の試験は keep に hoge だけを当てる正規表現を渡す
    def test_redact_bare_keep_and_counter(self):
        keep = re.compile('hoge')
        n = {}
        out = mail.redact_bare('a taro@mail.example.com b hoge@example.com c', keep=keep, counter=n)
        self.assertEqual(out, 'a %s b hoge@example.com c' % mail.REDACTED)
        self.assertEqual(n, {'bare': 1})
        n = {}
        out = mail.redact_bare('hoge@example.com', keep=None, counter=n, key='k')
        self.assertEqual((out, n), (mail.REDACTED, {'k': 1}))
        # 既定 (本文の決まり) では example を残す
        self.assertEqual(mail.redact_bare('taro@mail.example.com'), 'taro@mail.example.com')

    def test_scrub_addresses(self):
        n = {'mailto': 0, 'bare': 0}
        src = ('<a href="mailto:taro@example.com">たろう</a> と '
               '<p href=\'mailto:z@example.com\'>p</p> と jiro@example.com')
        out = mail.scrub_addresses(src, n, keep=re.compile('hoge'))
        self.assertEqual(out, 'たろう と <p>p</p> と ' + mail.REDACTED)
        self.assertEqual(n, {'mailto': 1, 'bare': 1})

    def test_has_leak(self):
        keep = re.compile('hoge')
        self.assertTrue(mail.has_leak('jiro@example.com', keep))
        self.assertFalse(mail.has_leak('hoge@example.com', keep))
        self.assertFalse(mail.has_leak('jiro@example.com'))
        self.assertTrue(mail.has_leak('<a href="mailto:">x</a>'))
        self.assertTrue(mail.has_leak("<a href='mailto:'>x</a>"))
        self.assertFalse(mail.has_leak('index@08201937.html', keep))

    def test_docs_keep_is_wider(self):
        self.assertTrue(mail.MAIL_KEEP_DOCS_RE.search('foo@'))
        self.assertIsNone(mail.MAIL_KEEP_RE.search('foo@'))


if __name__ == '__main__':
    unittest.main()
