<?php
/**
 * catalog の JSON / JSONL を読む (T8.2)。WordPress を使わない。
 *
 * 読めないときは RuntimeException を投げる (文言はそのまま WP_CLI::error に渡す。includes/commands/command-base.php)。
 *
 * PHP 8.0 互換。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

final class TS_Catalog_Files {

    /** JSONL (1 行 1 レコード)。空行は飛ばす */
    public static function read_jsonl(string $path): array {
        if (!is_file($path)) throw new RuntimeException("ファイルがありません: $path");
        $out = [];
        $fh = fopen($path, 'r');
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $row = json_decode($line, true);
            if ($row === null) { fclose($fh); throw new RuntimeException("JSONL の壊れた行: $path"); }
            $out[] = $row;
        }
        fclose($fh);
        return $out;
    }

    public static function read_json(string $path): array {
        if (!is_file($path)) throw new RuntimeException("ファイルがありません: $path");
        $d = json_decode(file_get_contents($path), true);
        if ($d === null) throw new RuntimeException("JSON が読めません: $path");
        return $d;
    }
}
