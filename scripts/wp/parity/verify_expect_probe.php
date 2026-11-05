<?php
// 新しい TS_Verify_Expect が本番の catalog から出す期待値 (旧 wp ts verify の「期待=」と比べる。読むだけ)
define('TS_LIBRARY_TEST', true);
$plugin = $argv[1]; $root = $argv[2];
require "$plugin/includes/files.php";
foreach (TS_Library_Files::PURE as $f) require "$plugin/includes/$f";
$episodes = TS_Catalog_Files::read_jsonl("$root/catalog/episodes.jsonl");
$works = TS_Catalog_Files::read_jsonl("$root/catalog/works.jsonl");
$vocab = new TS_Vocabulary(TS_Catalog_Files::read_json("$root/catalog/terms.json"));
$authors = TS_Catalog_Files::read_json("$root/catalog/authors.json"); if (isset($authors['authors'])) $authors = $authors['authors'];
$pseudo = ['unattributed' => '作者不詳', 'series-index' => '（シリーズ目録）'];
$e = new TS_Verify_Expect($works, $episodes, $authors, $pseudo, $vocab, TS_Content_Model::TAXONOMIES);
$p = $e->post_count();
printf("投稿数: 期待=%d (works %d + episodes %d - 単発 %d)\n", $p['expect'], $p['works'], $p['episodes'], $p['singles']);
$sets = $e->vocab_sets();
foreach (TS_Content_Model::TAXONOMIES as $tax) printf("%s: 語彙=%d\n", $tax, count($sets[$tax] ?? []));
foreach ($e->assignment_counts() as $tax => $n) printf("割当 %s: 期待=%d\n", $tax, $n);
foreach ($e->meta_coverage() as $mk => $n) printf("メタ %s: 期待=%d\n", $mk, $n);
// 話の slug の割り当て (sanitize は ASCII 小文字化だけの代替) と索引の件数
[$ep2work, $single, $author, $order] = TS_Work_Index::index($works);
$slugs = TS_Work_Index::child_slugs($episodes, $ep2work, $single, function ($s) { return preg_replace('/[^a-z0-9_-]+/', '-', strtolower($s)); });
printf("child_slugs: %d 話 (単発 %d 作品を除く)、重複 %d\n", count($slugs), count($single), count($slugs) - count(array_unique($slugs)));
