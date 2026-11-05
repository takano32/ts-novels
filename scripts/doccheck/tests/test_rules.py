# -*- coding: utf-8 -*-
"""rules.py: 使わない語の各行に当たる 1 文と、当たらない文。番号・リンクの正規表現。"""
import unittest

from doccheck.rules import BANNED, CODE, LINK, S_REF, T_REF, TASK_ITEM

# BANNED の名前 → それにだけ当たる 1 文
HITS = {
    '廃止した取り下げ番号 (TD-…・TD.n)': '取り下げ TD.3 の件',
    '廃止した TODO.md': 'TODO.md を見る',
    '§': '§3 を見る',
    '台帳': '作品の台帳',
    'Phase n': 'Phase 2 の作業',
    'CP-4': 'CP-4 の確認',
    '👤': '👤 人の判断',
    '本館/別館/旧館': '本館 に置く',
    '正典': '正典 として扱う',
    'Tier A/B/C': 'Tier B の作品',
    '略記 OF/VR/TF/VS/CO': '結果は VR で確かめる',
    '要決定事項の番号 Q + 数字': 'Q3 を決める',
    '記号 (A)〜(H)': '不具合 (B) を直す',
    '丸数字': '手順 ② を流す',
    '設計の版 v1.n': '設計 v1.2 に従う',
    '文書の版 v0/v1': 'ランブック v1 の手順',
    '第 n 段': '第 2 段 の作業',
    'Sn の 記号': 'S10 の B を見る',
    'Sn の 番号': 'S10 の 3 を見る',
    '決定 n': '決定 4 のとおり',
    '取り下げ番号の旧略記 TD-0': 'TD-012 の件',
    'cat + 数字': 'cat 3 の作品',
    'T0 / T+n': 'T+3 に確かめる',
    '審査員 n': '審査員 A の意見',
    '旧タスクリストの番号': '- [x] 3.1 やったこと',
    'n 節': '3 節 を見る',
    'A 案': 'A 案 を採る',
    '補足/原則/ルール n': '原則 2 に従う',
}
# どの行にも当たらない文 (数を表す「Sn の 11 項目」・英単語の中の略記・普通の言葉)
MISSES = ['S114 の 11 項目', 'Office と CORE と TFS', 'S12 の決まり', 'T45.2 の作業', '原則として 3 件', 'concat 3']


class BannedTest(unittest.TestCase):
    def test_every_rule_has_a_sample(self):
        self.assertEqual([n for n, _ in BANNED], list(HITS))

    def test_each_sample_hits_its_rule(self):
        for name, rx in BANNED:
            with self.subTest(name=name):
                self.assertTrue(rx.search(HITS[name]), HITS[name])

    def test_each_sample_hits_only_its_rule(self):
        # 旧略記 TD-0 の規則は 1 行目の規則 (TD-0 を含む) に含まれ、同じ文に 2 つが当たる (T45.2.40 で決める)
        overlap = {'取り下げ番号の旧略記 TD-0': ['廃止した取り下げ番号 (TD-…・TD.n)', '取り下げ番号の旧略記 TD-0']}
        for name, text in HITS.items():
            with self.subTest(name=name):
                self.assertEqual([n for n, rx in BANNED if rx.search(text)], overlap.get(name, [name]))

    def test_ordinary_text_hits_nothing(self):
        for text in MISSES:
            with self.subTest(text=text):
                self.assertEqual([n for n, rx in BANNED if rx.search(text)], [])


class RefTest(unittest.TestCase):
    def test_s_ref_range_and_next(self):
        self.assertEqual(S_REF.findall('S3 と S5〜S7、次は S9'), [('3', ''), ('5', '7')])
        self.assertEqual(S_REF.findall('xS.3 と .S.4 と S10a'), [('10', '')])

    def test_t_ref_depth_and_next(self):
        self.assertEqual(T_REF.findall('T1 と T45.2.8、次は T46'), ['T1', 'T45.2.8'])

    def test_code_and_link(self):
        line = '[a](x.md) と `[b](y.md)`'
        self.assertEqual(LINK.findall(CODE.sub('', line)), ['x.md'])

    def test_task_item(self):
        self.assertEqual(TASK_ITEM.match('  - [x] T1.2 やった').groups(), ('x', 'T1.2'))
        self.assertIsNone(TASK_ITEM.match('- [ ] T1.2.x'))
        self.assertIsNone(TASK_ITEM.match('- T1.2 チェックボックス無し'))


if __name__ == '__main__':
    unittest.main()
