# -*- coding: utf-8 -*-
"""scripts/wp/tslib の単体の試験 (T45.2.8)。

流し方: `make test` (= python -m unittest discover -s scripts/wp/tests -t scripts/wp)。
実データ (catalog/・novel/・~ts/) は読まない。小さな入力は試験の中か scripts/wp/tests/data/ に置く。
PyYAML・pykakasi・beautifulsoup4 が無い環境では、それを使う試験を skipUnless で飛ばす。
"""
