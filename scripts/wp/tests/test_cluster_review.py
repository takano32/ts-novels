# -*- coding: utf-8 -*-
"""tslib/cluster_review.py: 作品クラスタのレビュー資料の抽出と一次案。"""
import unittest

from tslib import cluster_review as cr


def ep(eid, title):
    return {'episode_id': eid, 'title': title, 'source_path': 'n/d/%s.html' % eid, 'date': '2001-01-01'}


def work(episodes, evidence=('filename-stem',)):
    return {'work_slug': 'au-x', 'title': 'X', 'author_slug': 'au', 'author_display': '作者|名',
            'episode_count': len(episodes), 'evidence': list(evidence), 'episodes': episodes}


class ClusterReviewTest(unittest.TestCase):
    def test_is_target_and_weak(self):
        self.assertTrue(cr.is_target(work([ep('e1', '赤い花 第1話'), ep('e2', '青い空')])))
        self.assertFalse(cr.is_target(work([ep('e1', '赤い花'), ep('e2', '赤い実')])))     # 接頭辞 2 文字
        self.assertFalse(cr.is_target(work([ep('e1', '赤い花 第1話'), ep('e2', '赤い花 第2話')])))
        self.assertFalse(cr.is_target(work([ep('e1', 'あ')])))
        self.assertTrue(cr.is_weak(work([], ['filename-stem', 'title-prefix'])))
        self.assertFalse(cr.is_weak(work([], ['series-html', 'title-prefix'])))

    def test_core_families(self):
        fams = cr.core_families(work([ep('e1', '赤い花 第1話'), ep('e2', '青い空'), ep('e3', '赤い花 第2話')]))
        self.assertEqual([(c, [e['episode_id'] for e in eps]) for c, eps in fams],
                         [('赤い花', ['e1', 'e3']), ('青い空', ['e2'])])

    def test_proposal_three_branches(self):
        w = work([ep('e1', '赤い花 第1話'), ep('e2', '青い空'), ep('e3', '赤い花 第2話'),
                  ep('e4', '白い雲 前編'), ep('e5', '白い雲 後編')])
        text, ylines = cr.proposal(w)
        self.assertEqual(text[0], '- 一次案: **分割 + 切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「赤い花」の 2 話)')
        self.assertEqual(ylines, ['  "au|X":', '    confirmed: true', '    split:', '      - work_title: "白い雲"',
                                  '        episodes: [e4, e5]', '    detach: [e2]'])
        text, ylines = cr.proposal(dict(w, evidence=['series-html', 'filename-stem']))
        self.assertTrue(text[0].startswith('- 一次案: **○** (強い根拠 `series-html` で繋がっている。'))
        self.assertEqual(ylines, ['  "au|X":', '    confirmed: true'])
        text, _ = cr.proposal(work([ep('e1', '赤い花'), ep('e2', '赤い実')]))
        self.assertEqual(text, ['- 一次案: **○** (芯は違うが、どれも共通接頭辞 2 文字以上でつながる)'])

    def test_render_work(self):
        out = cr.render_work(work([ep('e1', '赤い花 第1話'), ep('e2', '青い空')]), 3, {'e1': {'corpus': 'honkan'}})
        self.assertEqual(out[:6], ['### 3. `au-x` — X', '', '- 根拠: `filename-stem` / 話数: 2 / 作者: 作者\\|名 (`au`)', '',
                                   '| episode_id | 題名 | ファイル名 | 掲載日 | corpus |', '|---|---|---|---|---|'])
        self.assertEqual(out[6:8], ['| `e1` | 赤い花 第1話 | e1.html | 2001-01-01 | honkan |',
                                    '| `e2` | 青い空 | e2.html | 2001-01-01 | — |'])
        self.assertEqual(out[-2:], ['</details>', ''])

    def test_slug_rows(self):
        old = [{'work_slug': 'b', 'episodes': [{'episode_id': 'e2'}]}, {'work_slug': 'a', 'episodes': [{'episode_id': 'e1'}]}]
        new = [{'work_slug': 'a2', 'episodes': [{'episode_id': 'e1'}]}, {'work_slug': 'b', 'episodes': [{'episode_id': 'e2'}]}]
        self.assertEqual(cr.slug_rows(old, new), [(old[1], ['a2'])])

    def test_render_report(self):
        weak = dict(work([ep('e1', '赤い花 第1話'), ep('e2', '青い空')]), work_slug='b-weak')
        strong = dict(work([ep('e3', '白い雲'), ep('e4', '黒い夜')], ['series-html']), work_slug='a-strong')
        other = dict(work([ep('e5', '赤い花')]), work_slug='c-single')
        L = cr.render_report([strong, other, weak], {}, '2026-11')
        self.assertEqual(L[0], '# 作品クラスタのレビュー (2026-11)')
        self.assertIn('- 件数: **2 作品** (根拠が規則 5/6 だけ = 弱い根拠 1 を先頭に、強い根拠のあるもの 1 を後に)', L)
        heads = [x for x in L if x.startswith('## ') or x.startswith('### ')]
        self.assertEqual(heads, ['## 弱い根拠だけの作品 (1)', '### 1. `b-weak` — X',
                                 '## 強い根拠のある作品 (1)', '### 2. `a-strong` — X'])
        self.assertEqual(cr.split_targets([strong, other, weak]), ([weak], [strong]))
        self.assertEqual(cr.render_report([], {}, '2026-11')[-2:], ['## 強い根拠のある作品 (0)', ''])

    def test_render_slug_section(self):
        old = [{'work_slug': 'a', 'title': 'A|1', 'episode_count': 1, 'episodes': [{'episode_id': 'e1'}]},
               {'work_slug': 'b', 'title': 'B', 'episode_count': 1, 'episodes': [{'episode_id': 'e2'}]}]
        new = [{'work_slug': 'a2', 'title': 'A2', 'episode_count': 1, 'episodes': [{'episode_id': 'e1'}]},
               {'work_slug': 'c', 'title': 'C', 'episode_count': 2,
                'episodes': [{'episode_id': 'e2'}, {'episode_id': 'e3'}]}]
        rows = cr.slug_rows(old, new)
        L = cr.render_slug_section(old, new, rows, 'abc1234')
        self.assertEqual(L[0], '## slug が変わった作品 (abc1234 との比較)')
        self.assertEqual(L[4], '- works: 2 → 2 / slug が変わった作品: **2** (うち他の作品と束ね直されたもの 1)')
        self.assertEqual(L[-3:], ['| `a` | `a2` | A\\|1 | A2 | 1 → 1 |', '| `b` | `c` | B | C | 1 → 2 |', ''])


if __name__ == '__main__':
    unittest.main()
