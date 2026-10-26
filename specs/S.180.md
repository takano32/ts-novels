# S.180 make catalog と QA レポート (T.7.7)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 1.7
- 関連: T.7.7

- [x] T.7.7 `make catalog` で T.7.1〜T.7.6 を一発実行できる Makefile。QA レポートを `catalog/QA.md` に
      (件数・合格率・needs_review 数・provenance 被覆率・特殊エントリ内訳)
      - **T.7.7 の実測 (2026-08-30 再計測)**: `make catalog` は T.7.1/T.7.2 → **T.7.8 の目録外収蔵 → T.7.8 の pixiv 再掲** → T.7.3 → T.7.4
        → T.7.5 → T.7.6 → T.7.7 の順で走る(**本文変換 (T.7.6) 込みで 44 秒**。数え方: `time make catalog`)。
        `make check` は書き込みなしの自己検査(全段 OK・終了コード 0)、
        `make venv` は pykakasi / PyYAML / beautifulsoup4 / html5lib 入りの `.venv` を作る。
        **2 回連続実行で全出力(episodes/terms/authors/works/QA.md/slug_overrides)が
        バイト単位で同一**であることを確認済み。
      - QA レポートは `scripts/wp/qa_report.py` が `catalog/reports/*.json` から転記して
        `catalog/QA.md` を書く(二重帳簿にしないため、ここでは再計算しない)。
        **QA.md は生成物なので手で書き換えない — 直すのは `qa_report.py` のほう**。
        (2026-08-30 の修正: 読むレポート名が `convert_report.json` になっていて実ファイル
        `body_convert.json` と食い違い、T.7.6 の完了後も QA.md の「本文 Markdown 変換」の節が「未実装・`bodies/` はまだ空」と
        出続けていた。あわせて corpus `honkan` の表示を「本館」から「正規目録 (lib1–73)」に
        直し、corpus 語彙の自己検査結果を QA.md の「分類語彙」の節に出すようにした)
