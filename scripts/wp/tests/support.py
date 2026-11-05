# -*- coding: utf-8 -*-
"""試験の小道具: 一時ディレクトリ・依存の有無。"""
import importlib.util
import os
import shutil
import tempfile
import unittest

HAS_YAML = importlib.util.find_spec('yaml') is not None
HAS_KAKASI = importlib.util.find_spec('pykakasi') is not None
HAS_BS4 = (importlib.util.find_spec('bs4') is not None
           and importlib.util.find_spec('html5lib') is not None)
HAS_GIT = shutil.which('git') is not None
DATA = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')


class TempDirCase(unittest.TestCase):
    """setUp で self.tmp に空の一時ディレクトリを作り、tearDown で消す。"""

    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix='tslib-test-')

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def write(self, rel, content, mode='w', encoding='utf-8'):
        """self.tmp/rel にファイルを書き、そのパスを返す。content が bytes なら mode='wb'。"""
        path = os.path.join(self.tmp, rel)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        if isinstance(content, bytes):
            with open(path, 'wb') as fh:
                fh.write(content)
        else:
            with open(path, mode, encoding=encoding) as fh:
                fh.write(content)
        return path

    def read_bytes(self, rel):
        with open(os.path.join(self.tmp, rel), 'rb') as fh:
            return fh.read()
