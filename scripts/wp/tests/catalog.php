<?php
/**
 * catalog を読む側 (T8.2): ファイルの読み取り・語彙 (terms.json)・works の索引と話の slug・話のメタ・
 * 固定ページの原本の形 (S109)・wp ts verify の期待値。小さな作り物の catalog で試す
 * (本物の catalog との照合は `wp ts verify` が本番で行う)。scripts/wp/ts_library_test.php から読む。
 */

// ---------------------------------------------------------------- ファイルの読み取り
$tmp = tempnam(sys_get_temp_dir(), 'ts');
file_put_contents($tmp, "{\"a\":1}\n\n  \n{\"a\":2}\n");
check('JSONL (空行は飛ばす)', TS_Catalog_Files::read_jsonl($tmp), [['a' => 1], ['a' => 2]]);
file_put_contents($tmp, "{\"a\":1}\nnot json\n");
try { TS_Catalog_Files::read_jsonl($tmp); $msg = ''; } catch (RuntimeException $e) { $msg = $e->getMessage(); }
check('JSONL の壊れた行', $msg, "JSONL の壊れた行: $tmp");
file_put_contents($tmp, '{"x": [1, 2]}');
check('JSON', TS_Catalog_Files::read_json($tmp), ['x' => [1, 2]]);
file_put_contents($tmp, '{');
try { TS_Catalog_Files::read_json($tmp); $msg = ''; } catch (RuntimeException $e) { $msg = $e->getMessage(); }
check('JSON が読めない', $msg, "JSON が読めません: $tmp");
unlink($tmp);
try { TS_Catalog_Files::read_json($tmp); $msg = ''; } catch (RuntimeException $e) { $msg = $e->getMessage(); }
check('ファイルがありません', $msg, "ファイルがありません: $tmp");

// ---------------------------------------------------------------- 語彙 (terms.json)
$terms = ['taxonomies' => [
    'ts_genre' => ['terms' => [
        ['slug' => 'sf', 'name' => 'ＳＦ', 'raw_variants' => ['SF', 'ＳＦ'], 'lookup_variants' => ['SF']],
        ['slug' => 'fantasy', 'name' => 'ファンタジー', 'raw_variants' => ['ファンタジィ']]]],
    'ts_corpus' => ['terms' => [['slug' => 'honkan', 'name' => '正規目録'], ['slug' => 'kyukan', 'name' => '旧目録']]],
    'ts_world' => ['terms' => [['slug' => 'delayed', 'name' => 'ディレイド']]],
], 'episode_worlds' => ['e1' => ['delayed']]];
$vocab = new TS_Vocabulary($terms);
check('語彙を持つ taxonomy', [$vocab->has('ts_genre'), $vocab->has('ts_author')], [true, false]);
check('値 → slug (name / 原表記 / 中間形 / slug)', [$vocab->slug('ts_genre', 'ＳＦ'), $vocab->slug('ts_genre', 'SF'), $vocab->slug('ts_genre', 'ファンタジィ'),
      $vocab->slug('ts_genre', 'fantasy'), $vocab->slug('ts_genre', 'ホラー')], ['sf', 'sf', 'fantasy', 'fantasy', null]);
check('slug の集合', $vocab->slug_sets(), ['ts_genre' => ['sf' => 1, 'fantasy' => 1], 'ts_corpus' => ['honkan' => 1, 'kyukan' => 1], 'ts_world' => ['delayed' => 1]]);
check('共有世界', [$vocab->episode_worlds('e1'), $vocab->episode_worlds('e9')], [['delayed'], []]);
check('解決 (語彙に無い値は null で残し、? と空と文字列でないものは飛ばす)',
      $vocab->resolve('ts_genre', ['SF', 'ホラー', '?', '', null, 7, 'ファンタジー']),
      [['value' => 'SF', 'slug' => 'sf'], ['value' => 'ホラー', 'slug' => null], ['value' => 'ファンタジー', 'slug' => 'fantasy']]);
check('語彙を持たない ts_author は値を slug とみなす', $vocab->resolve('ts_author', 'johdan'), [['value' => 'johdan', 'slug' => 'johdan']]);
check('全角の ？ も指定なしの印', $vocab->resolve('ts_genre', ['？', '（？）']), []);

