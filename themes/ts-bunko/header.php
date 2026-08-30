<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="ts-site-header">
  <div class="ts-shell">
    <p class="ts-site-title"><a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></p>
    <nav class="ts-site-nav" aria-label="サイト内">
      <a href="<?php echo esc_url(home_url('/')); ?>">トップ</a>
      <a href="<?php echo esc_url(home_url('/?s=')); ?>">検索</a>
      <a href="<?php echo esc_url(home_url('/about/')); ?>">このサイトについて</a>
    </nav>
  </div>
</header>
<main class="ts-shell">
