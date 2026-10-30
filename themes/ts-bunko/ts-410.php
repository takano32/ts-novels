<?php
/**
 * 取り下げた作品への応答 (410。mu-plugin の includes/visibility.php が状態を 410 にしてこのテンプレートを選ぶ)。
 * 題名は出さない (specs/S.118.md)。
 */
get_header();
?>
<article>
  <h1 class="ts-entry-title">掲載していない作品</h1>
  <p>この作品は作者の申し出により掲載していません。</p>
  <p><a href="<?php echo esc_url(home_url('/removed/')); ?>">取り下げ済みの一覧</a></p>
</article>
<?php
get_footer();
