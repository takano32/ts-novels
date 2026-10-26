# S.179 pixiv 再掲 2 作の収録 (T.7.8、元の 1.9)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.9
- 関連: T.7.8, S.41

- [x] 1.9 保管中の pixiv 再掲 2 作を catalog に収録 (`~/ts-novels-holding/stage_repost/`)
      - **雨女**(おもちばこ) = 文庫の欠落 `novel/200802/15232641/rainGirl.html` に対応。
        本文は**作者本人の pixiv 再掲 (id 351985)** から取得。`provenance` に出所 `pixiv`・
        作品 id・取得日を必ず記録する
      - **きらいなもの→ＧＷ**(同) = **文庫には一度も掲載されていない pixiv 限定の番外編**。
        `corpus=extern-repost` で区別し、書誌カードに「文庫未掲載・pixiv 由来」と出せるメタを持たせる
      - 本文はプレーンテキストなので **1.6 body_convert の対象外**。T.9 で空行区切りの
        段落分割 → paragraph ブロック化する
      - 同ディレクトリの他の回収物(きりか進ノ介さんの wiki 発掘 6 点、ライターマン「天女の末裔」、
        ヴァルキュリア外伝、城弾シアター版ディレイド 4/5 など)は**検証未完なので今回は収録しない**
      - **1.9 実測 (2026-08-30)**: 雨女は既存の目録エントリ
        `novel__200802__15232641__rainGirl.html` に本文 (`reposts/…txt`)・provenance
        (route=pixiv・作品 id 351985・取得日)・`published_in_bunko: true` を**足した**
        (新しい話は作っていない)。きらいなもの→ＧＷ は `repost__pixiv__272830` として
        `corpus=extern-repost`・`published_in_bunko: false` で新規収録。
        両方 `body_convert_exempt: true` (1.6 の対象外)。
      - **補足**: pixiv のタグ 5 語 (オリジナル/TSF/SS/女性人格化/チェス) が
        keywords に入るので、文庫の語彙に外部由来の語が 1 件分だけ混ざる
