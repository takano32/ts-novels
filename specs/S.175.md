# S.175 Episode → Work のクラスタリング (T.7.5)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.5
- 関連: T.7.5, S.107

- [x] T.7.5 `scripts/wp/work_builder.py`: Episode → Work クラスタリング → `catalog/works.jsonl`
      - シード: `series.html` 有効 **112** 行(コメント除去後にリンクを持つ TR。
        旧記述の「123 行」は誤り)+`share_world.html`+
        **シリーズタイトルページ(novel/ 配下の `*title*.htm*` および投稿ディレクトリ内 index.html 名、
        計約 124+旧世代ツリー内 51)**+推薦文内ナビ 696 リンクの誘導先 URL 集合
      - 補完: 作者×ファイル名 prefix×題名共通接頭辞クラスタ
      - あいまい分は `needs_review: true` で `catalog/work_overrides.yml` 雛形に出力(想定 100〜200 件)
      - work_slug = `{author_slug}-{作品ローマ字}`。**作品ローマ字も pykakasi 候補+
        `slug_overrides.yml` 経由の人間 (サイト所有者) の確認ゲートを通す**。重複ファイルは正本 1 つ+`alias_paths`
      - 受け入れ: 全 episode がいずれかの work に属す。orphan 0
      - **T.7.5 の実測 (2026-08-30 再計測。T.7.8 (目録外収蔵と pixiv 再掲) で episode が 2,984→3,844 に増えた分を反映)**:
        **Work 1,463 件**(単発 946 / 連載 517)、3,844 episode 全部がどれかに属し **orphan 0**。
        `needs_review` は **328 件**(内訳: 弱い根拠のみ 269 / 15 話以上 22 /
        複数ディレクトリ 37)。md5 一致の重複ファイル 55 本を `alias_paths` に。
        タイトルページを持つ work 234 件、series.html の有効行 112。
        (数え方: `catalog/works.jsonl` の行数、内訳は
        `catalog/reports/work_builder.json` の `works_single_episode` /
        `works_multi_episode` / `needs_review` / `needs_review_reasons` /
        `alias_paths_total` / `works_with_title_page` / `series_html_rows`)
      - **タスクリストの記述の補足 3 点**:
        (a) 「series.html 有効 123 行」は実測 **112 行**(リンクを持つ行。
        コメント除去後の `<TR>` は 135 だがヘッダ行と番外編のみの行を含む)
        (b) 「推薦文内ナビ 696 リンク」は実測 **1,236 リンク・誘導先 114 ページ**
        (【シリーズタイトルはこちら】1,023 +【華代ちゃんシリーズタイトルはこちら】139 ほか)
        (c) **Work は 1 作者に閉じる規則を追加した**。共有世界のタイトルページ
        (novel/kayo_chan/index.html に 139 リンク) は 69 名の話を 1 つに束ねてしまい、
        「作品」ではなく「世界」になる。シリーズとしての同一性は ts_world が担う