// ---------------------------------------------------------------- works の索引と話の slug
$works = [
    ['work_slug' => 'a-serial', 'author_slug' => 'a', 'episode_count' => 3, 'episodes' => ['e1', ['episode_id' => 'e2'], 'e3']],
    ['work_slug' => 'b-single', 'author_slug' => 'b', 'episode_count' => 1, 'episodes' => ['e4']],
    ['work_slug' => 'c-serial', 'episode_count' => 2, 'episodes' => ['e5', 'e6']],
];
[$ep2work, $single, $author, $order] = TS_Work_Index::index($works);
check('episode → work', $ep2work, ['e1' => 'a-serial', 'e2' => 'a-serial', 'e3' => 'a-serial', 'e4' => 'b-single', 'e5' => 'c-serial', 'e6' => 'c-serial']);
check('単発 work', $single, ['b-single' => true]);
check('work → 作者 (無ければ空)', $author, ['a-serial' => 'a', 'b-single' => 'b', 'c-serial' => '']);
check('話順', $order, ['e1' => 1, 'e2' => 2, 'e3' => 3, 'e4' => 1, 'e5' => 1, 'e6' => 2]);

$sanitize = function ($s) { return strtolower(trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $s), '-')); };
$episodes = [
    ['episode_id' => 'e1', 'source_path' => 'novel/200201/x/Story.htm', 'source_anchor' => null, 'date' => '2002-01-01'],
    ['episode_id' => 'e2', 'source_path' => 'novel/200202/y/story.HTM', 'source_anchor' => null, 'date' => '2002-02-02'],
    ['episode_id' => 'e3', 'source_path' => 'novel/200203/z/other.htm', 'source_anchor' => 'ＢＢＳ', 'date' => '2002-03-03'],
    ['episode_id' => 'e4', 'source_path' => 'novel/200204/single.htm', 'source_anchor' => null, 'date' => '2002-04-04'],
    ['episode_id' => 'e5', 'source_path' => 'novel/200205/w/same.htm', 'source_anchor' => null, 'date' => '2002-05-05'],
    ['episode_id' => 'e6', 'source_path' => 'novel/200205/w/same.htm', 'source_anchor' => null, 'date' => '2002-05-05'],
];
$slugs = TS_Work_Index::child_slugs($episodes, $ep2work, $single, $sanitize);
check('語幹を小文字に、重複は親ディレクトリを前置', [$slugs['e1'], $slugs['e2']], ['x-story', 'y-story']);
check('アンカーは連結してから半角に', $slugs['e3'], 'other-bbs');
check('単発は子投稿を作らない', isset($slugs['e4']), false);
check('同じ場所・同じ日は、親ディレクトリと日付でも分かれないので連番', [$slugs['e5'], $slugs['e6']], ['w-same-20020505', 'w-same-20020505-2']);
$slugs2 = TS_Work_Index::child_slugs([
    ['episode_id' => 'f1', 'source_path' => 'novel/a/p.htm', 'source_anchor' => null, 'date' => '2001-01-01'],
    ['episode_id' => 'f2', 'source_path' => 'novel/a/p.htm', 'source_anchor' => null, 'date' => '2001-02-02'],
], ['f1' => 'w', 'f2' => 'w'], [], $sanitize);
check('同じ場所で日が違えば日付を後置', $slugs2, ['f1' => 'a-p-20010101', 'f2' => 'a-p-20010202']);
check('掃除して空になれば episode_id から', TS_Work_Index::child_slugs([['episode_id' => 'g1', 'source_path' => 'novel/a/あ.htm', 'source_anchor' => null]], ['g1' => 'w'], [], $sanitize), ['g1' => 'g1']);

// ---------------------------------------------------------------- 話のメタ
$ep = ['episode_id' => 'e1', 'source_path' => 'novel/x.htm', 'source_anchor' => null, 'corpus' => 'honkan', 'catalog_ref' => 'lib1.html#1',
       'date_raw' => '2002/1/1', 'size_kb' => 10, 'files_n' => null, 'illustrator' => ['画'], 'illustrator_url' => null,
       'kansou_slug' => 'k', 'kansou_annex_url' => null, 'arasuji' => 'あ', 'comment' => null, 'suisen' => null, 'osusume' => null,
       'nav_links' => [], 'orig_url' => 'http://ts.novels.jp/novel/x.htm', 'annex_url' => 'https://x/novel/x.htm', 'annex_yays_url' => null,
       'provenance' => ['route' => 'wayback'], 'genre_raw' => 'ＳＦ', 'type_raw' => null, 'keywords_raw' => null, 'zokusei' => null];
