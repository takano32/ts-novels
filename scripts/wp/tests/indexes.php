<?php
/**
 * 索引ページ群 (T10.3) の種類・題名・並び。scripts/wp/ts_library_test.php から読む。
 */

check('索引の種類', TS_INDEX_KINDS, ['kana', 'timeline', 'bunrui', 'vocabulary', 'osusume', 'docs']);
check('題名は種類ごとにある', array_keys(TS_Index_Text::TITLES), TS_INDEX_KINDS);
// 期待値は 0e8c7712 の themes/ts-bunko/ts-index.php の表 (T45.1.5.4 でテーマから消し、この表を使う)
check('索引の題名', TS_Index_Text::TITLES, ['kana' => '作者索引', 'timeline' => '年表', 'bunrui' => '分類索引',
      'vocabulary' => 'キーワード索引', 'osusume' => 'オススメの環', 'docs' => '運営文書・資料']);
check('テーマの ts-index.php に題名の表が無い (TS_Index_Text::TITLES を使う)', [preg_match_all("/'([a-z]+)' => '([^']+)'/u", file_get_contents("$root/themes/ts-bunko/ts-index.php")),
      strpos(file_get_contents("$root/themes/ts-bunko/ts-index.php"), 'TS_Index_Text::TITLES') !== false], [0, true]);
// 期待値は 0e8c7712 の themes/ts-bunko/ts-year.php:5 と includes/indexes.php の document_title_parts の文言
check('年別一覧の題名', [TS_Index_Text::year_title(2003), TS_Index_Text::year_title(0)], ['2003年の作品', '0年の作品']);
check('テーマの ts-year.php は年の題名を組み立てない', [strpos(file_get_contents("$root/themes/ts-bunko/ts-year.php"), "'年の作品"),
      strpos(file_get_contents("$root/themes/ts-bunko/ts-year.php"), 'TS_Index_Text::year_title') !== false], [false, true]);

check('五十音行 → 欧文 → 想定外 → 他', TS_Index_Text::kana_group_order(['他', 'Z', 'か行', 'x9', 'A', 'あ行', 'わ行']),
      ['あ行', 'か行', 'わ行', 'A', 'Z', 'x9', '他']);
check('無い行は出さない', TS_Index_Text::kana_group_order(['さ行']), ['さ行']);
check('他だけ', TS_Index_Text::kana_group_order(['他']), ['他']);
check('空', TS_Index_Text::kana_group_order([]), []);
check('数字の見出しも落とさない (配列の添字が整数になっても)', TS_Index_Text::kana_group_order(array_keys(['1' => [], 'あ行' => []])), ['あ行', 1]);

check('いまの一作: 同じ時間なら同じ添字', TS_Index_Text::pick_index(2026110512, 1443), TS_Index_Text::pick_index(2026110512, 1443));
check('いまの一作: 添字は範囲内', array_unique(array_map(function ($h) { $i = TS_Index_Text::pick_index(2026110500 + $h, 7); return $i >= 0 && $i < 7; }, range(0, 23))), [true]);
check('いまの一作: 1 作なら 0', TS_Index_Text::pick_index(2026110512, 1), 0);
check('いまの一作: 隣の時間で違う作品になる (攪拌)', TS_Index_Text::pick_index(2026110512, 1443) !== TS_Index_Text::pick_index(2026110513, 1443), true);
// 期待値は分割前 (0e8c7712) の ts_bunko_todays_pick の式をサーバの PHP 8.0 で計算した値 (2026-11-05)
check('いまの一作: 分割前と同じ添字', [TS_Index_Text::pick_index(2026110512, 1443), TS_Index_Text::pick_index(2026110513, 1443),
      TS_Index_Text::pick_index(2026010100, 7), TS_Index_Text::pick_index(2099123123, 1443)], [757, 51, 1, 1092]);

check('運営文書の区分の並び', array_keys(TS_Index_Text::DOC_SECTIONS), ['gallery', 'comittee', 'columns', 'dialy', 'prehistory', 'notes']);
check('年表の最初の年', TS_Index_Text::TIMELINE_FROM, 1990);
check('分類の見出し (分類索引の並び。検索のファセットと共有)', TS_Index_Text::TAX_LABELS, ['ts_genre' => 'ジャンル', 'ts_type' => '変身のかたち', 'ts_world' => '共有世界']);
check('検索のファセットはジャンルと種別 (見出しは TAX_LABELS にある)', [TS_Index_Text::SEARCH_FACETS, array_values(array_diff(TS_Index_Text::SEARCH_FACETS, array_keys(TS_Index_Text::TAX_LABELS)))],
      [['ts_genre', 'ts_type'], []]);
