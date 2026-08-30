<?php
/**
 * Plugin Name: ts-library loader
 * Description: mu-plugins はサブディレクトリを自動読み込みしないため、このローダが
 *              ts-library/ts-library.php を読む。配備先: wp-content/mu-plugins/ 直下。
 */
require __DIR__ . '/ts-library/ts-library.php';