$meta = TS_Episode_Fields::meta($ep);
check('話のメタのキー', array_keys($meta), ['_ts_episode_id', '_ts_source_path', '_ts_source_anchor', '_ts_corpus', '_ts_catalog_ref',
      '_ts_pub_date_raw', '_ts_size_kb', '_ts_files_n', '_ts_illustrator', '_ts_illustrator_url', '_ts_kansou_slug', '_ts_kansou_annex_url',
      '_ts_arasuji', '_ts_author_comment', '_ts_suisen', '_ts_osusume', '_ts_nav_links', '_ts_orig_url', '_ts_annex_url', '_ts_annex_yays_url',
      '_ts_provenance', '_ts_raw_genre', '_ts_raw_type', '_ts_raw_keywords', '_ts_zokusei']);
check('キーは _ts_ で始まる', array_values(array_filter(array_keys($meta), function ($k) { return strpos($k, '_ts_') !== 0; })), []);
check('書誌カードの被覆を見るメタが含まれる', array_values(array_diff(array_keys(TS_Verify_Expect::META_FIELDS), array_keys($meta))), []);
check('値はそのまま (null は import が消す)', [$meta['_ts_episode_id'], $meta['_ts_pub_date_raw'], $meta['_ts_author_comment'], $meta['_ts_raw_genre']],
      ['e1', '2002/1/1', null, 'ＳＦ']);
// 読む側のキーは書く側 (meta() のキー + WORK_KEYS) に全部含まれる (キーの綴りの取り違えを機械的に見る)
$written = array_merge(array_keys($meta), TS_Episode_Fields::WORK_KEYS);
check('WORK_KEYS と meta() のキーは重ならない', array_values(array_intersect(TS_Episode_Fields::WORK_KEYS, array_keys($meta))), []);
$meta_keys_in = function (array $files) {
    $keys = [];
    foreach ($files as $f) {
        $src = file_get_contents($f);
        if (preg_match_all("/'(_ts_[a-z_]+)'/", $src, $m)) foreach ($m[1] as $k) $keys[$k] = true;
        if (preg_match_all("/\bts_meta\([^;]*?,\s*'([a-z_]+)'\)/", $src, $m)) foreach ($m[1] as $k) $keys["_ts_$k"] = true;
    }
    ksort($keys);
    return array_keys($keys);
};
$read = $meta_keys_in(array_merge(glob("$plugin/includes/render/*.php"), ["$plugin/includes/visibility/visibility-hooks.php"],
                                  glob("$root/themes/ts-bunko/*.php")));
check('読む側のキーを集められている', in_array('_ts_pub_date_raw', $read, true) && in_array('_ts_visibility', $read, true), true);
check('表示が読むメタのキーは書く側に全部ある', array_values(array_diff($read, $written)), []);
$wrote = $meta_keys_in(["$plugin/includes/commands/import.php", "$plugin/includes/commands/command-base.php",
                        "$plugin/includes/commands/takedown.php", "$plugin/includes/takedown/takedown-apply.php",
                        "$plugin/includes/visibility/visibility-rules.php"]);
check('import と取り下げが触るメタのキーは書く側の一覧に全部ある', array_values(array_diff($wrote, $written)), []);
check('本文の無い話の本文', TS_Episode_Fields::PLACEHOLDER_BODY, '<!-- wp:paragraph --><p>(本文は原本アーカイブでお読みください)</p><!-- /wp:paragraph -->');

