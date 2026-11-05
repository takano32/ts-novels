<?php
/**
 * WordPress の代わり — WordPress に触る側 (TS_Library_Files::WP・CLI、テーマ) を WordPress なしで動かすための最小の関数と
 * 置き場 (T45.1.14、specs/S242.md)。scripts/wp/ts_library_test.php が PURE と CLI を読んだ後にこれを読み、続けて WP の表を読む。
 *
 * 目的と範囲:
 *  - 出力の「構造」(タグ・class・順・文言) を固定するためのもの。エスケープの忠実さ (esc_url の正規化、esc_html の実体の扱い、
 *    sanitize_title の日本語の符号化) は見ない。本番の HTML との一致は T45.3.1 の基準で見る。
 *  - 判断 (T45.1.14): WordPress の関数を callable で注入する設計には変えず、同じ名前の代わりの関数を置く。WP の表のファイルを
 *    本番と同じ形のまま読め、旧版 (0e8c7712) のコードも同じ代わりで動かして期待値を取れるため (T45.1.15 の「実装の写しにしない」)。
 *    注入は読み込み順 (フックは読み込み時に登録する) と、テーマが名前で呼ぶ関数の互換に響く。
 *  - 代わりは scripts/wp/tests/ にだけあり includes/ には無いので、PURE の境目の検査 (scripts/wp/tests/files.php) は変わらない。
 *    PURE にも依存しない (旧版の probe で PURE を読まなくてよいように、URL 基底は $post_type_slugs に持つ)。
 *
 * 置き場 (TS_WP_Stubs の static 配列)。試験の前に reset() で空にし、add_post / set_meta / add_term / set_terms / $options で作る:
 *   TS_WP_Stubs::reset();
 *   $w = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_name' => 'a-foo', 'post_title' => '題', 'post_date' => '2002-01-01 00:00:00']);
 *   $e = TS_WP_Stubs::add_post(['post_type' => 'ts_work', 'post_parent' => $w, 'post_name' => 'ep01', 'menu_order' => 1]);
 *   TS_WP_Stubs::set_meta($e, ['_ts_source_path' => 'novel/200201/x.htm', '_ts_pub_date_raw' => '2002/01/01']);  // 値はそのまま (直列化しない)
 *   $a = TS_WP_Stubs::add_term(['taxonomy' => 'ts_author', 'name' => '山田', 'slug' => 'yamada']);
 *   TS_WP_Stubs::set_terms($w, 'ts_author', [$a]);
 *   TS_WP_Stubs::$options['ts_takedown_urls'] = ['/novel/a-foo/'];
 *   $html = TS_WP_Stubs::capture(function () use ($e) { ts_bunko_biblio_card(get_post($e)); });   // echo する部品は capture で受ける
 * 置き場を作る関数は scripts/wp/tests/wp-fixtures.php に置き、試験と probe (下) で同じものを呼ぶ。
 *
 * フックの記録: add_action / add_filter は呼ばず記録だけする。TS_WP_Stubs::$hooks に登録の順で [type, hook, priority, accepted_args,
 * callable, 登録したファイル] が並び、hook_list() が「ファイル: type hook@priority callable (accepted_args)」の文字列にする
 * (scripts/wp/tests/hooks.php が S242 の表と比べる)。do_action / apply_filters は記録した callable を priority の順 (同じなら登録の順) に
 * 呼ぶ。ショートコードは $shortcodes に入り、shortcode('ts_removed_list') で呼ぶ。init のフック (投稿タイプの登録・cron の予約) は
 * run_init() で流す (post_type_exists は登録した後に true になる)。
 *
 * 書き込みの記録: wp_insert_post / wp_update_post / update_option / delete_option / set_transient / wp_set_object_terms / status_header /
 * nocache_headers / wp_die は置き場を変えるとともに TS_WP_Stubs::$log に [関数, 引数] を残す。wp_mail は $mails に溜める。
 * wp_die は exit の代わりに TS_WP_Die を投げる (catch して title・args['response'] を見る)。
 * $wpdb は SQL を解釈しない: get_results / get_col / get_var が返す行を TS_WP_Stubs::$db_rows に先に積む (1 回の問い合わせに 1 組)。
 * 流した SQL は $db_queries に残る。
 *
 * 旧版のコードを同じ代わりで動かして期待値を取る (T45.1.15 の「実装の写しにしない」):
 *   scripts/wp/php_probe.sh <probe.php> scripts/wp/tests/wp-stubs.php scripts/wp/tests/wp-fixtures.php \
 *       --old 0e8c7712:mu-plugins/ts-library/includes/render.php
 *   probe.php (scratchpad に置いてよい) は、require 'scripts/wp/tests/wp-stubs.php' → require 'scripts/wp/tests/wp-fixtures.php' →
 *   require 'old/0e8c7712/mu-plugins/ts-library/includes/render.php' → 試験と同じ fixture の関数を呼ぶ → capture の結果を var_export で出す。
 *   旧版のファイルは新版と同じ関数名を定義するので、新版の WP の表 (と PURE) は読まない。出た値を試験の期待値に貼り、出典
 *   (リビジョン・日付・probe の名前) をコメントに書く。
 *
 * 本物と違うところ (試験を書くときに頼らない):
 *  - 同じ日付・同じ menu_order の並びは本番の MySQL では決まらない。代わりは ID の昇順で決める
 *  - time() は置き換えられない。wp_date / current_time は TS_WP_Stubs::$timezone (Asia/Tokyo) で変換し、$time を立てれば「いま」をそれにする
 *  - wp_insert_post / wp_update_post が書く post_modified は $now (固定の文字列)。手動編集の検出を試すときは $now を進める
 *  - get_posts / WP_Query が見る引数は post_type / post_status (any) / post_parent / post__in・include / p / name / year / posts_per_page・numberposts /
 *    orderby (date・ID・menu_order・name・title・modified・none) / order / fields=ids / meta_key・meta_value・meta_compare (= != IN LIKE) /
 *    meta_query (key・value・compare、relation) / tax_query (taxonomy・field・terms) だけ。配列のメタは serialize した形で LIKE を見る。
 *    meta_value の比較は字面の一致 (本番の MySQL の照合順序は大文字小文字を区別しない)。no_found_rows・update_post_meta_cache などは読まない
 *  - wp_insert_post / wp_update_post は post_name を重複しないように直さず (本物は wp_unique_post_slug)、guid・post_date_gmt も作らない。
 *    渡した欄をそのまま置く
 *  - get_term_by('slug', …) は本物と同じく値を sanitize_title してから引く (大文字まじりの author_slug が小文字の term に当たる)。
 *    get_terms の fields は ids・names・slugs・id=>slug・id=>name と all だけ
 *  - $wpdb は SQL を解釈しない。wp ts verify の 7 つの問い合わせ (orphan・割当数・メタの被覆 4 つ・本文 status) は、試験が同じ条件で
 *    置き場から数えた行を積む (wp-fixtures.php の ts_fixture_verify_db_rows)。本文 status の同数の並びは値の昇順にする (本番の MySQL は決まらない)
 *  - 権限は current_user_can → $caps (いまのユーザが持つ capability)、user_can → $users[ID]['caps']。条件タグは $is
 *    (admin・home・front_page・singular・404・search・paged・tax (taxonomy 名)・post_type_archive (投稿タイプ名))、get_queried_object は $queried
 *  - term の count は付いている公開投稿の数。投稿を置かずに数だけ要るときは add_term の count
 *  - includes/render/render-lookup.php と index-render.php の static なキャッシュ (原パスの表・作者名の表・作品数) は 1 プロセスに 1 度しか
 *    作られない。それを使う試験は最初に呼ぶ側が置き場を決める
 *
 * 足りない関数は同じ流儀でここに足す (置き場を読む・書く・記録する以外のことはしない)。PHP 8.0 互換。
 */

if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__, 3) . '/');
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); define('ARRAY_A', 'ARRAY_A'); define('ARRAY_N', 'ARRAY_N'); }
define('TS_WP_STUBS', true);

/** wp_die の代わりに投げる (本番は exit)。getMessage() は本文、title は題、args は ['response' => 状態コード, …] */
final class TS_WP_Die extends RuntimeException {
    public $title;
    public $args;
    public function __construct(string $message, string $title = '', array $args = []) {
        parent::__construct($message);
        $this->title = $title;
        $this->args = $args;
    }
}

