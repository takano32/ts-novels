# -*- coding: utf-8 -*-
"""tslib/qa.py: catalog/QA.md の本文 (レポートの転記だけで、再計算しない)。"""
import unittest

from tslib import qa


class QaTest(unittest.TestCase):
    def test_pct_and_table(self):
        self.assertEqual(qa.pct(1, 3), '33.33%')
        self.assertEqual(qa.pct(1, 0), '—')
        self.assertEqual(qa.table([('a', 1), ('b', 'x')]), '| 項目 | 値 |\n|---|---:|\n| a | 1 |\n| b | x |\n')

    def test_special_counts(self):
        eps = [{'entry_type': 'image'}, {'entry_type': 'html', 'source_anchor': 'p'},
               {'body_convert_exempt': True, 'entry_role': 'unattributed'}, {'commented_out': True},
               {'entry_role': 'work', 'overrides_applied': [1]}, {'entry_role': 'notice'}]
        self.assertEqual(qa.special_counts(eps), {
            'image': 1, 'anchor': 1, 'plain-text-repost': 1, 'unattributed': 1, 'commented-out-in-source': 1,
            'human-override': 1, 'notice': 1})

    def test_render_without_reports(self):
        text = qa.render({}, [{'corpus': 'honkan'}, {'corpus': 'legacy'}], 0)
        self.assertTrue(text.startswith('# catalog QA レポート\n'))
        self.assertEqual(text.count('_未実行_'), 3)
        self.assertIn('_未実行 — `catalog/reports/body_convert.json` が無い_', text)
        self.assertIn('| episodes 合計 | 2 |', text)
        self.assertIn('| 自己検査 (catalog_build) | **要確認** |', text)
        self.assertNotIn('## 10.', text)
        self.assertTrue(text.endswith('\n') and not text.endswith('\n\n'))

    def test_render_body_convert_mismatch_and_optional_sections(self):
        reports = {'cnv': {'convertible': 3, 'status': {'md': 2, 'raw-fallback': 1}, 'md_rate_pct': 66.67,
                           'acceptance': {'>=85% of convertible': False, 'invariant violations among md': 0},
                           'modes': {'br-para': 2}},
                   'rep': {'patched_existing': ['a'], 'added_extern_repost': [], 'missing_inputs': [], 'note': '注記'},
                   'qa3': {'summary': {'n': 2, 'seed': 1, 'counts': {'ok': 2}, 'problem_rate_pct': 0}}}
        text = qa.render(reports, [], 1)
        self.assertIn('| `bodies/*.md` の実ファイル数 (照合) | 1 ← レポートと不一致 |', text)
        self.assertIn('| 受け入れ (合格率 ≥85%) | **要確認** |', text)
        self.assertIn('| 文庫未掲載として新規収録 | — |', text)
        self.assertIn('| 入力の欠落 | なし |', text)
        self.assertIn('## 11. 本番ページの原本併読 QA (T9.3、独立検算)', text)
        self.assertIn('| 問題率 | 0.0% |', text)
        text = qa.render(reports, [], 2)
        self.assertIn('| `bodies/*.md` の実ファイル数 (照合) | 2 |', text)


if __name__ == '__main__':
    unittest.main()