// ---------------------------------------------------------------- 固定ページの原本 (S109)
$html = "<!-- title: このサイトについて -->\n<!-- owner-authored: 2026-10-30 -->\n<!-- wp:paragraph -->\n<p>本文</p>\n<!-- /wp:paragraph -->\n";
$f = TS_Page_File::parse($html, 'about');
check('題と本文 (所有者文責の注記は本文に入れない)', [$f['title'], $f['body']], ['このサイトについて', "<!-- wp:paragraph -->\n<p>本文</p>\n<!-- /wp:paragraph -->"]);
check('hash は題 + 本文', $f['hash'], md5('このサイトについて' . "<!-- wp:paragraph -->\n<p>本文</p>\n<!-- /wp:paragraph -->"));
check('題の行が無ければ slug', TS_Page_File::parse("<p>x</p>", 'takedown')['title'], 'takedown');
check('書き戻し (注記の行を保つ)', TS_Page_File::render('このサイトについて', "<!-- wp:paragraph -->\n<p>本文</p>\n<!-- /wp:paragraph -->\n", $html), $html);
check('書き戻し (新規は注記なし)', TS_Page_File::render('T', "<p>a</p>\r\n", ''), "<!-- title: T -->\n<p>a</p>\n");
$back = TS_Page_File::parse(TS_Page_File::render($f['title'], $f['body'], $html), 'about');
check('往復で hash が変わらない (export → sync-pages が skipped になる条件)', $back['hash'], $f['hash']);
check('差分の行数 (LCS)', TS_Page_File::diff_count("a\nb\nc\n", "a\nx\nc\nd\n"), [3, 2, 1]);
check('差分の行数 (新規)', TS_Page_File::diff_count('', "a\nb\n"), [2, 2, 0]);
check('差分の行数 (同じ)', TS_Page_File::diff_count("a\n", "a\n"), [0, 0, 0]);

// ---------------------------------------------------------------- verify の期待値
$authors = [['slug' => 'A_Upper'], ['slug' => 'b']];
$pseudo = ['unattributed' => '作者不詳'];
$v_works = [
    ['work_slug' => 'w1', 'author_slug' => 'A_Upper', 'episode_count' => 2, 'episodes' => ['e1', 'e2']],
    ['work_slug' => 'w2', 'author_slug' => 'b', 'episode_count' => 1, 'episodes' => ['e3']],
    ['work_slug' => 'w3', 'author_slug' => 'zz', 'episode_count' => 1, 'episodes' => ['e4']],   // 語彙に無い作者
];
$v_eps = [
    ['episode_id' => 'e1', 'corpus' => 'honkan', 'genre' => ['SF', 'ＳＦ', null, 'ホラー', 7], 'type' => [], 'keywords' => [], 'date_raw' => '2002', 'orig_url' => 'u', 'annex_url' => '', 'provenance' => ['r' => 1]],
    ['episode_id' => 'e2', 'corpus' => 'honkan', 'genre' => ['ファンタジー', null], 'keywords' => [3], 'date_raw' => null, 'orig_url' => null, 'annex_url' => null, 'provenance' => []],
    ['episode_id' => 'e3', 'corpus' => 'kyukan', 'genre' => ['ＳＦ'], 'date_raw' => '2003', 'orig_url' => 'u', 'annex_url' => 'a', 'provenance' => null],
    ['episode_id' => 'e4', 'corpus' => 'unknown', 'genre' => [], 'date_raw' => '', 'orig_url' => '', 'annex_url' => 'a'],
];
$exp = new TS_Verify_Expect($v_works, $v_eps, $authors, $pseudo, $vocab, TS_Content_Model::TAXONOMIES);
check('投稿数 = works + episodes - 単発', $exp->post_count(), ['expect' => 3 + 4 - 2, 'works' => 3, 'episodes' => 4, 'singles' => 2]);
check('語彙の集合 (作者は小文字 + 擬似作者、ほかは terms.json)', $exp->vocab_sets(),
      ['ts_author' => ['a_upper' => 1, 'b' => 1, 'unattributed' => 1], 'ts_genre' => ['sf' => 1, 'fantasy' => 1],
       'ts_corpus' => ['honkan' => 1, 'kyukan' => 1], 'ts_world' => ['delayed' => 1]]);
// 割当: 作者 = works 2 (w3 の zz は語彙に無い) + 連載の子 2 (e1 e2)、corpus = 話 3 (e4 の unknown は無い) + 親 w1 の 1、
// genre = e1 {sf} + e2 {fantasy} + e3 {sf} = 3 + 親 w1 {sf, fantasy} = 5、world = e1 の delayed 1 + 親 w1 の 1
// genre と keywords の null と数値は数えずに進む (T45.1.12。分割前と同じ寛容さ)
check('割当数の期待 (親 work への合併付与を含む)', $exp->assignment_counts(),
      ['ts_author' => 4, 'ts_genre' => 5, 'ts_type' => 0, 'ts_keyword' => 0, 'ts_world' => 2, 'ts_corpus' => 4]);
check('書誌カード用メタの被覆 (null・空文字・空配列は数えない)', $exp->meta_coverage(),
      ['_ts_pub_date_raw' => 2, '_ts_orig_url' => 2, '_ts_annex_url' => 2, '_ts_provenance' => 1]);
