# S.213 authors.json の欄

- 種類: 仕様
- 状態: 有効
- 出典: catalog/README.md の「authors.json の主な欄」(2026-10-26 にここへ移した)
- 関連: T.7.4, T.7.5.1, S.174, S.176, S.211

## authors.json の主な欄

| フィールド | 内容 |
|---|---|
| `slug` | 恒久 URL `/authors/<slug>/`。感想板 id が最優先、無ければ T.7.5.1 (specs/S.176.md) の裁定 |
| `display_name` / `display_variants[]` | 代表表示名と、統合した全表記 (`Ｍａｒｉｅ` / `麗香` など) |
| `kansou_slug` / `kansou_annex_url` | 本人の感想板 id と原本アーカイブ上の板ログ URL |
| `kansou_slug_alt[]` | **同じ人を指す別の板** — 旧名義の板 (薪喬 → `shinkyou`)・別名の板 (HIKU → `hiku`)・誤植の板 (`kamikawa_ayano_`)。現状 8 名 |
| `shared_boards[]` | 共有世界の板 (華代ちゃん等)。作者の板ではない |
| `yomi_group` | `lib-index-*.html` の五十音索引での所属行 |
| `slug_source` / `slug_status` | `kansou-board` / `ascii` / `pykakasi` と、`auto` / `confirmed` |
| `identified_by` | どの規則で同定したか (`board` / `name->board` / `name->board-list` / `name-only`) |
