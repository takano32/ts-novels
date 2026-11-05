# -*- coding: utf-8 -*-
"""tslib — catalog を作る Python (scripts/wp/*.py) の共有部分 (T45.2、specs/S243.md)。

scripts/wp/*.py はコマンドラインの入口 (引数の解釈・ファイルの読み書き・表示) だけを持ち、
変換・判定のロジックはこのパッケージの関数に置く。ロジックは引数と戻り値だけで試せる
(scripts/wp/tests/、`make test`)。

  paths.py      リポジトリのルートと catalog/ 配下の定位置
  fileio.py     JSON / JSONL / YAML / テキストの読み書き、md5、文字コードの推定
  checks.py     自己検査の結果の束 (OK / NG の行)
  htmltext.py   タグ除去・アンカー走査など HTML 断片の小道具
  mail.py       メールアドレスの検出と除去 (S10 の「DB に入れない」決まり)
  gitinfo.py    git ls-files / git log の呼び出し
  slugs.py      ローマ字 slug と catalog/slug_overrides.yml の往復
  kana_index.py 作家別五十音目録 (lib-index-*.html) の解釈 (authors と uncatalogued が使う)
  catalog/      目録 (lib*.html) のパース — catalog_build.py の分割
  その他        段ごとのロジック (terms / authors / works / bodies / payloads / …)
"""
