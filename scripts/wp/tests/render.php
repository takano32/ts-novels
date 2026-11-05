<?php
/**
 * 表示部品の文言と計算 (specs/S41.md の書誌カード、S195.md のサイドバー)。scripts/wp/ts_library_test.php から読む。
 */

check('読了目安: 200 字未満は出さない', TS_Render_Text::reading_minutes(199), null);
check('読了目安: 200 字は約 1 分 (切り上げではなく最低 1)', TS_Render_Text::reading_minutes(200), 1);
check('読了目安: 550 字/分で四捨五入', [TS_Render_Text::reading_minutes(825), TS_Render_Text::reading_minutes(824)], [2, 1]);
check('読了目安: 長い本文', TS_Render_Text::reading_minutes(55000), 100);

check('回収経路: 配列でなければ null', TS_Render_Text::provenance_label('wayback'), null);
check('回収経路: route_label があればそのまま', TS_Render_Text::provenance_label(['route' => 'wayback', 'route_label' => '手動の保存']), '手動の保存');
check('回収経路: route の表示', array_map(function ($r) { return TS_Render_Text::provenance_label(['route' => $r]); },
      ['wayback', 'mirror', 'author-repost', 'megalodon', 'commoncrawl']),
      ['Internet Archive Wayback Machine', 'サイト原本のミラー', '作者本人による再掲', 'ウェブ魚拓', 'Common Crawl']);
check('回収経路: method でも引ける', TS_Render_Text::provenance_label(['method' => 'mirror']), 'サイト原本のミラー');
check('回収経路: 知らない route はそのまま', TS_Render_Text::provenance_label(['route' => 'ftp']), 'ftp');
check('回収経路: 何も無ければ null', TS_Render_Text::provenance_label([]), null);

check('本文の注記: 整形表示', TS_Render_Text::body_note('converted'),
      'この本文は読みやすさのため組版を整えた表示です。文章そのものは原本と機械照合で一致しています。');
check('本文の注記: converted-text も同じ', TS_Render_Text::body_note('converted-text'), TS_Render_Text::body_note('converted'));
check('本文の注記: 原本の HTML', TS_Render_Text::body_note('raw-fallback'), 'この本文は原本の HTML をそのまま表示しています。');
check('本文の注記: ほかは出さない', [TS_Render_Text::body_note(null), TS_Render_Text::body_note('placeholder'), TS_Render_Text::body_note('takedown')], [null, null, null]);

check('サイドバーの索引入口は TS_INDEX_KINDS の中 (オススメの環は出さない。S101)',
      array_values(array_diff(array_column(TS_Render_Text::SIDEBAR_INDEXES, 0), TS_INDEX_KINDS)), []);
check('サイドバーの索引入口の順', array_column(TS_Render_Text::SIDEBAR_INDEXES, 0), ['kana', 'timeline', 'bunrui', 'vocabulary', 'docs']);
check('この文庫について', array_column(TS_Render_Text::SIDEBAR_ABOUT, 0), ['/authors/yays/', '/about/', '/archive/', '/takedown/']);
check('表示名に内部語を出さない (S200)', preg_match('/本館|別館|旧館|WP サイト|旧サイト/u',
      implode(' ', array_merge(array_column(TS_Render_Text::SIDEBAR_INDEXES, 1), array_column(TS_Render_Text::SIDEBAR_ABOUT, 1)))), 0);

check('作品カードの添え書き: 作者・初出・全N話の順', TS_Render_Text::workcard_bits('星野 灯', '2003/04/01', 3), ['星野 灯', '2003/04/01', '全3話']);
check('作品カードの添え書き: 1 話きりと単発 (0) は全N話を出さない', [TS_Render_Text::workcard_bits('a', 'd', 1), TS_Render_Text::workcard_bits('a', 'd', 0)],
      [['a', 'd'], ['a', 'd']]);
check('作品カードの添え書き: null は出さない (空なら何も無い)', [TS_Render_Text::workcard_bits(null, '2005/01/01', 2), TS_Render_Text::workcard_bits(null, null, 0)],
      [['2005/01/01', '全2話'], []]);
check('作品カードの添え書き: 受けた文字列はそのまま (エスケープは呼び出し側)', TS_Render_Text::workcard_bits('A &amp; B', '', 2), ['A &amp; B', '', '全2話']);

// 期待値は 0e8c7712 の themes/ts-bunko/index.php:38-39 と front-page.php:14-15 の文言
check('トップの案内文', TS_Render_Text::home_intro(1234), '開くたびに違う作品をお出しします。1234 篇からの偶然の出会いをどうぞ。');
check('トップの案内文: 0 篇でもそのまま', TS_Render_Text::home_intro(0), '開くたびに違う作品をお出しします。0 篇からの偶然の出会いをどうぞ。');

// 期待値は 0e8c7712 の themes/ts-bunko/single.php:14・:36・:39・:59-61・:65 の分岐と文言
check('表示の型: ts_work でなければ素表示 (子の数に依らない)', [TS_Render_Text::reader_mode('ts_doc', 0), TS_Render_Text::reader_mode('ts_dojo', 3), TS_Render_Text::reader_mode('page', 0)],
      ['plain', 'plain', 'plain']);
check('表示の型: 子があれば目次、無ければリーダー', [TS_Render_Text::reader_mode('ts_work', 2), TS_Render_Text::reader_mode('ts_work', 1), TS_Render_Text::reader_mode('ts_work', 0)],
      ['toc', 'toc', 'reader']);
check('リーダーの class: raw-fallback だけ is-raw', [TS_Render_Text::reader_classes('raw-fallback'), TS_Render_Text::reader_classes('converted'),
      TS_Render_Text::reader_classes(null), TS_Render_Text::reader_classes('')], [['ts-reader', 'is-raw'], ['ts-reader'], ['ts-reader'], ['ts-reader']]);
check('目次の見出し', [TS_Render_Text::toc_heading(3), TS_Render_Text::toc_heading(1)], ['目次 (全3話)', '目次 (全1話)']);
