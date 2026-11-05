# -*- coding: utf-8 -*-
"""tslib/gitinfo.py: 一時ディレクトリに git init した小さなリポジトリで問い合わせる。"""
import os
import subprocess
import unittest

from tslib import gitinfo
from tests.support import TempDirCase, HAS_GIT


def git(root, *args):
    env = dict(os.environ, GIT_AUTHOR_NAME='t', GIT_AUTHOR_EMAIL='t@example.com',
               GIT_COMMITTER_NAME='t', GIT_COMMITTER_EMAIL='t@example.com',
               GIT_AUTHOR_DATE='2001-02-03T04:05:06Z', GIT_COMMITTER_DATE='2001-02-03T04:05:06Z')
    return subprocess.run(['git', '-C', root, *args], check=True, capture_output=True,
                          text=True, env=env).stdout


@unittest.skipUnless(HAS_GIT, 'git が無い')
class GitInfoTest(TempDirCase):
    def setUp(self):
        super().setUp()
        git(self.tmp, 'init', '-q')
        git(self.tmp, 'config', 'commit.gpgsign', 'false')
        self.write('novel/a.html', 'v1\n')
        self.write('novel/p.GIF', b'GIF89a')
        self.write('docs/x.txt', 'x\n')
        git(self.tmp, 'add', '.')
        git(self.tmp, 'commit', '-q', '-m', 'first')
        self.write('novel/a.html', 'v2\n')
        self.write('novel/b c.jpg', b'\xff\xd8')
        git(self.tmp, 'add', '.')
        git(self.tmp, 'commit', '-q', '-m', 'second')

    def test_ls_files(self):
        self.assertEqual(gitinfo.ls_files(self.tmp, 'novel'), ['novel/a.html', 'novel/b c.jpg', 'novel/p.GIF'])
        self.assertEqual(gitinfo.ls_files(self.tmp, '--', 'novel/a*'), ['novel/a.html'])

    def test_ls_files_check(self):
        nogit = os.path.join(self.tmp, 'docs')        # 中だが .git の無い場所は親のリポジトリを見る
        self.assertEqual(gitinfo.ls_files(nogit), ['x.txt'])
        outside = self.tmp + '-none'
        with self.assertRaises(subprocess.CalledProcessError):
            gitinfo.ls_files(outside)
        self.assertEqual(gitinfo.ls_files(outside, check=False), [])

    def test_ls_files_z(self):
        self.assertEqual(gitinfo.ls_files_z(self.tmp),
                         ['docs/x.txt', 'novel/a.html', 'novel/b c.jpg', 'novel/p.GIF'])
        self.assertIsNone(gitinfo.ls_files_z(self.tmp + '-none'))

    def test_image_files(self):
        self.assertEqual(gitinfo.image_files(self.tmp, 'novel'), ['novel/b c.jpg', 'novel/p.GIF'])

    def test_added_files_log(self):
        log = gitinfo.added_files_log(self.tmp)
        heads = [line for line in log.splitlines() if line.startswith('__C__')]
        self.assertEqual(len(heads), 2)
        self.assertTrue(heads[0].endswith('|981173106|first'))
        # 初回追加だけが載る (a.html は 2 回目の変更では載らない)
        self.assertEqual(log.count('novel/a.html'), 1)
        self.assertIn('novel/b c.jpg', log.split(heads[1])[1])

    def test_show_file_and_short_rev(self):
        self.assertEqual(gitinfo.show_file(self.tmp, 'HEAD~1', 'novel/a.html'), 'v1\n')
        self.assertEqual(gitinfo.show_file(self.tmp, 'HEAD', 'novel/a.html'), 'v2\n')
        rev = gitinfo.short_rev(self.tmp, 'HEAD')
        self.assertTrue(git(self.tmp, 'rev-parse', 'HEAD').startswith(rev))
        with self.assertRaises(subprocess.CalledProcessError):
            gitinfo.show_file(self.tmp, 'HEAD', 'none.txt')


if __name__ == '__main__':
    unittest.main()
