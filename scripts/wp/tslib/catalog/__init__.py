# -*- coding: utf-8 -*-
"""tslib.catalog — 目録 (正規 lib1〜73 + 旧 lib01〜09) を episodes.jsonl のレコードにする (catalog_build.py の分割)。

  fragments.py   セルの切り出し・表示テキスト化・多値欄の分割・ナビリンク
  mailto.py      mailto の除去 (S10 のメールアドレスを DB に入れない決まり)
  keys.py        href → source_path / episode_id / URL (冪等キーの規則)
  provenance.py  git 履歴からの回収経路
  regular.py     正規目録 (corpus=honkan) のエントリ 1 件のパース
  legacy.py      旧目録 (corpus=legacy) の 2 形式のパース
  records.py     パース結果 → レコードの組み立て (両目録)
  selftest.py    受け入れ条件の検査
  report.py      レポートと要約の表示
"""