class WP_Error {
    public $errors = [];
    public $error_data = [];
    public function __construct($code = '', $message = '', $data = '') {
        if ($code === '') return;
        $this->errors[$code][] = $message;
        if ($data !== '') $this->error_data[$code] = $data;
    }
    public function get_error_code() { return array_key_first($this->errors) ?? ''; }
    public function get_error_message($code = '') {
        if ($code === '') $code = $this->get_error_code();
        return $this->errors[$code][0] ?? '';
    }
}

class WP_Post {
    public $ID = 0; public $post_author = 0; public $post_date = '0000-00-00 00:00:00'; public $post_date_gmt = '0000-00-00 00:00:00';
    public $post_content = ''; public $post_title = ''; public $post_excerpt = ''; public $post_status = 'publish';
    public $comment_status = 'closed'; public $ping_status = 'closed'; public $post_password = ''; public $post_name = '';
    public $post_modified = '0000-00-00 00:00:00'; public $post_modified_gmt = '0000-00-00 00:00:00'; public $post_parent = 0;
    public $guid = ''; public $menu_order = 0; public $post_type = 'post'; public $post_mime_type = ''; public $comment_count = 0;
    public $filter = 'raw';
    public function __construct($data = []) { foreach ((array) $data as $k => $v) $this->$k = $v; }
}

class WP_Term {
    public $term_id = 0; public $name = ''; public $slug = ''; public $term_group = 0; public $term_taxonomy_id = 0;
    public $taxonomy = ''; public $description = ''; public $parent = 0; public $count = 0; public $filter = 'raw';
    public function __construct($data = []) { foreach ((array) $data as $k => $v) $this->$k = $v; }
}

/** 問い合わせの代わり。引数を渡せば置き場を引く。pre_get_posts の試験では new WP_Query() に is_main と flags (home・search・tax) を立てて渡す */
class WP_Query {
    public $posts = []; public $post_count = 0; public $found_posts = 0; public $query = []; public $query_vars = [];
    public $is_main = false; public $flags = [];
    public function __construct($query = []) {
        $this->query = $this->query_vars = (array) $query;
        if ($this->query) { $this->posts = TS_WP_Stubs::query($this->query_vars); $this->post_count = $this->found_posts = count($this->posts); }
    }
    public function set($k, $v) { $this->query_vars[$k] = $v; }
    public function get($k, $default = '') { return $this->query_vars[$k] ?? $default; }
    public function is_main_query() { return $this->is_main; }
    public function is_home() { return !empty($this->flags['home']); }
    public function is_search() { return !empty($this->flags['search']); }
    public function is_tax($taxonomy = '', $term = '') {
        $t = $this->flags['tax'] ?? '';
        return $t !== '' && $t !== false && ($taxonomy === '' || $taxonomy === [] || in_array($t, (array) $taxonomy, true));
    }
    public function set_404() { $this->flags['404'] = true; TS_WP_Stubs::$log[] = ['set_404', []]; }
}

/** $wpdb の代わり。行は TS_WP_Stubs::$db_rows から順に取る (SQL は解釈しない) */
class wpdb {
    public $prefix = 'wp_'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $terms = 'wp_terms';
    public $term_taxonomy = 'wp_term_taxonomy'; public $term_relationships = 'wp_term_relationships'; public $termmeta = 'wp_termmeta';
    public $options = 'wp_options'; public $users = 'wp_users'; public $usermeta = 'wp_usermeta';
    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0;
        return preg_replace_callback('/%(?:(\d+)\$)?([sdfF%])/', function ($m) use (&$i, $args) {
            if ($m[2] === '%') return '%';
            $v = $args[$m[1] !== '' ? (int) $m[1] - 1 : $i++] ?? '';
            if ($m[2] === 'd') return (string) (int) $v;
            if ($m[2] === 'f' || $m[2] === 'F') return (string) (float) $v;
            return "'" . addslashes((string) $v) . "'";
        }, (string) $query);
    }
    public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }
    public function get_results($query = null, $output = OBJECT) {
        return array_map(function ($r) { return is_array($r) ? (object) $r : $r; }, $this->rows($query));
    }
    public function get_col($query = null, $x = 0) {
        return array_map(function ($r) use ($x) {
            $r = array_values(is_object($r) ? get_object_vars($r) : (array) $r);
            return $r[$x] ?? null;
        }, $this->rows($query));
    }
    public function get_var($query = null, $x = 0, $y = 0) { $col = $this->get_col($query, $x); return $col[$y] ?? null; }
    public function get_row($query = null, $output = OBJECT, $y = 0) { $rows = $this->get_results($query); return $rows[$y] ?? null; }
    public function query($query) { TS_WP_Stubs::$db_queries[] = (string) $query; return 0; }
    private function rows($query): array {
        TS_WP_Stubs::$db_queries[] = (string) $query;
        $set = array_shift(TS_WP_Stubs::$db_rows);
        return is_array($set) ? $set : [];
    }
}

final class TS_WP_Stubs {
    public static $home = 'https://novels.xwp.jp';
    public static $blogname = '少年少女文庫';
    public static $charset = 'UTF-8';
    public static $timezone = 'Asia/Tokyo';
    /** wp_insert_post / wp_update_post が post_modified に書く時刻 (決定的にするため time() を使わない) */
    public static $now = '2026-11-05 12:00:00';
    /** wp_date / current_time の「いま」(null なら time()) */
    public static $time = null;
    public static $theme_dir = '';

    // ---- 置き場
    public static $posts = [];          // ID => WP_Post
    public static $meta = [];           // 投稿 ID => [key => [値…]]
    public static $terms = [];          // term_id => WP_Term
    public static $object_terms = [];   // 投稿 ID => [taxonomy => [term_id…]]
    public static $term_meta = [];      // term_id => [key => [値…]]
    public static $options = [];
    public static $transients = [];     // key => [値, 寿命]
    public static $users = [];          // ID => ['user_email' => …, 'display_name' => …, 'roles' => […], 'caps' => […]]
    public static $cron = [];           // hook => 次の時刻
    public static $next_post_id = 1;
    public static $next_term_id = 1;
    public static $next_meta_id = 1;

    // ---- 要求の状態
    public static $current_user = 0;
    public static $caps = [];           // いまのユーザが持つ capability
    public static $is = [];             // 条件タグ (admin・home・front_page・singular・404・search・paged・tax・post_type_archive)
    public static $queried = null;      // get_queried_object (WP_Post か WP_Term)
    public static $query_vars = [];
    public static $loop = [];           // have_posts / the_post が回す投稿 (ID か WP_Post)
    public static $loop_i = -1;
    public static $post = null;         // ループの今の投稿 (get_post() の引数なし)

    // ---- 記録
    public static $hooks = [];          // [type, hook, priority, accepted_args, callable, 登録したファイル]
    public static $shortcodes = [];     // tag => callable
    public static $registered = ['post_type' => [], 'taxonomy' => [], 'meta' => [], 'rewrite' => [], 'meta_box' => [], 'theme_support' => []];
    public static $log = [];            // [関数, 引数]
    public static $mails = [];          // ['to', 'subject', 'body', 'headers']
    public static $status = 200;
    public static $db_rows = [];        // $wpdb が返す行の組の列
    public static $db_queries = [];

    /** URL 基底 (TS_Content_Model と同じ値。register_post_type / register_taxonomy が上書きする)。false は URL を持たない。'' は根 */
    public static $post_type_slugs = ['post' => '', 'page' => '', 'ts_work' => 'novel', 'ts_doc' => 'docs', 'ts_dojo' => 'dojo', 'ts_board_post' => 'boards'];
    public static $taxonomy_slugs = ['ts_author' => 'authors', 'ts_genre' => 'genre', 'ts_type' => 'type', 'ts_keyword' => 'keyword', 'ts_world' => 'world', 'ts_corpus' => false];
    public static $hierarchical = ['page' => true, 'ts_work' => true];

    /** 置き場・要求の状態・記録を空にする (読み込み時に記録したフック・ショートコード・登録はそのまま) */
    public static function reset(): void {
        self::$posts = self::$meta = self::$terms = self::$object_terms = self::$term_meta = [];
        self::$options = self::$transients = self::$users = self::$cron = [];
        self::$next_post_id = self::$next_term_id = self::$next_meta_id = 1;
        self::$current_user = 0; self::$caps = []; self::$is = []; self::$queried = null; self::$query_vars = [];
        self::$loop = []; self::$loop_i = -1; self::$post = null;
        self::$log = []; self::$mails = []; self::$status = 200; self::$db_rows = []; self::$db_queries = [];
        self::$time = null;
    }

