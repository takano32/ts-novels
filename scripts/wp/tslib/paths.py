# -*- coding: utf-8 -*-
"""paths.py — リポジトリのルートと、生成物・入力の定位置。

各スクリプトが `--root` で別のツリーを指せるよう、ルートは引数で受けて組み立てる。
REPO_ROOT はこのファイルの置き場 (scripts/wp/tslib/) から 4 つ上。
"""
import os

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

ORIG_BASE = 'http://ts.novels.jp/'
# 原本アーカイブ = 現行 GitHub Pages ミラー (設計 S35 で恒久併存が決定)。用語は specs/S14.md〜S19.md を優先する。
# リポジトリは takano32/ts-novels、カスタムドメイン (CNAME) なし。
DEFAULT_ANNEX_BASE = 'https://takano32.github.io/ts-novels/'
YAYS_PREFIX = '~yays/library/'          # 2000-02 年の旧世代ツリー (初出版)
KANSOU_DIR = os.path.join('~ts', 'kansou')


def catalog_dir(root):
    return os.path.join(root, 'catalog')


def reports_dir(root):
    return os.path.join(root, 'catalog', 'reports')


def report_path(root, name):
    """catalog/reports/<name>。name は 'catalog_build.json' のようにファイル名で渡す。"""
    return os.path.join(root, 'catalog', 'reports', name)


def episodes_path(root):
    return os.path.join(root, 'catalog', 'episodes.jsonl')


def works_path(root):
    return os.path.join(root, 'catalog', 'works.jsonl')


def authors_path(root):
    return os.path.join(root, 'catalog', 'authors.json')


def terms_path(root):
    return os.path.join(root, 'catalog', 'terms.json')


def slug_overrides_path(root):
    return os.path.join(root, 'catalog', 'slug_overrides.yml')


def work_overrides_path(root):
    return os.path.join(root, 'catalog', 'work_overrides.yml')


def convert_report_path(root):
    return os.path.join(root, 'catalog', 'convert_report.jsonl')


def denylist_path(root):
    return os.path.join(root, 'takedown', 'denylist.yml')


def bodies_dir(root):
    return os.path.join(root, 'bodies')


def payloads_dir(root):
    return os.path.join(root, 'payloads')


def payloads_docs_dir(root):
    return os.path.join(root, 'payloads-docs')


def kansou_board_path(root, slug):
    """感想板 1 枚のファイル (~ts/kansou/bbs@log_<slug>.cgi)。"""
    return os.path.join(root, KANSOU_DIR, 'bbs@log_%s.cgi' % slug)


def kansou_annex_url(annex_base, slug):
    """感想板の原本アーカイブ上の URL。slug が無ければ None。"""
    return annex_base + '~ts/kansou/bbs@log_%s.cgi' % slug if slug else None


def with_trailing_slash(url):
    return url if url.endswith('/') else url + '/'
