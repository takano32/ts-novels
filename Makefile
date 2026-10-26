# 少年少女文庫 → WordPress ライブラリ: catalog の再生成
#
#   make catalog   … 単一真実源 catalog/ を素の状態から作り直す (T.7、specs/S.171.md〜S.180.md)
#   make check     … ファイルを書かずに各段の自己検査と文書の検査 (scripts/doc_check.py) だけ走らせる
#   make qa        … catalog/reports/*.json から catalog/QA.md を書き直す
#   make venv      … pykakasi / PyYAML / beautifulsoup4 / html5lib を入れた .venv を作る
#                    (開発機のみ。git 管理外)
#   make verify    … bodies/*.md を html5lib で検算する (要 make venv)
#
# 段の順番には意味がある。episodes.jsonl は catalog_build が新規に書き、
# uncatalogued_build と repost_build が**自分の corpus 分だけ差し替えて**追記し、
# episode_overrides が最後に人手の上書き (原本の誤植の訂正) を当てる。
# terms/authors/works はその出来上がった episodes.jsonl を読む。
#
# T.7.6 の body_convert (本文 Markdown 化) は実装済み。下の ifneq は「スクリプトが無い環境でも
# catalog は作れる」ための保険で、通常は必ず走る。出力先 bodies/ は .gitignore 済み
# (git 管理外の派生物。実測 3,642 ファイル)。所要は body_convert 込みで約 44 秒。

VENV    ?= .venv
PYTHON  ?= $(if $(wildcard $(VENV)/bin/python),$(VENV)/bin/python,python3)
WP      := scripts/wp

.PHONY: all catalog check qa venv verify clean-catalog

all: catalog

# --- catalog 一式 (T.7)
catalog:
	$(PYTHON) $(WP)/catalog_build.py        # T.7.1 正規目録 lib1-73 + T.7.2 旧目録 lib01-09
	$(PYTHON) $(WP)/uncatalogued_build.py   # T.7.8 目録に載っていない novel/ 配下の本文
	$(PYTHON) $(WP)/repost_build.py         # T.7.8 作者本人の再掲 (検証済み 2 件)
	$(PYTHON) $(WP)/episode_overrides.py    # 話単位の人手上書き (原本 lib*.html の誤植)
	$(PYTHON) $(WP)/terms_build.py          # T.7.3 分類語彙 (genre/type/keyword/world/corpus)
	$(PYTHON) $(WP)/authors_build.py        # T.7.4 作者の同定
	$(PYTHON) $(WP)/work_builder.py         # T.7.5 Episode -> Work クラスタリング
ifneq ($(wildcard $(WP)/body_convert.py),)
	$(PYTHON) $(WP)/body_convert.py         # T.7.6 本文 Markdown 化 (無損失証明つき)
else
	@echo '--- body_convert.py (T.7.6) は未実装のためスキップ (bodies/ は空のまま)'
endif
	$(PYTHON) $(WP)/qa_report.py            # T.7.7 catalog/QA.md

# --- 書き込みなしの検査 (CI 向け)
check:
	$(PYTHON) $(WP)/catalog_build.py --check
	$(PYTHON) $(WP)/uncatalogued_build.py --check
	$(PYTHON) $(WP)/repost_build.py --check
	$(PYTHON) $(WP)/episode_overrides.py --check
	$(PYTHON) $(WP)/terms_build.py --check
	$(PYTHON) $(WP)/authors_build.py --check
	$(PYTHON) $(WP)/work_builder.py --check
	$(PYTHON) scripts/doc_check.py            # 文書 (docs / specs / tasks) の番号・リンク・一覧・使わない語 (specs/S.219.md)

qa:
	$(PYTHON) $(WP)/qa_report.py

venv:
	python3 -m venv $(VENV)
	$(VENV)/bin/pip install -q --upgrade pip
	$(VENV)/bin/pip install -q PyYAML==6.0.3 pykakasi==2.3.0 jaconv==0.5.0 beautifulsoup4==4.15.0 html5lib==1.1
	@# pykakasi の版が変わると作品・作者の slug (= 恒久 URL) が変わるので版を固定する
	@echo 'できた。以後 make catalog は $(VENV)/bin/python を使う'

# --- bodies/*.md の無損失性を html5lib (第二実装) で検算する
verify:
	$(PYTHON) $(WP)/verify_bodies_html5.py

# 生成物だけ消す (原本には触らない)
clean-catalog:
	rm -f catalog/episodes.jsonl catalog/terms.json catalog/authors.json \
	      catalog/works.jsonl catalog/uncatalogued_excluded.jsonl catalog/QA.md
	rm -rf catalog/reports
	@echo '正規化マップ (catalog/*_map.yml) と人手確定 (slug_overrides.yml /'
	@echo 'work_overrides.yml) は消していない。消すと人手の確認結果 (T.7.5.1) が失われる'
