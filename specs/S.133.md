# S.133 取り下げの原本アーカイブとの連携

- 種類: 仕様
- 状態: 有効
- 出典: docs/takedown-form-spec.md の「10. 別館 (GitHub Pages) との連携」
- 関連: T.26.7, S.44

## 10. 原本アーカイブ (GitHub Pages) との連携 (任意。T.35.2 と同時)

- 公開 REST `GET /wp-json/ts/v1/withdrawn` → `{"generated": "<ISO 8601>", "source_paths": ["novel/200202/03004409/pink.html", …]}`。依頼者情報は含めない。`withdrawn` の全投稿の `_ts_source_path` (子孫含む) を返す。
- `deploy-pages.yml` のビルド注入 (`annex_inject.py`) がこれを取得し、`denylist.yml` の除外と合流させる → 人手なしで 10 日後に原本アーカイブからも消える。取得に失敗したときは denylist.yml だけで続行し、ログに残す。
