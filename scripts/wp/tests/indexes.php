<?php
/**
 * 索引ページ群 (T10.3) の種類・題名・並び。scripts/wp/ts_library_test.php から読む。
 */

check('索引の種類', TS_INDEX_KINDS, ['kana', 'timeline', 'bunrui', 'vocabulary', 'osusume', 'docs']);
check('題名は種類ごとにある', array_keys(TS_Index_Text::TITLES), TS_INDEX_KINDS);
check('テーマの ts-index.php の題名の表と同じ', preg_match_all("/'([a-z]+)' => '([^']+)'/u", file_get_contents("$root/themes/ts-bunko/ts-index.php"), $m) === count(TS_INDEX_KINDS)
      && array_combine($m[1], $m[2]) === TS_Index_Text::TITLES, true);

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
check('いまの一作: 計算 (ymdh * 2654435761 % 2147483647 % n)', TS_Index_Text::pick_index(2026110512, 1443), (2026110512 * 2654435761 % 2147483647) % 1443);

check('運営文書の区分の並び', array_keys(TS_Index_Text::DOC_SECTIONS), ['gallery', 'comittee', 'columns', 'dialy', 'prehistory', 'notes']);
check('年表の最初の年', TS_Index_Text::TIMELINE_FROM, 1990);