    /** 投稿を置く。ID を返す。post_date を省くと $now、post_name を省くと題名から (sanitize_title。空なら ID) */
    public static function add_post(array $a): int {
        $id = isset($a['ID']) ? (int) $a['ID'] : self::$next_post_id;
        self::$next_post_id = max(self::$next_post_id, $id + 1);
        $date = $a['post_date'] ?? self::$now;
        $p = new WP_Post(array_merge([
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => '', 'post_content' => '', 'post_name' => '',
            'post_date' => $date, 'post_date_gmt' => $date, 'post_modified' => $date, 'post_modified_gmt' => $date,
            'post_parent' => 0, 'menu_order' => 0,
        ], $a, ['ID' => $id]));
        if ($p->post_name === '') $p->post_name = sanitize_title($p->post_title) ?: (string) $id;
        self::$posts[$id] = $p;
        return $id;
    }

    /** post meta を置く (キー => 値。値は直列化せずそのまま。同じキーを複数持たせるときは add_post_meta) */
    public static function set_meta(int $post_id, array $kv): void {
        foreach ($kv as $k => $v) self::$meta[$post_id][$k] = [$v];
    }

    /** term を置く。term_id を返す。slug を省くと name から */
    public static function add_term(array $a): int {
        $id = isset($a['term_id']) ? (int) $a['term_id'] : self::$next_term_id;
        self::$next_term_id = max(self::$next_term_id, $id + 1);
        $t = new WP_Term(array_merge(['taxonomy' => '', 'name' => '', 'slug' => '', 'description' => '', 'count' => 0], $a,
                                     ['term_id' => $id, 'term_taxonomy_id' => $id]));
        if ($t->slug === '') $t->slug = sanitize_title($t->name) ?: (string) $id;
        self::$terms[$id] = $t;
        return $id;
    }

    public static function set_terms(int $post_id, string $taxonomy, array $term_ids): void {
        self::$object_terms[$post_id][$taxonomy] = array_values(array_map('intval', $term_ids));
    }

    public static function set_term_meta(int $term_id, array $kv): void {
        foreach ($kv as $k => $v) self::$term_meta[$term_id][$k] = [$v];
    }

    /** echo する部品の出力を文字列で受ける */
    public static function capture(callable $fn): string {
        ob_start();
        try { $fn(); } finally { $out = ob_get_clean(); }
        return $out;
    }

    /** フックの記録を「ファイル: type hook@priority callable (accepted_args)」に (accepted_args は 1 以外のときだけ) */
    public static function hook_list(): array {
        $out = [];
        foreach (self::$hooks as [$type, $hook, $prio, $nargs, $cb, $file]) {
            $out[] = "$file: $type $hook@$prio " . self::callable_name($cb) . ($nargs !== 1 ? " ($nargs)" : '');
        }
        return $out;
    }

