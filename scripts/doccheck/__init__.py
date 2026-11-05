# -*- coding: utf-8 -*-
"""doccheck — 文書 (docs / specs / tasks) の検査のロジック (仕様は specs/S219.md、置き場は specs/S243.md)。

入口は scripts/doc_check.py (引数の解釈・表示・終了コードだけ)。文書の検査は catalog の道具では
ないので、catalog の共有部分 (scripts/wp/ の下) は import しない。`python3 scripts/doc_check.py` では sys.path[0] が
scripts/ なので、このパッケージはそのまま `import doccheck` で届く。
試験は scripts/doccheck/tests/ (標準の unittest)、流し方は `make test`。

  rules.py      使わない語 (BANNED) と、番号・リンク・タスクの行を拾う正規表現
  inventory.py  検査する文書の一覧、実在する S と T の番号
  allow.py      許可リスト (scripts/doc_check_allow.tsv) の読み込みと、問題の集め方 (Problems)
  refs.py       各文書の行の検査 (番号の実在・リンク・使わない語) と denylist の id
  lists.py      SPECS.md・TASKS.md の一覧と tasks/Tn.md の書式
  run.py        全部を順に流し (run)、表示の行を組む (output_lines)
"""
