# -*- coding: utf-8 -*-
"""tslib/catalog/fragments.py・mailto.py・keys.py・provenance.py: 目録の断片と冪等キー。"""
import unittest

from tslib.catalog import fragments as fr
from tslib.catalog import keys
from tslib.catalog import provenance as pv
from tslib.catalog.mailto import scrub_mailto


class FragmentsTest(unittest.TestCase):
    def test_split_tokens_nfkc(self):
        self.assertEqual(fr.split_tokens('ＴＳ　学園 \n ｶﾅ'), (['TS', '学園', 'カナ'], ['ＴＳ', '学園', 'ｶﾅ']))
        self.assertEqual(fr.split_tokens(''), ([], []))
        self.assertEqual(fr.split_tokens(None), ([], []))

    def test_marker_value_bold_and_plain(self):
        row = '<B>【あらすじ】</B> 本文【第2話はこちら】 <B>【コメント】</B> 作者より'
        self.assertEqual(fr.marker_value(row, '【あらすじ】'), ' 本文【第2話はこちら】 ')
        self.assertEqual(fr.marker_value(row, '【コメント】'), ' 作者より')
        with self.assertRaises(KeyError):
            fr.marker_value(row, '【推薦文】')
        row4 = '【ジャンル】 学園 【種別】 短編'
        self.assertEqual(fr.marker_value(row4, '【ジャンル】', fr.STOP_PLAIN), ' 学園 ')
        # 太字の止めでは 【 だけでは止まらない
        self.assertEqual(fr.marker_value(row4, '【ジャンル】'), ' 学園 【種別】 短編')

    def test_marker_values_missing_is_none(self):
        got = fr.marker_values('<B>【コメント】</B>x', [('comment', '【コメント】'), ('arasuji', '【あらすじ】')])
        self.assertEqual(got, {'comment': 'x', 'arasuji': None})

    def test_drop_nav_anchors_and_links(self):
        cell = '前<a href="b.html">【第2話はこちら】</a>後<a href="c.html">参考</a>'
        self.assertEqual(fr.drop_nav_anchors(cell), '前後<a href="c.html">参考</a>')
        self.assertEqual(fr.nav_links_of(cell), [{'href': 'b.html', 'label': '第2話はこちら'}])
        self.assertEqual(fr.plain_links_of(cell), [{'href': 'c.html', 'label': '参考'}])
        self.assertEqual(fr.body_text(cell), '前後参考')
        self.assertIsNone(fr.body_text(''))
        self.assertIsNone(fr.body_text(None))


class MailtoTest(unittest.TestCase):
    def test_four_forms(self):
        # 1 ラベルが名前 → 名前を残す
        out, d = scrub_mailto('<a href="mailto:taro@example.com">たろう</a>')
        self.assertEqual((out, d), ('たろう', ['taro@example.com']))
        # 2 ラベル自体がアドレス → 消す (宛先とラベルの 2 つを記録)
        out, d = scrub_mailto("<A HREF='mailto:taro@example.com'>taro@example.com</A>")
        self.assertEqual((out, d), ('', ['taro@example.com', 'taro@example.com']))
        # 3 閉じ忘れで残った属性 → href="#"
        out, d = scrub_mailto('<a href=mailto:jiro@example.com>x')
        self.assertEqual((out, d), ('<a href="#">x', ['jiro@example.com']))
        # 4 素のアドレス表記 → 消す。ファイル名の @ は残す
        out, d = scrub_mailto('連絡 hana@example.com まで index@08201937.html')
        self.assertEqual((out, d), ('連絡  まで index@08201937.html', ['hana@example.com']))

    def test_empty_label(self):
        out, d = scrub_mailto('<a href="mailto:">  </a>')
        self.assertEqual((out, d), ('', []))