    public static function callable_name($cb): string {
        if ($cb instanceof Closure) return '{closure}';
        if (is_string($cb)) return $cb;
        if (is_array($cb)) return (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . $cb[1];
        return '?';
    }

    /** $hook に登録した callable を priority の順 (同じなら登録の順) に */
    public static function callbacks(string $hook): array {
        $list = array_values(array_filter(self::$hooks, function ($h) use ($hook) { return $h[1] === $hook; }));
        usort($list, function ($a, $b) { return $a[2] <=> $b[2]; });   // PHP 8 の sort は安定
        return $list;
    }

    public static function same_callable($a, $b): bool {
        return (is_object($a) || is_object($b)) ? $a === $b : $a == $b;
    }

    public static function shortcode(string $tag, array $atts = [], ?string $content = null): string {
        if (!isset(self::$shortcodes[$tag])) throw new RuntimeException("ショートコードが登録されていない: $tag");
        return (string) call_user_func(self::$shortcodes[$tag], $atts, $content, $tag);
    }

    /** init のフックを流す (投稿タイプ・taxonomy・meta の登録、rewrite、cron の予約) */
    public static function run_init(): void { do_action('init'); }

    public static function tz(): DateTimeZone { return new DateTimeZone(self::$timezone); }

    public static function now_ts(): int { return self::$time ?? time(); }

    /** 階層のある投稿の URL のパス (親の post_name / … / 自分の post_name) */
    public static function path_of(WP_Post $p): string {
        $parts = [$p->post_name];
        $cur = $p; $seen = [];
        while ($cur->post_parent && isset(self::$posts[$cur->post_parent]) && !isset($seen[$cur->post_parent])) {
            $seen[$cur->post_parent] = true;
            $cur = self::$posts[$cur->post_parent];
            array_unshift($parts, $cur->post_name);
        }
        return implode('/', $parts);
    }

    /** term に付いている公開投稿の数 (1 つも付いていなければ add_term の count) */
    public static function term_count(int $term_id): int {
        $n = 0;
        foreach (self::$object_terms as $pid => $taxes) {
            if (!isset(self::$posts[$pid]) || self::$posts[$pid]->post_status !== 'publish') continue;
            foreach ($taxes as $ids) if (in_array($term_id, $ids, true)) { $n++; break; }
        }
        return $n ?: (int) (self::$terms[$term_id]->count ?? 0);
    }

    /** 比較に使うメタの値 (配列は WordPress が直列化して持つので、LIKE はその形で見る) */
    private static function meta_str($v): string { return (is_array($v) || is_object($v)) ? serialize($v) : (string) $v; }

    private static function meta_compare(array $vals, $want, string $cmp): bool {
        if ($cmp === 'EXISTS') return (bool) $vals;
        if ($cmp === 'NOT EXISTS') return !$vals;
        foreach ($vals as $v) {
            $s = self::meta_str($v);
            if ($cmp === 'IN') { if (in_array($s, array_map('strval', (array) $want), true)) return true; }
            elseif ($cmp === 'NOT IN') { if (!in_array($s, array_map('strval', (array) $want), true)) return true; }
            elseif ($cmp === '!=') { if ($s !== (string) $want) return true; }
            elseif ($cmp === 'LIKE') { if (strpos($s, (string) $want) !== false) return true; }
            elseif ($cmp === 'NOT LIKE') { if (strpos($s, (string) $want) === false) return true; }
            else { if ($s === (string) $want) return true; }
        }
        return false;
    }

    private static function meta_query_match(int $pid, array $mq): bool {
        $relation = strtoupper($mq['relation'] ?? 'AND');
        $results = [];
        foreach ($mq as $k => $clause) {
            if ($k === 'relation' || !is_array($clause)) continue;
            if (!isset($clause['key']) && !isset($clause['value'])) { $results[] = self::meta_query_match($pid, $clause); continue; }
            $vals = self::$meta[$pid][$clause['key'] ?? ''] ?? [];
            $cmp = strtoupper($clause['compare'] ?? (isset($clause['value']) ? '=' : 'EXISTS'));
            $results[] = self::meta_compare($vals, $clause['value'] ?? '', $cmp);
        }
        if (!$results) return true;
        return $relation === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    private static function tax_query_match(int $pid, array $tq): bool {
        $relation = strtoupper($tq['relation'] ?? 'AND');
        $results = [];
        foreach ($tq as $k => $clause) {
            if ($k === 'relation' || !is_array($clause) || !isset($clause['taxonomy'])) continue;
            $have = self::$object_terms[$pid][$clause['taxonomy']] ?? [];
            $field = $clause['field'] ?? 'term_id';
            $ids = [];
            foreach ((array) ($clause['terms'] ?? []) as $t) {
                if ($field === 'term_id') { $ids[] = (int) $t; continue; }
                $term = get_term_by($field === 'id' ? 'id' : $field, $t, $clause['taxonomy']);
                if ($term) $ids[] = $term->term_id;
            }
            $hit = (bool) array_intersect($have, $ids);
            $results[] = strtoupper($clause['operator'] ?? 'IN') === 'NOT IN' ? !$hit : $hit;
        }
        if (!$results) return true;
        return $relation === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    /** get_posts / WP_Query の代わり。見る引数は先頭のコメントのとおり */
    public static function query(array $q): array {
        $types = $q['post_type'] ?? 'post';
        $statuses = $q['post_status'] ?? 'publish';
        $limit = (int) ($q['posts_per_page'] ?? ($q['numberposts'] ?? 5));
        $out = [];
        foreach (self::$posts as $p) {
            if ($types !== 'any' && !in_array($p->post_type, (array) $types, true)) continue;
            if ($statuses === 'any') { if (in_array($p->post_status, ['trash', 'auto-draft'], true)) continue; }
            elseif (!in_array($p->post_status, (array) $statuses, true)) continue;
            if (isset($q['post_parent']) && $q['post_parent'] !== '' && (int) $p->post_parent !== (int) $q['post_parent']) continue;
            if (!empty($q['post__in']) && !in_array($p->ID, array_map('intval', (array) $q['post__in']), true)) continue;
            if (!empty($q['post__not_in']) && in_array($p->ID, array_map('intval', (array) $q['post__not_in']), true)) continue;
            if (!empty($q['p']) && (int) $q['p'] !== $p->ID) continue;
            if (!empty($q['name']) && $p->post_name !== $q['name']) continue;
            if (!empty($q['year']) && (int) substr($p->post_date, 0, 4) !== (int) $q['year']) continue;
            if (isset($q['meta_key']) && $q['meta_key'] !== '') {
                $vals = self::$meta[$p->ID][$q['meta_key']] ?? [];
                if (array_key_exists('meta_value', $q)) {
                    if (!self::meta_compare($vals, $q['meta_value'], strtoupper($q['meta_compare'] ?? '='))) continue;
                } elseif (!$vals) continue;
            }
            if (!empty($q['meta_query']) && !self::meta_query_match($p->ID, (array) $q['meta_query'])) continue;
            if (!empty($q['tax_query']) && !self::tax_query_match($p->ID, (array) $q['tax_query'])) continue;
            $out[] = $p;
        }
        $orderby = $q['orderby'] ?? 'date';
        $order = strtoupper((string) ($q['order'] ?? 'DESC')) === 'ASC' ? 1 : -1;
        $key = function (WP_Post $p) use ($orderby) {
            switch ($orderby) {
                case 'ID': return $p->ID;
                case 'menu_order': return $p->menu_order;
                case 'name': return $p->post_name;
                case 'title': return $p->post_title;
                case 'modified': return $p->post_modified;
                case 'none': return 0;
                default: return $p->post_date;
            }
        };
        usort($out, function ($a, $b) use ($key, $order) {
            $c = $key($a) <=> $key($b);
            return $c !== 0 ? $c * $order : $a->ID <=> $b->ID;   // 同じ値の並びは本番では決まらない。ID の昇順にする
        });
        if ($limit > 0) $out = array_slice($out, 0, $limit);
        if (($q['fields'] ?? '') === 'ids') return array_map(function ($p) { return (int) $p->ID; }, $out);
        return array_map(function ($p) { return clone $p; }, $out);
    }

    /** meta の読み書き (post meta と term meta で共通。WordPress と同じく書くときに slash を外す) */
    public static function meta_get(array &$store, int $id, $key, bool $single) {
        if ($key === '') return $store[$id] ?? [];
        $vals = $store[$id][$key] ?? [];
        return $single ? ($vals[0] ?? '') : $vals;
    }
    public static function meta_update(array &$store, int $id, $key, $value) {
        $value = wp_unslash($value);
        if (empty($store[$id][$key])) { $store[$id][$key] = [$value]; return self::$next_meta_id++; }
        if ($store[$id][$key][0] === $value) return false;
        $store[$id][$key][0] = $value;
        return true;
    }
    public static function meta_add(array &$store, int $id, $key, $value, bool $unique) {
        if ($unique && !empty($store[$id][$key])) return false;
        $store[$id][$key][] = wp_unslash($value);
        return self::$next_meta_id++;
    }
    public static function meta_delete(array &$store, int $id, $key, $value) {
        if (empty($store[$id][$key])) return false;
        if ($value === '') { unset($store[$id][$key]); return true; }
        $value = wp_unslash($value);
        $kept = array_values(array_filter($store[$id][$key], function ($v) use ($value) { return $v !== $value; }));
        $changed = count($kept) < count($store[$id][$key]);
        if ($kept) $store[$id][$key] = $kept; else unset($store[$id][$key]);
        return $changed;
    }
}

TS_WP_Stubs::$theme_dir = dirname(__DIR__, 3) . '/themes/ts-bunko';

// ====================================================================== フックとショートコード (記録)

function add_filter($hook, $cb, $priority = 10, $accepted_args = 1) {
    TS_WP_Stubs::$hooks[] = ['filter', $hook, (int) $priority, (int) $accepted_args, $cb, basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '')];
    return true;
}
function add_action($hook, $cb, $priority = 10, $accepted_args = 1) {
    TS_WP_Stubs::$hooks[] = ['action', $hook, (int) $priority, (int) $accepted_args, $cb, basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '')];
    return true;
}
function remove_filter($hook, $cb, $priority = 10) {
    $before = count(TS_WP_Stubs::$hooks);
    TS_WP_Stubs::$hooks = array_values(array_filter(TS_WP_Stubs::$hooks, function ($h) use ($hook, $cb, $priority) {
        return !($h[1] === $hook && $h[2] === (int) $priority && TS_WP_Stubs::same_callable($h[4], $cb));
    }));
    return count(TS_WP_Stubs::$hooks) < $before;
}
function remove_action($hook, $cb, $priority = 10) { return remove_filter($hook, $cb, $priority); }
function has_filter($hook, $cb = false) {
    foreach (TS_WP_Stubs::$hooks as $h) {
        if ($h[1] === $hook && ($cb === false || TS_WP_Stubs::same_callable($h[4], $cb))) return $cb === false ? true : $h[2];
    }
    return false;
}
function has_action($hook, $cb = false) { return has_filter($hook, $cb); }
function apply_filters($hook, $value, ...$args) {
    foreach (TS_WP_Stubs::callbacks($hook) as $h) {
        $value = call_user_func_array($h[4], array_slice(array_merge([$value], $args), 0, max(1, $h[3])));
    }
    return $value;
}
function do_action($hook, ...$args) {
    foreach (TS_WP_Stubs::callbacks($hook) as $h) call_user_func_array($h[4], array_slice($args, 0, $h[3]));
}
function add_shortcode($tag, $cb) { TS_WP_Stubs::$shortcodes[$tag] = $cb; }
function remove_shortcode($tag) { unset(TS_WP_Stubs::$shortcodes[$tag]); }
function shortcode_exists($tag) { return isset(TS_WP_Stubs::$shortcodes[$tag]); }
function has_shortcode($content, $tag) { return strpos((string) $content, '[' . $tag) !== false; }
function do_shortcode($content, $ignore_html = false) {
    return preg_replace_callback('/\[([a-z_]+)\]/', function ($m) {
        return isset(TS_WP_Stubs::$shortcodes[$m[1]]) ? (string) call_user_func(TS_WP_Stubs::$shortcodes[$m[1]], [], null, $m[1]) : $m[0];
    }, (string) $content);
}
function __return_false() { return false; }
function __return_true() { return true; }
function __return_null() { return null; }
function __return_empty_array() { return []; }
function __return_empty_string() { return ''; }

// ====================================================================== 登録 (記録)

function register_post_type($post_type, $args = []) {
    TS_WP_Stubs::$registered['post_type'][$post_type] = $args;
    $rw = $args['rewrite'] ?? true;
    $no_url = $rw === false || (empty($args['public']) && empty($args['publicly_queryable']));
    TS_WP_Stubs::$post_type_slugs[$post_type] = $no_url ? false : (is_array($rw) ? ($rw['slug'] ?? $post_type) : $post_type);
    if (!empty($args['hierarchical'])) TS_WP_Stubs::$hierarchical[$post_type] = true;
    return (object) ['name' => $post_type];
}
function register_taxonomy($taxonomy, $object_type, $args = []) {
    TS_WP_Stubs::$registered['taxonomy'][$taxonomy] = ['object_types' => (array) $object_type, 'args' => $args];
    $rw = $args['rewrite'] ?? true;
    $no_url = $rw === false || (isset($args['public']) && !$args['public']);
    TS_WP_Stubs::$taxonomy_slugs[$taxonomy] = $no_url ? false : (is_array($rw) ? ($rw['slug'] ?? $taxonomy) : $taxonomy);
    return (object) ['name' => $taxonomy];
}
function register_post_meta($post_type, $meta_key, $args = []) { TS_WP_Stubs::$registered['meta'][$post_type][$meta_key] = $args; return true; }
function post_type_exists($post_type) { return isset(TS_WP_Stubs::$registered['post_type'][$post_type]) || in_array($post_type, ['post', 'page'], true); }
function taxonomy_exists($taxonomy) { return isset(TS_WP_Stubs::$registered['taxonomy'][$taxonomy]); }
function add_rewrite_rule($regex, $query, $after = 'bottom') { TS_WP_Stubs::$registered['rewrite'][] = [$regex, $query, $after]; }
function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null) {
    TS_WP_Stubs::$registered['meta_box'][$id] = [$title, $callback, $screen, $context, $priority];
}
function add_theme_support(...$args) { TS_WP_Stubs::$registered['theme_support'][] = $args; }
function wp_enqueue_style(...$args) { TS_WP_Stubs::$log[] = ['wp_enqueue_style', $args]; }
function wp_enqueue_script(...$args) { TS_WP_Stubs::$log[] = ['wp_enqueue_script', $args]; }
function wp_next_scheduled($hook, $args = []) { return TS_WP_Stubs::$cron[$hook] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false) {
    TS_WP_Stubs::$cron[$hook] = (int) $timestamp;
    TS_WP_Stubs::$log[] = ['wp_schedule_event', [$hook, $recurrence]];
    return true;
}

