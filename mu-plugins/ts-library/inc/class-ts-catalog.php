<?php
/**
 * catalog/ (単一真実源) の読み込み。
 *
 * catalog は本番では公開ルートの外 (`~/novels.xwp.jp/catalog/`) に置く。
 * 配備は scripts/wp/deploy.sh。ここは読むだけで、書き戻すことはしない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TS_Library_Catalog {

	/** JSON ファイルを配列で返す。 */
	public static function read_json( string $path ): array {
		$raw = self::read_file( $path );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( sprintf( '%s の JSON を読めない: %s', $path, json_last_error_msg() ) );
		}
		return $data;
	}

	/** JSONL を「1 行 1 レコード」の配列で返す。 */
	public static function read_jsonl( string $path ): array {
		$fh = @fopen( $path, 'r' );
		if ( ! $fh ) {
			WP_CLI::error( sprintf( 'catalog のファイルが読めない: %s', $path ) );
		}
		$out = array();
		$n   = 0;
		while ( ( $line = fgets( $fh ) ) !== false ) {
			++$n;
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$rec = json_decode( $line, true );
			if ( ! is_array( $rec ) ) {
				fclose( $fh );
				WP_CLI::error( sprintf( '%s の %d 行目が JSON として読めない: %s', $path, $n, json_last_error_msg() ) );
			}
			$out[] = $rec;
		}
		fclose( $fh );
		return $out;
	}

	public static function read_file( string $path ): string {
		if ( ! is_readable( $path ) ) {
			WP_CLI::error( sprintf( 'ファイルが読めない: %s', $path ) );
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			WP_CLI::error( sprintf( 'ファイルの読み込みに失敗: %s', $path ) );
		}
		return $raw;
	}

	/**
	 * terms.json から「原表記 → term slug」の逆引きを作る。
	 *
	 * episodes.jsonl の genre_raw / type_raw / keywords_raw / zokusei_raw は
	 * **正規化前の原表記**で、terms_build.py が同じ値を term の raw_variants に残している。
	 * したがって raw_variants を引けば正規化ロジックを PHP 側に再実装しなくてよい
	 * (実測: 原表記 14,948 個のうち引けないのは `?` の 1 個だけで、これは正規化すると空になる語)。
	 */
	public static function build_term_lookup( array $terms ): array {
		$lookup = array();
		foreach ( ( $terms['taxonomies'] ?? array() ) as $tax => $spec ) {
			$map = array();
			foreach ( ( $spec['terms'] ?? array() ) as $term ) {
				$slug = $term['slug'];
				if ( ! isset( $map[ $term['name'] ] ) ) {
					$map[ $term['name'] ] = $slug;
				}
				foreach ( ( $term['raw_variants'] ?? array() ) as $raw ) {
					if ( ! isset( $map[ $raw ] ) ) {
						$map[ $raw ] = $slug;
					}
				}
			}
			$lookup[ $tax ] = $map;
		}
		return $lookup;
	}

	/**
	 * 配備時に deploy.sh が書く catalog の git コミット。
	 * 「いま本番に何が載っているか」の追跡に使う (rebuild-runbook §2-D)。
	 */
	public static function read_commit_stamp( string $catalog_dir ): ?string {
		$path = rtrim( $catalog_dir, '/' ) . '/.catalog-commit';
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$v = trim( (string) file_get_contents( $path ) );
		return '' === $v ? null : $v;
	}

	/** JSON メタ値の共通表現 (人が mysql から読める形にする)。 */
	public static function json_meta( $value ): string {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
