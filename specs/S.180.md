# S.180 make catalog と QA レポート (T.7.7、元の 1.7)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.7
- 関連: T.7.7

- [x] 1.7 `make catalog` で 1.1〜1.6 を一発実行できる Makefile。QA レポートを `catalog/QA.md` に
      (件数・合格率・needs_review 数・provenance 被覆率・特殊エントリ内訳)
      - **1.7 実測 (2026-08-30 再計測)**: `make catalog` は 1.1/1.2 → **1.8 → 1.9** → 1.3 → 1.4
        → 1.5 → 1.6 → 1.7 の順で走る(**1.6 込みで 44 秒**。数え方: `time make catalog`)。
        `make check` は書き込みなしの自己検査(全段 OK・終了コード 0)、
        `make venv` は pykakasi / PyYAML / beautifulsoup4 / html5lib 入りの `.venv` を作る。
        **2 回連続実行で全出力(episodes/terms/authors/works/QA.md/slug_overrides)が
        バイト単位で同一**であることを確認済み。
      - QA レポートは `scripts/wp/qa_report.py` が `catalog/reports/*.json` から転記して
        `catalog/QA.md` を書く(二重帳簿にしないため、ここでは再計算しない)。
        **QA.md は生成物なので手で書き換えない — 直すのは `qa_report.py` のほう**。
        (2026-08-30 の修正: 読むレポート名が `convert_report.json` になっていて実ファイル
        `body_convert.json` と食い違い、1.6 完了後も QA.md の節 8 が「未実装・`bodies/` はまだ空」と
        出続けていた。あわせて corpus `honkan` の表示を「本館」から「正規目録 (lib1–73)」に
        直し、corpus 語彙の自己検査結果を QA.md の節 4 に出すようにした)