// ====================================================================== 投稿

function get_post($post = null, $output = OBJECT, $filter = 'raw') {
    if ($post instanceof WP_Post) return $post;
    if ($post === null || $post === 0 || $post === '') return TS_WP_Stubs::$post;
    $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
    return isset(TS_WP_Stubs::$posts[$id]) ? clone TS_WP_Stubs::$posts[$id] : null;
}
function get_posts($args = []) {
    $a = (array) $args;
    if (!empty($a['numberposts']) && empty($a['posts_per_page'])) $a['posts_per_page'] = $a['numberposts'];
    if (!empty($a['include'])) $a['post__in'] = (array) $a['include'];
    return TS_WP_Stubs::query(array_merge(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 5], $a));
}
function get_children($args = [], $output = OBJECT) {
    $a = array_merge(['post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1], (array) $args);
    if (empty($a['post_parent'])) return [];
    $kids = get_posts($a);
    if (!empty($a['fields'])) return $kids;
    $out = [];
    foreach ($kids as $k) $out[$k->ID] = $k;
    return $out;
}
function get_post_field($field, $post = null, $context = 'display') { $p = get_post($post); return ($p && isset($p->$field)) ? $p->$field : ''; }
function get_post_status($post = null) { $p = get_post($post); return $p ? $p->post_status : false; }
function get_post_type($post = null) { $p = get_post($post); return $p ? $p->post_type : false; }
function get_the_ID() { return TS_WP_Stubs::$post ? TS_WP_Stubs::$post->ID : false; }
function get_the_title($post = 0) {
    $p = get_post($post);
    if (!$p) return '';
    $title = (string) $p->post_title;
    if (!is_admin() && $p->post_status === 'private') $title = sprintf(apply_filters('private_title_format', '非公開: %s', $p), $title);
    return apply_filters('the_title', $title, $p->ID);
}
function wp_get_post_parent_id($post = null) { $p = get_post($post); return $p ? (int) $p->post_parent : false; }
function get_post_time($format = 'U', $gmt = false, $post = null, $translate = false) {
    $p = get_post($post);
    if (!$p) return false;
    $dt = new DateTimeImmutable($gmt ? $p->post_date_gmt : $p->post_date, $gmt ? new DateTimeZone('UTC') : TS_WP_Stubs::tz());
    return $format === 'U' ? $dt->getTimestamp() : $dt->format($format);
}
function get_permalink($post = 0, $leavename = false) {
    $p = get_post($post);
    if (!$p) return false;
    $type = $p->post_type;
    if ($type === 'post') return home_url('?p=' . $p->ID);
    $base = TS_WP_Stubs::$post_type_slugs[$type] ?? false;
    if ($base === false) return home_url('?post_type=' . $type . '&p=' . $p->ID);
    if (in_array($p->post_status, ['draft', 'pending', 'auto-draft', 'future'], true)) {
        return home_url($type === 'page' ? '?page_id=' . $p->ID : '?post_type=' . $type . '&p=' . $p->ID);
    }
    $path = !empty(TS_WP_Stubs::$hierarchical[$type]) ? TS_WP_Stubs::path_of($p) : $p->post_name;
    return home_url(($base !== '' ? "/$base" : '') . '/' . $path . '/');
}
function get_page_by_path($page_path, $output = OBJECT, $post_type = 'page') {
    $norm = function ($s) { return strtolower(rawurlencode(rawurldecode($s))); };
    $want = array_map($norm, array_values(array_filter(explode('/', trim((string) $page_path, '/')), 'strlen')));
    if (!$want) return null;
    foreach (TS_WP_Stubs::$posts as $p) {
        if (!in_array($p->post_type, (array) $post_type, true)) continue;
        if (array_map($norm, explode('/', TS_WP_Stubs::path_of($p))) === $want) return clone $p;
    }
    return null;
}
function url_to_postid($url) {
    $norm = function ($u) { return rtrim(preg_replace('|^https?://[^/]+|i', '', (string) $u), '/'); };
    $want = $norm($url);
    if ($want === '') return 0;
    foreach (TS_WP_Stubs::$posts as $p) if ($norm(get_permalink($p)) === $want) return $p->ID;
    return 0;
}
function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true) {
    $a = wp_unslash((array) $postarr);
    if (!empty($a['ID'])) return wp_update_post($a, $wp_error);
    TS_WP_Stubs::$log[] = ['wp_insert_post', $a];
    $data = array_merge(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => '', 'post_content' => '', 'post_parent' => 0,
                         'menu_order' => 0, 'post_date' => TS_WP_Stubs::$now], $a);
    $data = apply_filters('wp_insert_post_data', $data, $a, $postarr, false);
    $id = TS_WP_Stubs::add_post(array_merge($data, ['ID' => TS_WP_Stubs::$next_post_id]));
    TS_WP_Stubs::$posts[$id]->post_modified = TS_WP_Stubs::$posts[$id]->post_modified_gmt = TS_WP_Stubs::$now;
    do_action('save_post_' . TS_WP_Stubs::$posts[$id]->post_type, $id, TS_WP_Stubs::$posts[$id], false);
    do_action('save_post', $id, TS_WP_Stubs::$posts[$id], false);
    return $id;
}
function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true) {
    $a = wp_unslash($postarr instanceof WP_Post ? get_object_vars($postarr) : (array) $postarr);
    TS_WP_Stubs::$log[] = ['wp_update_post', $a];
    $id = (int) ($a['ID'] ?? 0);
    if (!isset(TS_WP_Stubs::$posts[$id])) return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    $p = TS_WP_Stubs::$posts[$id];
    $data = apply_filters('wp_insert_post_data', array_merge(get_object_vars($p), $a), $a, $postarr, true);
    foreach ($data as $k => $v) if (property_exists($p, $k)) $p->$k = $v;
    $p->post_modified = $p->post_modified_gmt = TS_WP_Stubs::$now;
    do_action('save_post_' . $p->post_type, $id, $p, true);
    do_action('save_post', $id, $p, true);
    return $id;
}
function wp_delete_post($postid = 0, $force_delete = false) {
    $p = get_post($postid);
    if (!$p) return false;
    unset(TS_WP_Stubs::$posts[$p->ID], TS_WP_Stubs::$meta[$p->ID], TS_WP_Stubs::$object_terms[$p->ID]);
    TS_WP_Stubs::$log[] = ['wp_delete_post', [$p->ID, $force_delete]];
    return $p;
}
function clean_post_cache($post) {}
function _prime_post_caches($ids, $update_term_cache = true, $update_meta_cache = true) {}
function wp_is_post_revision($post) { return false; }
function wp_count_posts($type = 'post', $perm = '') {
    $c = ['publish' => 0, 'future' => 0, 'draft' => 0, 'pending' => 0, 'private' => 0, 'trash' => 0, 'auto-draft' => 0, 'inherit' => 0];
    foreach (TS_WP_Stubs::$posts as $p) if ($p->post_type === $type) $c[$p->post_status] = ($c[$p->post_status] ?? 0) + 1;
    return (object) $c;
}

// ====================================================================== meta

