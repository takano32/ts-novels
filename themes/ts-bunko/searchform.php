<form role="search" method="get" class="ts-searchform" action="<?php echo esc_url(home_url('/')); ?>">
  <label><span class="screen-reader-text">検索語</span>
    <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="題名・本文を検索">
  </label>
  <button type="submit">検索</button>
</form>
