# S.191 verify の拡張 (T.9.4、元の 3.4)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 3.4
- 関連: T.9.4

- [x] 3.4 verify 拡張: 全話に書誌カード用メタ(初出日・orig_url・annex_url・provenance)が
      揃っているか被覆率で確認
      - **3.4 実測 (2026-08-30、コミット `8a70ca05`)**: `wp ts verify` に
        メタ被覆と本文 status 分布の照合が入り、**全項目が期待値と一致して verify OK**:
        **メタ `_ts_pub_date_raw` 2985=2985 / `_ts_orig_url` 3844=3844 /
        `_ts_annex_url` 3644=3644 / `_ts_provenance` 3646=3646**、
        **本文 status: converted 3642 / placeholder 198 / raw-fallback 2 / converted-text 2**
      - あわせて暫定テーマの修正 (`7673d311`) を検収: TT5 の single テンプレが
        ts_work で常に空になる「執筆者:」「カテゴリ:」のメタ行を出さなくなり、
        `/novel/omochibako-ameonna/` の先頭段落が「恵みの雨という言葉がある。…」に、
        通常の話 (`19204751-tsukinuke`) からも空メタ行が消えた(**残存 0 箇所**)。
        **ページの段落数が DB の `wp:paragraph` ブロック数と一致**することも確認
        (tsukinuke 446=446 / 雨女 8=8)