function get_post_meta($post_id, $key = '', $single = false) { return TS_WP_Stubs::meta_get(TS_WP_Stubs::$meta, (int) $post_id, $key, (bool) $single); }
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') { return TS_WP_Stubs::meta_update(TS_WP_Stubs::$meta, (int) $post_id, $meta_key, $meta_value); }
function add_post_meta($post_id, $meta_key, $meta_value, $unique = false) { return TS_WP_Stubs::meta_add(TS_WP_Stubs::$meta, (int) $post_id, $meta_key, $meta_value, (bool) $unique); }
function delete_post_meta($post_id, $meta_key, $meta_value = '') { return TS_WP_Stubs::meta_delete(TS_WP_Stubs::$meta, (int) $post_id, $meta_key, $meta_value); }
function get_term_meta($term_id, $key = '', $single = false) { return TS_WP_Stubs::meta_get(TS_WP_Stubs::$term_meta, (int) $term_id, $key, (bool) $single); }
function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') { return TS_WP_Stubs::meta_update(TS_WP_Stubs::$term_meta, (int) $term_id, $meta_key, $meta_value); }
function add_term_meta($term_id, $meta_key, $meta_value, $unique = false) { return TS_WP_Stubs::meta_add(TS_WP_Stubs::$term_meta, (int) $term_id, $meta_key, $meta_value, (bool) $unique); }
function delete_term_meta($term_id, $meta_key, $meta_value = '') { return TS_WP_Stubs::meta_delete(TS_WP_Stubs::$term_meta, (int) $term_id, $meta_key, $meta_value); }

// ====================================================================== term

function get_terms($args = [], $deprecated = '') {
    $a = (array) $args;
    $taxes = (array) ($a['taxonomy'] ?? []);
    $hide_empty = $a['hide_empty'] ?? true;
    $out = [];
    foreach (TS_WP_Stubs::$terms as $t) {
        if ($taxes && !in_array($t->taxonomy, $taxes, true)) continue;
        $c = TS_WP_Stubs::term_count($t->term_id);
        if ($hide_empty && $c === 0) continue;
        if (!empty($a['slug']) && !in_array($t->slug, (array) $a['slug'], true)) continue;
        if (!empty($a['include']) && !in_array($t->term_id, array_map('intval', (array) $a['include']), true)) continue;
        if (!empty($a['exclude']) && in_array($t->term_id, array_map('intval', (array) $a['exclude']), true)) continue;
        $tt = clone $t; $tt->count = $c; $out[] = $tt;
    }
    $orderby = $a['orderby'] ?? 'name';
    $order = strtoupper((string) ($a['order'] ?? 'ASC')) === 'DESC' ? -1 : 1;
    $key = function (WP_Term $t) use ($orderby) {
        switch ($orderby) {
            case 'count': return $t->count;
            case 'slug': return $t->slug;
            case 'id': case 'term_id': return $t->term_id;
            case 'none': return 0;
            default: return $t->name;
        }
    };
    usort($out, function ($x, $y) use ($key, $order) {
        $c = $key($x) <=> $key($y);
        return $c !== 0 ? $c * $order : $x->term_id <=> $y->term_id;
    });
    if (!empty($a['number'])) $out = array_slice($out, (int) ($a['offset'] ?? 0), (int) $a['number']);
    $fields = $a['fields'] ?? 'all';
    if ($fields === 'ids') return array_map(function ($t) { return $t->term_id; }, $out);
    if ($fields === 'names') return array_map(function ($t) { return $t->name; }, $out);
    if ($fields === 'slugs') return array_map(function ($t) { return $t->slug; }, $out);
    if ($fields === 'id=>slug' || $fields === 'id=>name') {   // term_id => slug / name (wp ts verify の語彙整合)
        $kv = [];
        foreach ($out as $t) $kv[$t->term_id] = $fields === 'id=>slug' ? $t->slug : $t->name;
        return $kv;
    }
    return $out;
}
function get_the_terms($post, $taxonomy) {
    $p = get_post($post);
    if (!$p) return false;
    $out = [];
    foreach (TS_WP_Stubs::$object_terms[$p->ID][$taxonomy] ?? [] as $id) {
        if (!isset(TS_WP_Stubs::$terms[$id])) continue;
        $t = clone TS_WP_Stubs::$terms[$id]; $t->count = TS_WP_Stubs::term_count($id); $out[] = $t;
    }
    if (!$out) return false;
    usort($out, function ($a, $b) { return strcmp($a->name, $b->name) ?: $a->term_id <=> $b->term_id; });   // wp_get_object_terms の既定は name の順
    return $out;
}
function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw') {
    if ($term instanceof WP_Term) return $term;
    return isset(TS_WP_Stubs::$terms[(int) $term]) ? clone TS_WP_Stubs::$terms[(int) $term] : null;
}
function get_term_by($field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw') {
    $f = $field === 'id' ? 'term_id' : $field;
    if ($f === 'slug') {   // 本物と同じく slug は sanitize_title してから引く (大文字まじりの author_slug を小文字の term に当てる。wp ts import)
        $value = sanitize_title((string) $value);
        if ($value === '') return false;
    }
    foreach (TS_WP_Stubs::$terms as $t) {
        if ($taxonomy !== '' && $t->taxonomy !== $taxonomy) continue;
        if (isset($t->$f) && (string) $t->$f === (string) $value) return clone $t;
    }
    return false;
}
function get_term_link($term, $taxonomy = '') {
    if ($term instanceof WP_Term) $t = $term;
    elseif (is_int($term) || ctype_digit((string) $term)) $t = get_term((int) $term);
    else $t = get_term_by('slug', (string) $term, $taxonomy);
    if (!$t) return new WP_Error('invalid_term', 'Empty Term.');
    $base = TS_WP_Stubs::$taxonomy_slugs[$t->taxonomy] ?? $t->taxonomy;
    return $base === false ? home_url('?' . $t->taxonomy . '=' . $t->slug) : home_url("/$base/{$t->slug}/");
}
function has_term($term = '', $taxonomy = '', $post = null) {
    $p = get_post($post);
    if (!$p) return false;
    $ids = TS_WP_Stubs::$object_terms[$p->ID][$taxonomy] ?? [];
    if ($term === '' || $term === null || $term === []) return (bool) $ids;
    foreach ((array) $term as $x) {
        $t = is_int($x) ? get_term($x) : (get_term_by('slug', $x, $taxonomy) ?: get_term_by('name', $x, $taxonomy));
        if ($t && in_array($t->term_id, $ids, true)) return true;
    }
    return false;
}
function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
    $ids = [];
    foreach ((array) $terms as $t) {
        if (is_int($t) || ctype_digit((string) $t)) { if (isset(TS_WP_Stubs::$terms[(int) $t])) $ids[] = (int) $t; continue; }
        if ($t === '' || $t === null) continue;
        $found = get_term_by('slug', $t, $taxonomy) ?: get_term_by('name', $t, $taxonomy);
        $ids[] = $found ? $found->term_id : TS_WP_Stubs::add_term(['taxonomy' => $taxonomy, 'name' => (string) $t]);
    }
    $ids = array_values(array_unique($ids));
    if ($append) $ids = array_values(array_unique(array_merge(TS_WP_Stubs::$object_terms[(int) $object_id][$taxonomy] ?? [], $ids)));
    TS_WP_Stubs::$object_terms[(int) $object_id][$taxonomy] = $ids;
    TS_WP_Stubs::$log[] = ['wp_set_object_terms', [(int) $object_id, $taxonomy, $ids]];
    return $ids;
}
function wp_insert_term($term, $taxonomy, $args = []) {
    $slug = $args['slug'] ?? sanitize_title($term);
    if (get_term_by('slug', $slug, $taxonomy)) return new WP_Error('term_exists', 'A term with the name provided already exists in this taxonomy.');
    $id = TS_WP_Stubs::add_term(['taxonomy' => $taxonomy, 'name' => $term, 'slug' => $slug, 'description' => $args['description'] ?? '',
                                 'parent' => (int) ($args['parent'] ?? 0)]);
    TS_WP_Stubs::$log[] = ['wp_insert_term', [$term, $taxonomy, $slug]];
    return ['term_id' => $id, 'term_taxonomy_id' => $id];
}
function wp_update_term($term_id, $taxonomy, $args = []) {
    $t = TS_WP_Stubs::$terms[(int) $term_id] ?? null;
    if (!$t) return new WP_Error('invalid_term', 'Empty Term.');
    foreach ((array) $args as $k => $v) if (property_exists($t, $k)) $t->$k = $v;
    TS_WP_Stubs::$log[] = ['wp_update_term', [(int) $term_id, $taxonomy, (array) $args]];
    return ['term_id' => $t->term_id, 'term_taxonomy_id' => $t->term_id];
}
function wp_delete_term($term_id, $taxonomy, $args = []) {
    if (!isset(TS_WP_Stubs::$terms[(int) $term_id])) return false;
    unset(TS_WP_Stubs::$terms[(int) $term_id], TS_WP_Stubs::$term_meta[(int) $term_id]);
    foreach (TS_WP_Stubs::$object_terms as $pid => $taxes) {
        if (isset($taxes[$taxonomy])) TS_WP_Stubs::$object_terms[$pid][$taxonomy] = array_values(array_diff($taxes[$taxonomy], [(int) $term_id]));
    }
    TS_WP_Stubs::$log[] = ['wp_delete_term', [(int) $term_id, $taxonomy]];
    return true;
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

// ====================================================================== option・transient・ユーザ・メール

function get_option($option, $default = false) { return array_key_exists($option, TS_WP_Stubs::$options) ? TS_WP_Stubs::$options[$option] : $default; }
function update_option($option, $value, $autoload = null) {
    TS_WP_Stubs::$log[] = ['update_option', [$option, $value]];
    $changed = !array_key_exists($option, TS_WP_Stubs::$options) || TS_WP_Stubs::$options[$option] !== $value;
    TS_WP_Stubs::$options[$option] = $value;
    return $changed;
}
function add_option($option, $value = '', $deprecated = '', $autoload = 'yes') {
    if (array_key_exists($option, TS_WP_Stubs::$options)) return false;
    TS_WP_Stubs::$options[$option] = $value;
    return true;
}
function delete_option($option) {
    TS_WP_Stubs::$log[] = ['delete_option', [$option]];
    $had = array_key_exists($option, TS_WP_Stubs::$options);
    unset(TS_WP_Stubs::$options[$option]);
    return $had;
}
function get_transient($transient) { return TS_WP_Stubs::$transients[$transient][0] ?? false; }
function set_transient($transient, $value, $expiration = 0) {
    TS_WP_Stubs::$transients[$transient] = [$value, (int) $expiration];
    TS_WP_Stubs::$log[] = ['set_transient', [$transient, $value, (int) $expiration]];
    return true;
}
function delete_transient($transient) { $had = isset(TS_WP_Stubs::$transients[$transient]); unset(TS_WP_Stubs::$transients[$transient]); return $had; }
function get_bloginfo($show = '', $filter = 'raw') {
    switch ($show) {
        case 'name': return TS_WP_Stubs::$blogname;
        case 'charset': return TS_WP_Stubs::$charset;
        case 'url': case 'home': case 'wpurl': return TS_WP_Stubs::$home;
        case 'language': return 'ja';
        default: return '';
    }
}
function bloginfo($show = '') { echo get_bloginfo($show, 'display'); }
function get_current_user_id() { return (int) TS_WP_Stubs::$current_user; }
function current_user_can($capability, ...$args) { return in_array($capability, TS_WP_Stubs::$caps, true); }
function user_can($user, $capability, ...$args) {
    $id = is_object($user) ? (int) ($user->ID ?? 0) : (int) $user;
    return in_array($capability, TS_WP_Stubs::$users[$id]['caps'] ?? [], true);
}
function get_userdata($user_id) {
    $u = TS_WP_Stubs::$users[(int) $user_id] ?? null;
    return $u ? (object) array_merge(['ID' => (int) $user_id, 'user_email' => '', 'display_name' => '', 'roles' => [], 'caps' => []], $u) : false;
}
function get_users($args = []) {
    $out = [];
    foreach (TS_WP_Stubs::$users as $id => $u) {
        if (!empty($args['role__in']) && !array_intersect((array) $args['role__in'], $u['roles'] ?? [])) continue;
        $out[] = get_userdata($id);
    }
    return $out;
}
function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
    TS_WP_Stubs::$mails[] = ['to' => $to, 'subject' => $subject, 'body' => $message, 'headers' => $headers];
    return true;
}

