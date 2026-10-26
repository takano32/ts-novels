# S.107 シリーズ判定のバグ (別作品の混入) と手動修正

- 種類: 設計
- 状態: 有効
- 出典: docs/owner-feedback-2026-10.md の「7. シリーズ判定のバグ (別作品の混入) と手動修正」
- 関連: T.24, S.146〜S.154, S.218

## 7. シリーズ判定のバグ (別作品の混入) と手動修正

**現状 (実測)**:

| 指標 | 値 |
|---|---|
| 連載扱いの作品 | 519 (1,451 作品中) |
| 構成話の題名の芯 (巻号を落としたもの) が 2 種以上 | 258 |
| うち芯どうしに共通接頭辞 (2 文字以上) が無い = **別題が混在** | **71** (根拠が弱い規則だけで束ねた **39** / 強い根拠あり 32) |
| `needs_review` (`catalog/reports/work_builder.json`) | 331 (weak-evidence 271 / multi-directory 38 / large-cluster 22) |
| `work_overrides.yml` の雛形 | 368 件、**`confirmed: true` は 0 件** |

誤混入の例 (弱い根拠のみ): `marie-kyaria-dei-97` (10 話 = キャリア・デイ 97/2023 + 未来から来た女 + 虹色のイヤリング + イチゴのお弁当箱。`toukou01.html` の同一ファイル衝突群 `+日付` と `career*` の語幹) / `yays-yuushaouga-…` (勇者王 + セラムン + エヴァの 3 パロディ = `aniparo` 連番) / `yays-awasekagaminokoibitotachi` (`story2` / `story3` = 別作 2 本) / `million-tenshinooshigoto` (14 話に別題 4) / `izumi_miki-gunshuunonakadenokodoku` (`all_alone` 連番に別題 3) / `johdan-hanayochanshiriizubangaihen-hantaashiriizugaiden-hantaagaisuto` (華代ちゃん + ハンター)。強い根拠ありでも疑わしい例: `marie-haatonopendanto` (series.html が ピンクのランドセル と ハートのペンダント を同じ行に列挙)。

原因: 規則 5 **filename-stem** (`work_builder.py:237-248`: 同一ディレクトリ・同一作者で末尾連番を外した語幹が一致) が「作者が同じ接頭辞で別作を並べた」場合に効きすぎ、規則 6 **title-prefix** (`:250-258`) が版違いを束ねる。**T.7.5.1 (S.176) で裁定したのは作者 slug 40 件と同名 2 組だけ**で、クラスタ本体は S.218 (元の `catalog/review/works-risk-note.md`) が記録するとおり「自動生成のまま採用」。
**手動修正の機構は不足**: `work_overrides.yml` で効くのは `work_title` と `note` だけ (`:368-371`) → **分割・併合・切り離しはできない**。付随バグ: 題名の芯の切り出しが「第１回」「その」「Lesson」「EPISODE」を落とさず、作品名が「イマジン 第」「本当の自分 第」「〜その」で終わる (10 作品、S.218 (元の `catalog/review/works-risk-note.md`) の 1-F)。

**仕様 (決定案)**:

1. `work_overrides.yml` のスキーマ拡張 (鍵は現行どおり `<作者slug>|<機械の題名>`): `split: [{work_title, episodes: [episode_id…]}]` / `detach: [episode_id…]` (単独作品化) / `merge_into: "<鍵>"` / `confirmed: true`。`work_builder` は union-find の後に適用し、`evidence` に `override` を記録。slug は `slug_overrides.yml` の confirmed を保つ。
2. 題名の芯の正規表現 (`:59-69`) に 回・Lesson・EPISODE・stage・その を追加し、末尾が助数詞で終わる題名 (10 件) を直す。
3. レビュー資料 `catalog/review/clusters-2026-10.md` を生成 (71 作品。弱根拠 39 を先頭に、各話の題名・ファイル名・根拠を並べる) → 所有者/運営委員が ○× と正しい束ね方を書く → セッションが overrides に転記 → `make catalog` → 再投入。
4. **URL への影響**: 束ね直した作品・話の URL が変わる (S.218 (元の `catalog/review/works-risk-note.md`) の 0)。公開中なので旧 URL → 新 URL の 301 を `.htaccess` の ts-library ブロックに足す (生成は `export-pathmap` の差分から)。

**未決**: レビューを所有者が行うか、セッションが一次案を作って所有者が承認する形か (推奨: 後者)。
**規模**: builder 改修 1 日 / レビュー 71 件 (1〜2 時間) / 再投入 + 301。**S.106 より先**。
**実装仕様**: S.146〜S.154 (`work_overrides.yml` v2 の形式・適用順・題名の芯の規則・レビュー資料・`redirects.yml`・再投入方式)。
