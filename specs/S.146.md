# S.146 作品クラスタの手動修正: work_overrides.yml のスキーマ v2

- 種類: 仕様
- 状態: 有効
- 出典: docs/cluster-overrides-spec.md の冒頭と「catalog/work_overrides.yml のスキーマ (version 2)」
- 関連: T.24.1, S.43, S.107, S.218, S.238

## 作品クラスタの手動修正の仕様 (2026-10-26)

**位置づけ**: 作品クラスタを人手で直せるようにする決定 (S.43) の実装仕様。
根拠と実測は S.107、実装タスクは `TASKS.md` の T.24。
版違いの整理 (S.137〜S.145) はこの修正の**後**に行う (束ね直すと版グループが変わる)。
背景: `work_builder.py` の規則 5 (同一ディレクトリ・同一作者の連番ファイル名) と規則 6 (題名の芯の一致) が別作品を束ねる例が 71 作品あり、
`work_overrides.yml` は現状 `work_title` と `note` しか効かない。T.7.5.1 (S.176) で確認したのは slug だけで、クラスタ本体は未確認
(S.218。元の `catalog/review/works-risk-note.md`)。

### `catalog/work_overrides.yml` のスキーマ (version 2)

鍵は現行どおり `"<作者slug>|<機械が付けた作品名>"`。値に次を足す。

```yaml
works:
  "marie|キャリア・デイ　９７":
    confirmed: true                    # 人手で確認した (needs_review を落とす)
    note: 短編 4 本が toukou01 の衝突群と career* の語幹で束なっていた (レビュー 2026-10)
    work_title: キャリア・デイ         # 残るクラスタの題名 (現行どおり)
    split:                             # 列挙した話を新しい作品に分ける。残りは元のクラスタに残る
      - work_title: 未来から来た女
        episodes: [novel__toukou01.html+19971209, novel__200202__03004409__future.html]
      - work_title: 虹色のイヤリング
        episodes: [novel__toukou01.html+19971221, novel__200202__03004409__earring.html]
    detach: [novel__toukou02.html+19980130]      # 1 話ずつ単独の作品にする (題名は話の題名)
  "yays|奇術":
    merge_into: "yays|奇術"                       # このクラスタの全話を指定した鍵のクラスタへ併合 (作者が同じこと)
```

- `split` の `episodes` に無い話は元のクラスタに残る。`split` 先の slug は `slug_overrides.yml` に confirmed があればそれ、無ければ `work_builder` が候補を作る。
- `detach` は `split` の省略形 (1 話 1 作品、題名は話の題名)。
- `merge_into` の併合先の鍵は、併合先クラスタの**機械の題名**で書く (併合先に `work_title` があってもよい)。
- 実装 (2026-10-30) で足した検証と振る舞いは S.238 (鍵に当たるクラスタがちょうど 1 件・指示の話がそのクラスタの話・連鎖と自分自身への併合を誤りにする・split と detach でできた作品は needs_review を立てない)。
- 検証 (`work_builder.py --check`): 参照する `episode_id` が実在する / 同じ話が複数の指示に現れない / `merge_into` の先が実在し作者が同じ / 適用後も orphan 0 / `split` の `work_title` が非空 / `confirmed: true` のクラスタは `needs_review` false。