// ====================================================================== 日時

function wp_date($format, $timestamp = null, $timezone = null) {
    $tz = $timezone instanceof DateTimeZone ? $timezone : TS_WP_Stubs::tz();
    return (new DateTimeImmutable('@' . ($timestamp ?? TS_WP_Stubs::now_ts())))->setTimezone($tz)->format($format);
}
function current_time($type, $gmt = 0) {
    $dt = (new DateTimeImmutable('@' . TS_WP_Stubs::now_ts()))->setTimezone($gmt ? new DateTimeZone('UTC') : TS_WP_Stubs::tz());
    if ($type === 'timestamp' || $type === 'U') return $dt->getTimestamp() + ($gmt ? 0 : $dt->getOffset());
    if ($type === 'mysql') return $dt->format('Y-m-d H:i:s');
    return $dt->format($type);
}
function wp_timezone() { return TS_WP_Stubs::tz(); }
function wp_timezone_string() { return TS_WP_Stubs::$timezone; }

// ====================================================================== nonce・ハッシュ

function wp_salt($scheme = 'auth') { return 'ts-stub-salt-' . $scheme; }
function wp_hash($data, $scheme = 'auth') { return hash_hmac('md5', (string) $data, wp_salt($scheme)); }
function wp_create_nonce($action = -1) { return substr(wp_hash($action . '|' . get_current_user_id(), 'nonce'), -12, 10); }
function wp_verify_nonce($nonce, $action = -1) { return hash_equals(wp_create_nonce($action), (string) $nonce) ? 1 : false; }
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
    $name = esc_attr($name);
    $field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce($action) . '" />';
    if ($referer) $field .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr($_SERVER['REQUEST_URI'] ?? '') . '" />';
    if ($echo) echo $field;
    return $field;
}

// ====================================================================== エスケープと文字列 (忠実さは見ない)

function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_url($url, $protocols = null, $_context = 'display') {
    $u = trim((string) $url);
    if ($u === '') return '';
    $u = str_replace(['&amp;', '&#038;'], '&', $u);
    return str_replace(['&', "'"], ['&#038;', '&#039;'], $u);
}
function esc_url_raw($url, $protocols = null) { return trim((string) $url); }
function sanitize_url($url, $protocols = null) { return trim((string) $url); }
function esc_js($text) { return addslashes((string) $text); }
function __($text, $domain = 'default') { return $text; }
function _x($text, $context, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function _n($single, $plural, $number, $domain = 'default') { return (int) $number === 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html($text); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr($text); }
function wp_strip_all_tags($text, $remove_breaks = false) {
    $t = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
    $t = strip_tags($t);
    if ($remove_breaks) $t = preg_replace('/[\r\n\t ]+/', ' ', $t);
    return trim($t);
}
function wp_specialchars_decode($text, $quote_style = ENT_NOQUOTES) { return htmlspecialchars_decode((string) $text, is_int($quote_style) ? $quote_style : ENT_QUOTES); }
function wp_slash($value) {
    if (is_array($value)) return array_map('wp_slash', $value);
    return is_string($value) ? addslashes($value) : $value;
}
function wp_unslash($value) { return stripslashes_deep($value); }
function stripslashes_deep($value) {
    if (is_array($value)) return array_map('stripslashes_deep', $value);
    if (is_object($value)) { foreach (get_object_vars($value) as $k => $v) $value->$k = stripslashes_deep($v); return $value; }
    return is_string($value) ? stripslashes($value) : $value;
}
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function maybe_serialize($data) { return (is_array($data) || is_object($data)) ? serialize($data) : $data; }
function is_serialized($data, $strict = true) { return is_string($data) && preg_match('/^(?:[aOsbid]:|N;)/', trim($data)) === 1; }
function maybe_unserialize($data) {
    if (!is_serialized($data)) return $data;
    $u = @unserialize(trim($data));
    return ($u !== false || trim($data) === 'b:0;') ? $u : $data;
}
/** 日本語は WordPress と同じく小文字のパーセント符号化にする (忠実さは見ない。ASCII の slug を使うこと) */
function sanitize_title($title, $fallback_title = '', $context = 'save') {
    $t = strip_tags((string) $title);
    $t = preg_replace_callback('/[^\x00-\x7F]+/u', function ($m) { return strtolower(rawurlencode($m[0])); }, $t);
    $t = strtolower($t);
    $t = preg_replace('/&.+?;/', '', $t);
    $t = str_replace('.', '-', $t);
    $t = preg_replace('/[^%a-z0-9 _-]/', '', $t);
    $t = preg_replace('/\s+/', '-', $t);
    $t = preg_replace('|-+|', '-', $t);
    $t = trim($t, '-');
    return $t === '' ? (string) $fallback_title : $t;
}
function sanitize_text_field($str) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $str))); }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function absint($maybeint) { return abs((int) $maybeint); }
function is_email($email, $deprecated = false) { return (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) ? $email : false; }
function wp_kses_post($data) { return (string) $data; }
function wp_check_invalid_utf8($text, $strip = false) { return (string) $text; }

