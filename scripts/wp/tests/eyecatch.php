<?php
/**
 * eyecatch の再設定 (scripts/wp/eyecatch_restore.php、S189) の判断と文面。scripts/wp/ts_library_test.php から読む。
 */

check('画像は先頭を使い、公開 URL の接頭辞を外す', TS_Eyecatch_Refs::source_rel(['/assets/annex-img/novel/a/1.jpg', '/assets/annex-img/novel/a/2.jpg']),
      'novel/a/1.jpg');
check('接頭辞が無ければそのまま', TS_Eyecatch_Refs::source_rel(['novel/b/x.gif']), 'novel/b/x.gif');
check('接頭辞は先頭のときだけ外す', TS_Eyecatch_Refs::source_rel(['x/assets/annex-img/y.png']), 'x/assets/annex-img/y.png');
check('候補が無ければ null', TS_Eyecatch_Refs::source_rel([]), null);

check('同じ中身の attachment があれば使い回す', TS_Eyecatch_Refs::decide('abc', ['abc' => 12]), 'reuse');
check('dry-run で取り込む予定にしたもの (attachment ID 0) も使い回す', TS_Eyecatch_Refs::decide('abc', ['abc' => 0]), 'reuse');
check('無ければ取り込む', TS_Eyecatch_Refs::decide('def', ['abc' => 12]), 'import');

check('終わりの 1 行', TS_Eyecatch_Refs::summary(3, 5, false), 'eyecatch: 3 話に設定 (対象 5 話)');
check('終わりの 1 行 (dry-run)', TS_Eyecatch_Refs::summary(0, 5, true), 'eyecatch: 0 話に設定 (dry-run) (対象 5 話)');
