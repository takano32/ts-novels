# S.72 やり直しの大原則と、直したいこと → 変える場所の対応表

- 種類: 手順
- 状態: 有効
- 出典: docs/rebuild-runbook.md の冒頭・「0. 大原則」・「1. 対応表」
- 関連: T.10.7, T.25.6, S.73, S.74

## やり直しランブック — レビューで NG だったときの再走手順

本プロジェクトは**全工程を再走できる**ように作ってある(単一真実源 = `catalog/`、
WordPress の DB は使い捨ての派生ビュー)。このランブックは「構築後のレビューで NG が出て、
判断を変えてやり直す」ときに、**どこを直せば何が変わるか**と**再走のコマンド**をまとめる。
用語は S.14〜S.19。

### 0. 大原則

- **手で直してよいのは「入力」だけ**: 判断ファイル(下表)・スクリプト・設計書。
  生成物(`catalog/*.jsonl`・`catalog/QA.md`・`bodies/`・WP の DB)を手で直しても
  次の再生成で消える
- **すべての判断はリポジトリに記録されている**。git 履歴が再走の完全な記録
- 再走は何度でも安全: catalog 生成は決定的(同じ入力なら 2 回流してバイト同一)、
  WP への投入は冪等(episode_id で upsert)

### 1. 「何を直したいか」→「どこを変えるか」対応表

| レビューで NG だったもの | 直す場所 | 直した後の再走 |
|---|---|---|
| 作者・作品の slug(URL) | `catalog/slug_overrides.yml`(該当行を書き換え `status: confirmed`) | S.73 の A |
| 作品のまとめ方(シリーズ分割・併合) | `catalog/work_overrides.yml` | S.73 の A |
| 話の作者・帰属の誤り | `catalog/episode_overrides.yml` | S.73 の A |
| ジャンル・種別・キーワードの正規化 | `catalog/genre_map.yml` / `type_map.yml` / `keyword_map.yml` | S.73 の A |
| 本文の変換品質(欠落・整形) | `scripts/wp/body_convert.py`(不変量を壊さないこと。`make verify` = html5lib 検算を必ず流す) | S.73 の B |
| 目録のパース(拾い漏れ・誤読) | `scripts/wp/catalog_build.py` ほか builder | S.73 の A |
| 特定作品を非公開に | `takedown/denylist.yml` | `wp ts apply-takedown` のみ |
| 方針そのもの(掲載範囲・URL 設計 等) | S.20〜S.43 に改訂章を**追記**(v1.5, v1.6, …。書き換えず追記が慣例) → TASKS.md と S へ展開 | 影響範囲による |