// ====================================================================== URL

function home_url($path = '', $scheme = null) {
    $url = TS_WP_Stubs::$home;
    if ($path !== '' && is_string($path)) $url .= '/' . ltrim($path, '/');
    return $url;
}
function site_url($path = '', $scheme = null) { return home_url($path, $scheme); }
function get_home_url($blog_id = null, $path = '', $scheme = null) { return home_url($path, $scheme); }
function admin_url($path = '', $scheme = 'admin') { return home_url('/wp-admin/' . ltrim((string) $path, '/')); }
function content_url($path = '') { return home_url('/wp-content/' . ltrim((string) $path, '/')); }
function get_template_directory() { return TS_WP_Stubs::$theme_dir; }
function get_stylesheet_directory() { return TS_WP_Stubs::$theme_dir; }
function get_template_directory_uri() { return home_url('/wp-content/themes/' . basename(TS_WP_Stubs::$theme_dir)); }
function get_stylesheet_uri() { return get_template_directory_uri() . '/style.css'; }
function add_query_arg(...$args) {
    if (is_array($args[0])) { $kv = $args[0]; $uri = (string) ($args[1] ?? ($_SERVER['REQUEST_URI'] ?? '')); }
    else { $kv = [$args[0] => $args[1] ?? '']; $uri = (string) ($args[2] ?? ($_SERVER['REQUEST_URI'] ?? '')); }
    $frag = '';
    if (($i = strpos($uri, '#')) !== false) { $frag = substr($uri, $i); $uri = substr($uri, 0, $i); }
    $query = '';
    if (($i = strpos($uri, '?')) !== false) { $query = substr($uri, $i + 1); $uri = substr($uri, 0, $i); }
    parse_str($query, $qs);
    $qs = array_map(function ($v) { return is_array($v) ? $v : urlencode((string) $v); }, $qs);
    foreach ($kv as $k => $v) { if ($v === false) unset($qs[$k]); else $qs[$k] = $v; }
    $pairs = [];
    foreach ($qs as $k => $v) $pairs[] = ($v === '' || $v === null) ? $k : "$k=$v";
    return $uri . ($pairs ? '?' . implode('&', $pairs) : '') . $frag;
}
function remove_query_arg($key, $query = false) {
    $kv = [];
    foreach ((array) $key as $k) $kv[$k] = false;
    return add_query_arg($kv, $query === false ? ($_SERVER['REQUEST_URI'] ?? '') : $query);
}
function wp_make_link_relative($link) { return preg_replace('|^(https?:)?//[^/]+(/?.*)|i', '$2', (string) $link); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }

// ====================================================================== 条件タグと問い合わせの状態

function is_admin() { return !empty(TS_WP_Stubs::$is['admin']); }
function is_home() { return !empty(TS_WP_Stubs::$is['home']); }
function is_front_page() { return !empty(TS_WP_Stubs::$is['front_page']); }
function is_404() { return !empty(TS_WP_Stubs::$is['404']); }
function is_search() { return !empty(TS_WP_Stubs::$is['search']); }
function is_paged() { return !empty(TS_WP_Stubs::$is['paged']); }
function is_singular($post_types = '') {
    if (empty(TS_WP_Stubs::$is['singular'])) return false;
    if ($post_types === '' || $post_types === []) return true;
    $q = TS_WP_Stubs::$queried;
    return $q instanceof WP_Post && in_array($q->post_type, (array) $post_types, true);
}
function is_single($post = '') { return is_singular() && TS_WP_Stubs::$queried instanceof WP_Post && TS_WP_Stubs::$queried->post_type !== 'page'; }
function is_page($page = '') { return is_singular('page'); }
function is_tax($taxonomy = '', $term = '') {
    $t = TS_WP_Stubs::$is['tax'] ?? '';
    if ($t === '' || $t === false || $t === null) return false;
    return $taxonomy === '' || $taxonomy === [] || in_array($t, (array) $taxonomy, true);
}
function is_post_type_archive($post_types = '') {
    $t = TS_WP_Stubs::$is['post_type_archive'] ?? '';
    if ($t === '' || $t === false || $t === null) return false;
    return $post_types === '' || $post_types === [] || in_array($t, (array) $post_types, true);
}
function get_queried_object() { return TS_WP_Stubs::$queried; }
function get_queried_object_id() { $q = TS_WP_Stubs::$queried; return $q ? (int) ($q->ID ?? ($q->term_id ?? 0)) : 0; }
function get_query_var($var, $default = '') { return TS_WP_Stubs::$query_vars[$var] ?? $default; }
function set_query_var($var, $value) { TS_WP_Stubs::$query_vars[$var] = $value; }
function get_search_query($escaped = true) { $s = (string) (TS_WP_Stubs::$query_vars['s'] ?? ''); return $escaped ? esc_attr($s) : $s; }
function single_term_title($prefix = '', $display = true) {
    $t = TS_WP_Stubs::$queried;
    $name = $t instanceof WP_Term ? $t->name : '';
    if ($display) echo $prefix . $name; else return $name;
}

// ====================================================================== 応答とテンプレート

function nocache_headers() { TS_WP_Stubs::$log[] = ['nocache_headers', []]; }
function status_header($code, $description = '') { TS_WP_Stubs::$status = (int) $code; TS_WP_Stubs::$log[] = ['status_header', [(int) $code]]; }
function wp_die($message = '', $title = '', $args = []) {
    TS_WP_Stubs::$log[] = ['wp_die', [$message, $title, $args]];
    throw new TS_WP_Die(is_string($message) ? $message : '', (string) $title, is_array($args) ? $args : ['response' => (int) $args]);
}
function locate_template($template_names, $load = false, $load_once = true, $args = []) {
    foreach ((array) $template_names as $n) {
        if ($n === '') continue;
        $f = TS_WP_Stubs::$theme_dir . '/' . $n;
        if (is_file($f)) { if ($load) load_template($f, $load_once, $args); return $f; }
    }
    return '';
}
function load_template($_template_file, $load_once = true, $args = []) {
    global $post, $wp_query, $wpdb;
    $post = TS_WP_Stubs::$post;
    if ($load_once) require_once $_template_file; else require $_template_file;
}
function get_header($name = null, $args = []) { TS_WP_Stubs::$log[] = ['get_header', [$name]]; locate_template('header.php', true, false, $args); }
function get_footer($name = null, $args = []) { TS_WP_Stubs::$log[] = ['get_footer', [$name]]; locate_template('footer.php', true, false, $args); }
function get_search_form($args = []) {
    $echo = is_array($args) ? ($args['echo'] ?? true) : (bool) $args;
    ob_start();
    if (!locate_template('searchform.php', true, false)) {
        echo '<form role="search" method="get" class="search-form" action="' . esc_url(home_url('/')) . '"></form>';
    }
    $form = ob_get_clean();
    if ($echo) echo $form;
    return $form;
}
function wp_head() { do_action('wp_head'); }
function wp_footer() { do_action('wp_footer'); }
function language_attributes($doctype = 'html') { echo 'lang="ja"'; }
function body_class($class = '') { echo 'class="' . esc_attr(trim(implode(' ', (array) $class))) . '"'; }
function have_posts() { return TS_WP_Stubs::$loop_i + 1 < count(TS_WP_Stubs::$loop); }
function the_post() {
    global $post;
    TS_WP_Stubs::$loop_i++;
    $cur = TS_WP_Stubs::$loop[TS_WP_Stubs::$loop_i] ?? null;
    $post = TS_WP_Stubs::$post = $cur instanceof WP_Post ? $cur : get_post((int) $cur);
}
function the_content($more_link_text = null, $strip_teaser = false) {
    echo apply_filters('the_content', TS_WP_Stubs::$post ? (string) TS_WP_Stubs::$post->post_content : '');
}
function get_the_content($more_link_text = null, $strip_teaser = false, $post = null) { $p = get_post($post); return $p ? (string) $p->post_content : ''; }
function the_title($before = '', $after = '', $echo = true) {
    $t = get_the_title();
    if ($t === '') return '';
    $t = $before . $t . $after;
    if ($echo) echo $t;
    return $t;
}
function the_permalink($post = 0) { echo esc_url(get_permalink($post)); }
function posts_nav_link($sep = '', $prelabel = '', $nxtlabel = '') { TS_WP_Stubs::$log[] = ['posts_nav_link', [$sep, $prelabel, $nxtlabel]]; }

$wpdb = new wpdb();
