# S.208 原本アーカイブの公開 (GitHub Pages) とリポジトリ構成

- 種類: 仕様
- 状態: 有効
- 出典: ルートの README.md の「公開（GitHub Pages）」と「リポジトリ構成・履歴」(2026-10-26 にここへ移した)
- 関連: T.1.1, S.58, S.68, S.205

## 公開（GitHub Pages）

`master` ブランチへの push で `.github/workflows/deploy-pages.yml` が動作し、GitHub Pages へ自動デプロイされます。
初回のみ **Settings → Pages → Build and deployment → Source** を **「GitHub Actions」** に設定してください。

## リポジトリ構成・履歴

- ディレクトリ対応: リポジトリ直下 = `ts.novels.jp/`、
  `~ts/`・`~yaji/`・`~bbs/` = `www.novels.jp/` の各ユーザディレクトリ
  （矢治さんの意見ページは `~yaji/opinion/`）、
  `~yays/` = `www14.big.or.jp/~yays/`（旧世代小説ライブラリ `~yays/library/` を完全収蔵。
  同一構造の本体ツリーへのギャップフィル供給源でもある）、
  `~ezpe/` = `www2.tomato.ne.jp/~ezpe/`、`ts.novels.name/` ほかホスト名ディレクトリ = 各姉妹ドメイン。
- `scripts/` … 回収・監査・変換ツール一式（ミラー本体ではない）。
- `TASKS.md` と `SPECS.md` + `specs/` … タスク (T) と、設計・手順・記録の仕様 (S)。**プロジェクト内で使う語の定義は
  [`specs/S.14.md`〜`S.19.md`](S.14.md) を優先する**（WordPress 移築の設計文書で使う内部用語を含む）。
- タグ **`raw-original`** … 初期 Wayback 回収分の変換前原本バイト。以後の追加回収分も、
  いずれも**変換前の生バイトを一旦コミットしてから** UTF-8 化しており、各 raw コミットが履歴に残っています。
- `.nojekyll` … GitHub Pages の Jekyll 処理を無効化。

---

*本ミラーは資料保存を目的とした非営利のアーカイブです。各作品の著作権は原著者に帰属します。*
