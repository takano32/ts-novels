# S.188 本文投入: 状況とペイロード生成 (T.9.1、元の 3.1)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の「Phase 3」の状況と 3.1
- 関連: T.9, T.9.1, T.39.5

## T.9 — 本文投入(目安 2 週)

**状況 (2026-08-30 時点): 3.5(作者連絡。T.33)を除いて完了。**
本文 3,646 話ぶんを投入し、**`wp ts verify` は OK**(投稿 4,363・orphan 0・語彙外 term 0・
taxonomy 割当 6 本・書誌メタ 4 本すべて期待値と一致)、
**`wp ts import` は実行・dry-run とも created=0 / updated=0 で冪等**。
独立検算の原本併読 QA(3.3)は **100 話で問題率 0.0%(ng 0)** で、
**本文の欠落・順序破壊・文字化けは 0 件**。
本文 status は **converted 3,642 / converted-text 2 / raw-fallback 2 / placeholder 198**。
本番の `ts_catalog_commit` = `3f20b1a00b50c95221e0d1f04a82a77d48677356`。
**既知の未解決バグは無い。** 次は T.10(テーマ)。

なお **placeholder 198 話**は payload を持たない話(画像でも本文でもない外部 URL スタブ等)で、
設計どおりの状態。書誌カードから原本へ辿れるようにするのは T.10 の仕事。

- [x] 3.1 本文投入: **MD→Gutenberg ブロック HTML への変換は開発機の python で事前実施**し、
      投入用 payload を生成して rsync → `wp ts import --bodies` は payload を post_content に
      流すだけにする(サーバ PHP に Markdown パーサを導入しない)。ブロック対応:
      paragraph/separator/quote/heading/image。ruby と本文内 table はインライン HTML。
      raw フォールバック分は `wp:html` 1 ブロック+`_ts_body_status=raw-fallback`
      - **3.1 実測 (2026-08-30、コミット `d79ac2a4`)**: payload は開発機で生成した
        **3,644 本 / 178MB**(`payloads/<episode_id>.html`)。deploy.sh の [2/6] が
        `repo/payloads/` へ運ぶ(**初回転送 178,470,680 バイトを 36 秒**、6.5MB/s)。
      - **投入**: `wp ts import --bodies=$HOME/novels.xwp.jp/repo/payloads`。
        ETA 見積りに `--limit=100` を先に流して **updated=100 skipped=1451 / 33.9 秒**
        → 全量を **created=0 / updated=3744 / skipped=1551 / 2 分 28 秒**で完了
        (2 回の合計で episodes 3,844 全部を更新。`--limit=100` の分が 2 回目では
        skipped に回るのでこの内訳になる)。
        **works 1,451 が skipped なのは正常** — work 投稿の hash に本文は入らないので
        差分が出ない。よって「全量更新 = 5,295」ではなく **episodes 3,844 が更新の全量**
      - **冪等**: `wp ts import --bodies=… --dry-run` = **created=0 / updated=0 /
        skipped=5295**。`wp ts verify` は **OK 維持**(投稿 4,363・orphan 0・
        語彙外 term 0・割当 6 本すべて期待値と一致)。
        `ts_catalog_commit` = `d79ac2a4516d2926e53390fd629be53a84e85dd5`
      - **`_ts_body_status` の分布 (合計 3,844 = episodes 全数)**:
        **converted 3,642 / placeholder 200 / raw-fallback 2**。
        placeholder 200 は payload を持たない話(3,844 − 3,644)で、
        corpus 内訳は **honkan 192 / legacy 7 / extern-repost 1**(画像作品・外部 URL
        スタブ等、1.6 の変換対象外だったもの)。
        本文が空の投稿 519 は**連載の親 work** で正常(単発 932 は親自身が本文を持つ)
      - **受入 curl (すべて `?_cb=$RANDOM` 付き)**:
        `/novel/johdan-tsukinuke/19204751-tsukinuke/` **200**・
        `<p class="wp-block-paragraph">` が **451 段落**・placeholder 文言 **0 箇所** /
        **ruby は HTML として出力**(`<ruby>悍<rp>（</rp><rt>おぞ</rt><rp>）</rp></ruby>` の
        形。`<rp>` フォールバック付き、2 箇所) /
        引用は `/novel/mondo-nidaimehamikosamatsu/`(hime_miko)で
        **`<blockquote class="wp-block-quote …">` が出力**(中に paragraph ブロック) /
        単発作品 `/novel/kagerou6-pootoreeto-hiroiniii/` **200**・39 段落・placeholder 0 /
        raw-fallback 2 件も本文あり(`/novel/aibatou-koromogaemae/koromogae1/` 14,693 バイト・
        `/novel/million-tenshinogyararii/` 6,148 バイト)
      - **~~3.1 の残件~~ → 解消 (2026-08-30、コミット `8fe04aed`)**:
        pixiv 再掲 2 作(1.9 の積み残し = プレーンテキスト経路)が placeholder のままだったが、
        payload 生成器にテキスト経路が入って解決。payloads は 3,644 → **3,646 本**。
        `wp ts import --bodies=…` = **created=0 / updated=2 / skipped=5293 / 1 分 55 秒**
        (差分の 2 件だけが更新される、という読みどおり)。
        **`_ts_body_status` は converted 3,642 / converted-text 2 / raw-fallback 2 /
        placeholder 198**(合計 3,844)。
        受入 curl: 雨女 `/novel/omochibako-ameonna/` **200**・**10 段落・7,268 バイト**、
        きらいなもの→ＧＷ `/novel/omochibako-kirainamono-gw/` **200**・**6 段落・5,722 バイト**、
        どちらも placeholder 文言 0。本文は末尾「おわり」まで揃っている
      - **軽微な気づき (要判断)**: 再掲 2 件の本文は先頭 2 段落が
        **`執筆者:` と `カテゴリ:` の値なしラベル**になる(pixiv のテキスト書き出しの
        ヘッダ行がそのまま段落化されている)。読める本文は 3 段落目から。
        気になるなら payload 生成器側で空ラベル行を落とす