class KeysTest(unittest.TestCase):
    def test_split_href(self):
        self.assertEqual(keys.split_href('./novel/a.html#p2'), ('novel/a.html', 'p2', 'local'))
        self.assertEqual(keys.split_href('novel/a.html#'), ('novel/a.html', None, 'local'))
        self.assertEqual(keys.split_href('http://www.example.com/x/y.html#z'),
                         ('external/www.example.com/x/y.html', 'z', 'external'))
        self.assertEqual(keys.split_href('http://www.example.com'),
                         ('external/www.example.com/', None, 'external'))

    def test_episode_id_of(self):
        self.assertEqual(keys.episode_id_of('novel/2000/a.html', None), 'novel__2000__a.html')
        self.assertEqual(keys.episode_id_of('novel/a.html', 'p2', '20010203'), 'novel__a.html@p2+20010203')
        self.assertEqual(keys.episode_id_of('a.html', None, '20010203'), 'a.html+20010203')

    def test_url_path(self):
        self.assertEqual(keys.url_path('novel/Winter sea.htm'), 'novel/Winter%20sea.htm')
        self.assertEqual(keys.url_path('~ts/bbs@log_x&y.cgi'), '~ts/bbs@log_x&y.cgi')
        self.assertEqual(keys.url_path('a/日本.html'), 'a/%E6%97%A5%E6%9C%AC.html')

    def test_entry_type_and_urls(self):
        self.assertEqual(keys.entry_type_of('a/b.JPG', 'local'), 'image')
        self.assertEqual(keys.entry_type_of('external/h/x', 'external'), 'external')
        self.assertEqual(keys.entry_type_of('a.html', 'local'), 'html')
        self.assertEqual(keys.orig_url_of('a b.html', 'x', 'local'), 'http://ts.novels.jp/a%20b.html#x')
        self.assertEqual(keys.orig_url_of('external/h.example.com/p.html', None, 'external'),
                         'http://h.example.com/p.html')
        self.assertEqual(keys.annex_url_of('https://a.example/', 'n/a b.html', 'z'),
                         'https://a.example/n/a%20b.html#z')
        self.assertEqual(keys.annex_url_of('https://a.example/', 'n/a.html'), 'https://a.example/n/a.html')


class ProvenanceTest(unittest.TestCase):
    def test_classify_route_first_hit_and_mixed(self):
        self.assertEqual(pv.classify_route('ウェブ魚拓から回収'), ('megalodon', 'ウェブ魚拓', False))
        key, _, mixed = pv.classify_route('Wayback と CommonCrawl')
        self.assertEqual((key, mixed), ('commoncrawl', True))
        self.assertEqual(pv.classify_route('なにか'), ('other', 'その他 (コミット件名から分類できず)', False))

    def test_parse_added_log_first_add_wins(self):
        log = ('__C__aaaaaaaaaaaaaaaa|981173106|Wayback 回収\n\nnovel/a.html\nnovel/b.html\n'
               '__C__bbbbbbbbbbbbbbbb|981259506|pixiv 再掲\n\nnovel/a.html\n')
        got = pv.parse_added_log(log)
        self.assertEqual(got['novel/a.html']['commit'], 'aaaaaaaaaaaa')
        self.assertEqual(got['novel/a.html']['acquired_at'], '2001-02-03')
        self.assertEqual(list(got['novel/a.html']), ['commit', 'acquired_at', 'commit_subject',
                                                     'route', 'route_label', 'route_mixed'])
        self.assertEqual(got['novel/b.html']['route'], 'wayback')

    def test_provenance_for(self):
        pm = pv.parse_added_log('__C__a|0|Wayback\nn/a b.html\n__C__b|0|pixiv\nn/c.html\n')
        rec = pv.provenance_for('n/a b.html', pm)
        self.assertEqual(rec['method'], 'git-history')
        self.assertEqual(rec['snapshot_query_url'], 'https://web.archive.org/web/2*/http://ts.novels.jp/n/a%20b.html')
        self.assertNotIn('snapshot_query_url', pv.provenance_for('n/c.html', pm))
        self.assertNotIn('method', pm['n/a b.html'])
        self.assertIsNone(pv.provenance_for('none', pm))


if __name__ == '__main__':
    unittest.main()
