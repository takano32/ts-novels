# -*- coding: utf-8 -*-
"""tslib/paths.py: 定位置の組み立て・URL の小道具。"""
import os
import unittest

from tslib import paths


class PathsTest(unittest.TestCase):
    def test_repo_root_has_scripts(self):
        self.assertTrue(os.path.isfile(os.path.join(paths.REPO_ROOT, 'scripts', 'wp', 'tslib', 'paths.py')))

    def test_fixed_locations(self):
        r = '/r'
        self.assertEqual(paths.catalog_dir(r), '/r/catalog')
        self.assertEqual(paths.reports_dir(r), '/r/catalog/reports')
        self.assertEqual(paths.report_path(r, 'x.json'), '/r/catalog/reports/x.json')
        self.assertEqual(paths.episodes_path(r), '/r/catalog/episodes.jsonl')
        self.assertEqual(paths.works_path(r), '/r/catalog/works.jsonl')
        self.assertEqual(paths.authors_path(r), '/r/catalog/authors.json')
        self.assertEqual(paths.terms_path(r), '/r/catalog/terms.json')
        self.assertEqual(paths.slug_overrides_path(r), '/r/catalog/slug_overrides.yml')
        self.assertEqual(paths.work_overrides_path(r), '/r/catalog/work_overrides.yml')
        self.assertEqual(paths.convert_report_path(r), '/r/catalog/convert_report.jsonl')
        self.assertEqual(paths.denylist_path(r), '/r/takedown/denylist.yml')
        self.assertEqual(paths.bodies_dir(r), '/r/bodies')
        self.assertEqual(paths.payloads_dir(r), '/r/payloads')
        self.assertEqual(paths.payloads_docs_dir(r), '/r/payloads-docs')
        self.assertEqual(paths.kansou_board_path(r, 'johdan'), '/r/~ts/kansou/bbs@log_johdan.cgi')

    def test_kansou_annex_url(self):
        self.assertEqual(paths.kansou_annex_url('https://a.example/', 'johdan'),
                         'https://a.example/~ts/kansou/bbs@log_johdan.cgi')
        self.assertIsNone(paths.kansou_annex_url('https://a.example/', None))
        self.assertIsNone(paths.kansou_annex_url('https://a.example/', ''))

    def test_with_trailing_slash(self):
        self.assertEqual(paths.with_trailing_slash('https://a.example'), 'https://a.example/')
        self.assertEqual(paths.with_trailing_slash('https://a.example/'), 'https://a.example/')

    def test_constants(self):
        self.assertTrue(paths.DEFAULT_ANNEX_BASE.endswith('/'))
        self.assertEqual(paths.ORIG_BASE, 'http://ts.novels.jp/')
        self.assertEqual(paths.YAYS_PREFIX, '~yays/library/')


if __name__ == '__main__':
    unittest.main()
