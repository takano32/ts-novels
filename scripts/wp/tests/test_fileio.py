# -*- coding: utf-8 -*-
"""tslib/fileio.py: 書式は生成物のバイト一致に懸かるので bytes で比べる。"""
import collections
import contextlib
import io
import unittest
from unittest import mock

from tslib import fileio
from tests.support import TempDirCase, HAS_YAML


class JsonTest(TempDirCase):
    def test_jsonl_format(self):
        p = self.write('a.jsonl', '')
        fileio.write_jsonl(p, [collections.OrderedDict([('b', 1), ('a', '日本')]), {'x': None}])
        self.assertEqual(self.read_bytes('a.jsonl'),
                         '{"b": 1, "a": "日本"}\n{"x": null}\n'.encode('utf-8'))
        self.assertEqual(fileio.read_jsonl(p), [{'b': 1, 'a': '日本'}, {'x': None}])

    def test_dumps_jsonl_has_no_trailing_newline(self):
        self.assertEqual(fileio.dumps_jsonl([{'a': 1}, {'b': 'é'}]), '{"a": 1}\n{"b": "é"}')

    def test_json_format(self):
        p = self.write('r.json', '')
        fileio.write_json(p, {'k': [1, 'あ']})
        self.assertEqual(self.read_bytes('r.json'), '{\n "k": [\n  1,\n  "あ"\n ]\n}\n'.encode('utf-8'))
        fileio.write_json(p, {'k': 1}, indent=2, newline=False)
        self.assertEqual(self.read_bytes('r.json'), b'{\n  "k": 1\n}')
        self.assertEqual(fileio.read_json(p), {'k': 1})


class TextTest(TempDirCase):
    def test_decode_guess_three_encodings(self):
        s = '日本語のテキスト'
        self.assertEqual(fileio.decode_guess(s.encode('utf-8')), (s, 'utf-8'))
        self.assertEqual(fileio.decode_guess(s.encode('cp932')), (s, 'cp932'))
        # 「字」の EUC-JP (BB FA) は cp932 では読めない (末尾の FA が後続バイトを欠く)
        self.assertEqual(fileio.decode_guess('漢字'.encode('euc_jp')), ('漢字', 'euc_jp'))

    def test_decode_guess_replace(self):
        text, enc = fileio.decode_guess(b'\x82\xa0\x82')
        self.assertEqual(enc, 'cp932-replace')
        self.assertTrue(text.startswith('あ'))

    def test_read_text_guess(self):
        p = self.write('s.txt', '漢字'.encode('cp932'))
        self.assertEqual(fileio.read_text_guess(p), ('漢字', 'cp932'))

    def test_read_text_or_empty(self):
        self.assertEqual(fileio.read_text_or_empty(self.tmp + '/none'), '')
        p = self.write('b.txt', b'a\xffb')
        self.assertEqual(fileio.read_text_or_empty(p), 'a�b')

    def test_md5_of(self):
        p = self.write('m.txt', b'abc')
        self.assertEqual(fileio.md5_of(p), '900150983cd24fb0d6963f7d28e17f72')
        self.assertIsNone(fileio.md5_of(self.tmp + '/none'))


@unittest.skipUnless(HAS_YAML, 'PyYAML が無い')
class YamlTest(TempDirCase):
    def test_read_yaml_missing_and_empty(self):
        self.assertEqual(fileio.read_yaml(self.tmp + '/none.yml'), {})
        self.assertEqual(fileio.read_yaml(self.write('e.yml', '')), {})

    def test_read_yaml_required(self):
        with self.assertRaises(FileNotFoundError):
            fileio.read_yaml_required(self.tmp + '/none.yml')
        self.assertEqual(fileio.read_yaml_required(self.write('e.yml', '')), {})
        self.assertEqual(fileio.read_yaml_required(self.write('a.yml', 'a: 1\n')), {'a': 1})

    def test_write_yaml_header_and_sorted_keys(self):
        p = self.write('w.yml', '')
        fileio.write_yaml(p, {'b': {'z': 1, 'y': '学園'}, 'a': [1]}, header='# 見出し\n\n')
        self.assertEqual(self.read_bytes('w.yml').decode('utf-8'),
                         '# 見出し\n\na:\n- 1\nb:\n  y: 学園\n  z: 1\n')


class ExitWithoutYamlTest(unittest.TestCase):
    def test_exit_without_yaml(self):
        err = io.StringIO()
        with mock.patch.object(fileio, 'yaml', None), contextlib.redirect_stderr(err):
            with self.assertRaises(SystemExit) as cm:
                fileio.exit_without_yaml('PyYAML が要ります (pip install pyyaml)')
        self.assertEqual((cm.exception.code, err.getvalue()), (2, 'PyYAML が要ります (pip install pyyaml)\n'))
        with mock.patch.object(fileio, 'yaml', object()):
            self.assertIsNone(fileio.exit_without_yaml())


if __name__ == '__main__':
    unittest.main()
