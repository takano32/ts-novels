# S.182 wp ts コマンド群 (T.8.2、元の 2.2)

- 種類: 記録
- 状態: 一部失効 (S.43 で上書き。import が post_status に触れない点)
- 出典: docs/wp-implementation-tasks.md の 2.2
- 関連: T.8.2, S.43

- [x] 2.2 `wp ts` WP-CLI コマンド群(mu-plugin 内): `sync-terms` / `import`(--dry-run/--limit/
      --author、created/updated/skipped 集計出力) / `apply-takedown` / `verify`(件数照合・orphan・
      taxonomy 被覆率・**特殊エントリ内訳**・冪等性=2 回目 created/updated 0) / `export-pathmap` /
      **`reset --yes`(ts_* の全投稿・term・meta を削除 — やり直し用。S.73 の C)**。
      **import は投入時の catalog の git コミットハッシュを `wp option ts_catalog_commit` に記録する**
      (何が本番に載っているかの追跡。S.73 の D)
      - **2.2 修正後の検収 (2026-08-30、コミット `70873826` + `2bd22a7d`)**: 下の初回実行で
        出た (A)〜(E) は**全て解消**。`wp ts reset --yes`(人間 (高野さん) の手動実行。実行セッションからは
        権限分類器に掛かる)→ `sync-terms` → `import` の再投入で確認した実測:
        - **(A) 冪等性 = 達成**。`wp ts import --dry-run` で **created=0 / updated=0 /
          skipped=5295**、さらに**実 import(dry-run でない)でも created=0 / updated=0 /
          skipped=5295**(1分48秒)。`_ts_import_hash` の奪い合いは解消
        - **(B) 語彙外 term = 全 taxonomy で 0**。term 数は catalog と完全一致
          (ts_author 324 / ts_genre 196 / ts_type 165 / ts_keyword 1032 / ts_world 14 /
          ts_corpus 5)、**パーセントエンコード slug の term は 6 taxonomy とも 0 本**
          (旧: 場当たり term 184 本)
        - **(C) 共有世界板の作者誤認 = 解消**(語彙外 ts_author term 0)
        - **(D) 単発作品の作者欠落 = 解消**。**ts_author を持たない親 work 投稿 0**(旧 266)。
          割当 ts_author も **4363/4363** で全投稿被覆
        - **(E) verify の検査項目 = 拡充済み**。投稿数・orphan・taxonomy 別の
          「WP / 語彙 / 語彙外 / 未作成」・taxonomy 別の割当数照合・未解決値の一覧を出す。
          期待値を catalog から**毎回再計算**して importer と独立に数え直す作りになっている
        - **新たに判明して修正された 6 件目**: 旧 importer は **ts_world を一度も付けていなかった**
          (`割当 ts_world: WP=0 期待=420`)。修正後は **420/420**
      - **2.2 最終検収 (2026-08-30、コミット `322522a8` + `2fe08197`)**:
        下の「残っていた未達 1 件」も**解消し、`wp ts verify` が初めて OK で終わった**。
        `terms.json` の各 term に**照合用の異表記 `lookup_variants`(genre 51 / type 26 /
        keyword 88)**が入り、半角 `?` 形が正規の term に吸われるようになった。実測:
        - `wp ts sync-terms` = **created=0 updated=1 skipped=1735 / 1.1 秒**
          (updated 1 = 擬似作者「(シリーズ目録)」の全角括弧化のみ)
        - `wp ts import`(全量)= **created=0 updated=5295 skipped=0 / 2 分 18 秒**
          (HASH_VER v3 で全件が差分扱い。reset は不要だった)
        - **`wp ts verify` = OK**。割当は **ts_author 4363 / ts_genre 5131 / ts_type 4381 /
          ts_keyword 5424 / ts_world 420 / ts_corpus 3844** が**全て期待値と一致**、
          語彙外 term 0・未作成 0・orphan 0、**「term 未解決の値」の警告は消滅**
        - `wp ts import --dry-run` = **created=0 / updated=0 / skipped=5295**(冪等)
        - 合流の確認: `ts_genre` の `gakuen` が **745 件**(`学園?` 表記 20 話が合流)、
          `ts_type` の `henshin` が **1,892 件**(旧 `変身(?)` の場当たり term 1,886 が本来の
          term に統合)。`ts_catalog_commit` = **`322522a8f0248aea3976cbe23e1b9753e3b4292b`**
      - **2.2 に残っていた未達 1 件 (→ `322522a8` で解消済み。経緯として残す)**:
        **分類語彙の異表記 166 種が term に解決できず、割当 448 件が捨てられていた。**
        import が `Warning: term に解決できず割当を落とした値: 164 種`、verify も
        `term 未解決の値 (164 種)` を出して **verify NG** で終わる。内訳(catalog 側で再集計):
        **ts_genre 52 種 / 延べ 169・ts_type 26 種 / 延べ 105・ts_keyword 88 種 / 延べ 174**。
        値は `学園?` `コメディ?` `魔法?` `変身?` `理解ある女友達(?)` `メカ?` のような
        **末尾に半角 `?` や `(?)` が付いた形**。
        **原因**: 1.3 の正規化は**全角「？」の除去**しかしておらず、原本に多い
        **半角 `?`** の異表記が `terms.json` の `name` にも `raw_variants` にも入っていない。
        importer は name / raw_variants / slug の対応表でしか引かないので解決できず捨てる。
        **直す場所は `terms_build.py`(半角 `?`・`(?)` も正規化して raw_variants に載せる)**で、
        importer 側ではない。直したら `make catalog` → deploy → import のやり直しが要る
      - **同じ原因の副作用 (verify の割当数不一致 1 件)**: `割当 ts_genre: WP=4974 期待=4963` の
        **+11 は全て `sf` term**。`SF?` / `SF(?)` を持つ 11 話が該当する。
        importer の `term_ids()` は対応表に無い値を `?? $v` で**slug とみなして**
        `get_term_by('slug', 'SF?')` を引き、WP の `get_term_by` が内部で `sanitize_title()` を
        掛けて `?` を落とすため **偶然 `sf` に当たって割り当たる**。
        一方 verify の期待値計算は `?? null` で数えない。**import と verify で
        未マップ値の扱いが非対称**なのが直接の原因(上の raw_variants を直せば
        両方とも正規に解決するので、この不一致も自然に消える)
        → **`322522a8` の `lookup_variants` 導入で予告どおり両方とも解消**
        (`割当 ts_genre` は 4974/4963 の不一致 → **5131/5131 の一致**へ)
      - **旧・既知バグ 1 件 (→ `ca583444` で解消済み。経緯として残す)**:
        `child_slugs()` がアンカー付きの話で全角→半角変換を取りこぼしていた。
        投稿 ID 8038(`novel__story3.html@ＢＢＳ`)の slug が
        `story3-%ef%bd%82%ef%bd%82%ef%bd%93` のままで `story3-bbs` にならなかった。
        原因は**処理順**で、`mb_convert_kana($s,'as')` を掛けた**後**に
        `$s .= '-' . strtolower($ep['source_anchor'])` でアンカーを連結していたため、
        **アンカー側の全角文字が変換を通らなかった**(`strtolower` はバイト単位なので
        全角大文字も落ちない)。**修正 = 変換をアンカー連結の後に移動**
      - **解消の検収 (2026-08-30、コミット `ca583444`)**:
        `wp ts import` = **created=0 / updated=1 / skipped=5294 / 1 分 48 秒**
        (変換順が影響するのはこの 1 件だけ、という読みどおり)。
        `wp ts verify` = **OK 維持**(割当 6 本すべて期待値と一致)。
        `wp ts import --dry-run` = **created=0 / updated=0 / skipped=5295** で収束を確認。
        投稿 ID 8038 の `post_name` は **`story3-bbs`**、
        URL `/novel/yays-awasekagaminokoibitotachi/story3-bbs/` が **200**
        (旧パーセントエンコード URL は **404**)。
        **`LOCATE('%', post_name) > 0` の ts_work 投稿は 0 件**、
        `^[a-z0-9_-]+$` 以外の post_name も **0 件**(全 4,363 投稿が ASCII slug)。
        `ts_catalog_commit` = **`ca583444047f681a0e0b59009b1d54be6fa3d3de`**
      - **2.2 初回実行時の記録 (2026-08-30。修正前。上記のとおり全て解消済み)**:
        6 サブコマンドは全て登録され動作する(`wp help ts` で確認)。ただし以下が未達:
        - **(A) 冪等性が成立しない** — 受け入れ条件「2 回目 created=0 / updated=0」に対し
          実測は **created=0 / updated=1864**(全量)・**updated=76**(パイロット 100 話)。
          `updated` は 3 回・4 回目も同値で発散はしないが 0 にならない。
          原因: `upsert_work()` と `fill_single_work()` が**同一投稿の同一メタキー
          `_ts_import_hash` を奪い合っている**。単発作品は 1 投稿を両者が触るため、
          works ループが work JSON の md5 を書く → episodes ループが `single:` 付き md5 で
          上書き、を毎回繰り返す。**単発 work 1 件につき 1 回の実行で 2 updated**。
          実測が `2 × 932(単発 work 数) = 1864` と完全一致することで裏付け済み
        - **(B) 分類語彙が sync-terms の語彙に載らず、場当たり term が 184 本増殖**。
          `apply_episode_data()` は `wp_set_object_terms($id, $names, $tax)` を **name 解決**で
          呼ぶが、WP の `term_exists()` は **slug 照合が先**で、`sanitize_title('学園?')` と
          `sanitize_title('学園')` はどちらも `%e5%ad%a6%e5%9c%92` に潰れる。
          episodes.jsonl の値(`学園?`)は terms.json の正規化名(`学園`)と一致しないので
          まず `学園?` という term が新規作成され、以後 `学園` もその term に吸われる。
          実測: **ts_genre 割当 5,135 のうち 3,682 (72%) / ts_type 4,381 のうち 3,438 (78%) /
          ts_keyword 5,424 のうち 2,200 (41%)** が、catalog 由来 term ではなく
          **パーセントエンコード slug の場当たり term**に付いた(1.3 の正規化が事実上無効化)。
          恒久 URL にもなるので要修正。**term は slug で解決すべき**
        - **(C) 共有世界の板 slug が `ts_author` になる** — `apply_episode_data()` が
          `ts_author` を `$ep['kansou_slug']`(= 感想板 id)から付けるため、authors.json に
          存在しない **11 板 slug**(kayo_chan 218 / himekami 41 / foster 21 / 2daime 12 /
          utanotsuki 11 / d_angel 11 / delayed 10 / relay_novel 8 / setubou 2 /
          rental_body 1 / sugar_sweets 1 = **延べ 336 話**)が作者 term として作られた。
          これは S.174 訂正 (b) が名指しで警告していた誤認そのもの
        - **(D) 単発作品 266 件が作者を失う** — `apply_episode_data()` は
          `wp_set_object_terms($id, $ep['kansou_slug'] ?: null, 'ts_author')` なので
          **kansou_slug が空(957 話)だと ts_author を空にする**。単発作品では
          `upsert_work()` が works.jsonl の `author_slug` で正しく付けた作者を
          直後の `fill_single_work()` が消してしまう。
          実測: **ts_author 無しの投稿 957 / うち単発 work 266**(連載の親 work は 0)。
          T.10 の作者ページ・五十音索引が欠落するので公開前に要修正
        - **(E) `verify` の実装が docblock より狭い** — 実装は「投稿数照合」と
          「手動編集警告」だけ。タスクリストが要求する **orphan / taxonomy 被覆率 /
          特殊エントリ内訳 / 冪等性(2 回目 created・updated 0)** は未実装。
          上記 (A)〜(D) が verify OK をすり抜けたのはこのため
        - 参考(不具合ではない): authors.json の `karaage_New` は WP が slug を小文字化して
          `karaage_new` になる。`unattributed` / `series-index` は authors.json に無い
          擬似作者なので import 側で term 名 = slug のまま作られる
        - **修正記録 (2026-08-30, 高品位なモデルとエフォート)**: (A)〜(E) を commands.php で修正済み —
          (A) 単発 fill の hash を `_ts_fill_hash` に分離 /
          (B) terms.json の name・raw_variants→slug 対応表を積み全 taxonomy を **term ID で割当**。
          未解決値は場当たり term を作らず option `ts_term_warnings` に記録。あわせて未実装だった
          **ts_world 割当 (episode_worlds 408 件)** を追加 /
          (C)(D) ts_author は作品の author_slug のみから。kansou_slug はメタ限定、単発 fill は作者に
          触らない。擬似作者 `unattributed`(作者不詳)/`series-index` は sync-terms が term を作る
          (sync-terms の期待 created は 1,734→**1,736**) /
          (E) verify に orphan・語彙整合(語彙外/未作成 term)・taxonomy 割当数の catalog 独立再計算
          照合・term 未解決値チェックを実装(冪等は `import --dry-run` 2 回目 created=0/updated=0 で確認) /
          子投稿の URL slug は原ファイル名の語幹から決定的に生成(`child_slugs()`: 作品内重複は
          親ディレクトリ前置→日付後置→jsonl 順連番。全て ASCII になる)。
          `HASH_VER=v2` のため既存投稿は全て更新対象。**再投入は reset --yes → sync-terms →
          import → verify → import --dry-run(冪等確認)の順**
