<?php
/**
 * `wp ts` のサブコマンドの登録 (T8.2)。WP-CLI からのみ動く (Web リクエストでは読まれない —
 * ts-library.php が WP_CLI のときだけ TS_Library_Files::CLI を読む)。
 *
 * サブコマンド名はここだけに書く。各コマンドの引数と出力はそれぞれのクラスの docblock と本体にある。
 */

if (!defined('ABSPATH') && !defined('TS_LIBRARY_TEST')) { exit; }

WP_CLI::add_command('ts sync-terms', [new TS_Sync_Terms_Command(), 'sync_terms']);
WP_CLI::add_command('ts sync-pages', [new TS_Pages_Command(), 'sync_pages']);
WP_CLI::add_command('ts export-pages', [new TS_Pages_Command(), 'export_pages']);
WP_CLI::add_command('ts sync-docs', [new TS_Sync_Docs_Command(), 'sync_docs']);
WP_CLI::add_command('ts import', [new TS_Import_Command(), 'import']);
WP_CLI::add_command('ts verify', [new TS_Verify_Command(), 'verify']);
WP_CLI::add_command('ts apply-takedown', [new TS_Takedown_Command(), 'apply_takedown']);
WP_CLI::add_command('ts export-takedown-urls', [new TS_Takedown_Command(), 'export_takedown_urls']);
WP_CLI::add_command('ts export-takedowns', [new TS_Takedown_Command(), 'export_takedowns']);
WP_CLI::add_command('ts reset', [new TS_Reset_Command(), 'reset']);
WP_CLI::add_command('ts export-pathmap', [new TS_Pathmap_Command(), 'export_pathmap']);
WP_CLI::add_command('ts role-sync', [new TS_Role_Sync_Command(), 'role_sync']);
