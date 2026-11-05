# -*- coding: utf-8 -*-
"""tslib/slugs.py: ローマ字 slug の候補と slug_overrides.yml の往復。"""
import os
import unittest

from tslib import slugs
from tests.support import TempDirCase, HAS_YAML, HAS_KAKASI


class RomanizeTest(unittest.TestCase):
    def test_ascii_path(self):
        self.assertEqual(slugs.romanize('ＫＥＢＯ'), ('kebo', 'ascii'))
        self.assertEqual(slugs.romanize('Foo Bar!'), ('foo-bar', 'ascii'))
        self.assertEqual(slugs.romanize(''), ('', 'fallback'))
        self.assertTrue(slugs.is_ascii_word('toshi９'))
        self.assertFalse(slugs.is_ascii_word('!!'))
        self.assertEqual(slugs.slugify_ascii('A_b--C'), 'a-b-c')

    @unittest.skipUnless(HAS_KAKASI, 'pykakasi が無い')
    def test_pykakasi_path(self):
        slug, source = slugs.romanize('学園')
        self.assertEqual(source, 'pykakasi')
        self.assertRegex(slug, r'^[a-z0-9-]+$')

    def test_unique_slug(self):
        used = {'a', 'a-2'}
        self.assertEqual(slugs.unique_slug('a', used), 'a-3')
        self.assertIn('a-3', used)
        self.assertEqual(slugs.unique_slug('b', used), 'b')
        used = {'term-1'}
        self.assertEqual(slugs.unique_slug('', used), 'term-2')
        self.assertEqual(slugs.unique_slug(None, used, fallback_prefix='author'), 'author-1')


class OverridesTest(TempDirCase):
    def test_merge_section_keeps_confirmed_and_marks_stale(self):
        existing = {'authors': {
            'A': {'slug': 'aa', 'status': 'confirmed'},
            'B': {'slug': 'b-old', 'status': 'candidate'},
            'C': {'slug': 'cc', 'status': 'confirmed'},
            'D': {'slug': 'dd', 'status': 'candidate'}}, 'other': {'x': 1}}
        entries = {'A': {'slug': 'a-new', 'status': 'candidate'},
                   'B': {'slug': 'b-new', 'status': 'candidate'}}
        out = slugs.merge_section(existing, 'authors', entries)
        self.assertEqual(out['authors'], {
            'A': {'slug': 'aa', 'status': 'confirmed'},
            'B': {'slug': 'b-new', 'status': 'candidate'},
            'C': {'slug': 'cc', 'status': 'confirmed', 'stale': True}})
        self.assertEqual(out['other'], {'x': 1})
        self.assertNotIn('stale', existing['authors']['C'])

    def test_pending_count(self):
        data = {'s1': {'a': {'status': 'confirmed'}, 'b': {'status': 'candidate'}, 'c': 'x'},
                's2': {'d': {}}, 's3': None}
        self.assertEqual(slugs.pending_count(data), 2)
        self.assertEqual(slugs.pending_count(data, 's1'), 1)
        self.assertEqual(slugs.pending_count(None), 0)

    @unittest.skipUnless(HAS_YAML, 'PyYAML が無い')
    def test_save_and_load_roundtrip(self):
        path = os.path.join(self.tmp, 'catalog', 'slug_overrides.yml')
        data = {'terms_genre': {'学園': {'slug': 'gakuen', 'status': 'candidate', 'source': 'pykakasi'}},
                'authors': {'城弾': {'slug': 'johdan', 'status': 'confirmed', 'source': 'kansou-board'}}}
        slugs.save_overrides(path, data, notes=['注記 1'])
        with open(path, encoding='utf-8') as fh:
            text = fh.read()
        self.assertTrue(text.startswith(slugs.HEADER + '# 注記 1\n\nauthors:\n'))
        self.assertEqual(slugs.load_overrides(path), data)
        self.assertEqual(slugs.load_overrides(path + '.none'), {})

    def test_overrides_notes(self):
        # 3 段 (terms_build・authors_build・work_builder) が同じ注記を書く約束。字面は catalog/slug_overrides.yml の頭の注記と同じ
        self.assertEqual(slugs.OVERRIDES_NOTES, [
            'terms_* セクション = 分類語彙の slug (scripts/wp/terms_build.py)',
            'authors セクション = 作者 slug (scripts/wp/authors_build.py)',
            'works セクション = 作品 slug (scripts/wp/work_builder.py)',
        ])


if __name__ == '__main__':
    unittest.main()
