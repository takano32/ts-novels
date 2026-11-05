# -*- coding: utf-8 -*-
"""provenance.py — 回収経路 (provenance)。git 履歴からパスごとの初回追加コミットを引く。

※ T7.1 のタスクリスト (specs/S171.md) は provenance の出所を `collinfo.json` と書いているが、実物は
  CommonCrawl のコレクション一覧 (127 件) でありファイル単位の来歴情報を一切持たない。
  ファイルがどの経路で入ったかを実際に記録しているのは git 履歴 (回収コミットの件名と日付)
  なので、そちらを出所とする。差異は QA レポートにも記録する。
"""
import datetime

from tslib.gitinfo import added_files_log
from tslib.paths import ORIG_BASE
from tslib.catalog.keys import url_path

# git のコミット件名 → 回収経路。上から順に最初に当たったものを採る。
ROUTE_RULES = [
    ('megalodon', ('ウェブ魚拓', '魚拓'), 'ウェブ魚拓'),
    ('commoncrawl', ('CommonCrawl',), 'CommonCrawl'),
    ('narou', ('なろう',), '作者本人のなろうアカウント'),
    ('pixiv', ('pixiv',), '作者本人の pixiv 再掲'),
    ('wayback', ('Wayback',), 'Internet Archive Wayback Machine'),
    ('live-site', ("author's live", 'live site', 'live relocated', 'relocated site',
                   'author sites', 'author site', '作者ブログ', 'still-live'), '作者の現存サイトから直接回収'),
    ('yays-gapfill', ('yays copies', 'Gap-fill', 'gapfill'), '旧世代ツリー (~yays) の同名ファイルで補完'),
    ('alias', ('alias',), '同内容ファイルの別名複製'),
]
SNAPSHOT_ROUTES = ('wayback', 'megalodon', 'commoncrawl')


def classify_route(subject):
    """コミット件名 → (route の鍵, 表示名, 複数の経路語に当たったか)。"""
    hits = []
    for key, needles, label in ROUTE_RULES:
        if any(n.lower() in subject.lower() for n in needles):
            hits.append((key, label))
    if not hits:
        return 'other', 'その他 (コミット件名から分類できず)', False
    key, label = hits[0]
    return key, label, len(hits) > 1


def _date(epoch):
    return datetime.datetime.fromtimestamp(int(epoch), datetime.timezone.utc).strftime('%Y-%m-%d')


def parse_added_log(out):
    """`git log --diff-filter=A --name-only --format=__C__%H|%at|%s` の出力 → {path: 初回追加コミットの情報}。"""
    prov, commit = {}, None
    for line in out.split('\n'):
        if line.startswith('__C__'):
            h, ts, subj = line[5:].split('|', 2)
            key, label, mixed = classify_route(subj)
            commit = {'commit': h[:12], 'acquired_at': _date(ts), 'commit_subject': subj,
                      'route': key, 'route_label': label, 'route_mixed': mixed}
        elif line and commit is not None:
            prov.setdefault(line, commit)
    return prov


def build_provenance_map(root):
    """git 履歴からパスごとの初回追加コミットを引く。"""
    return parse_added_log(added_files_log(root))


def provenance_for(path, prov_map):
    rec = prov_map.get(path)
    if rec is None:
        return None
    out = dict(rec)
    out['method'] = 'git-history'
    if out['route'] in SNAPSHOT_ROUTES:
        # 個別キャプチャの timestamp は記録されていないため、照会 URL の形で残す
        out['snapshot_query_url'] = 'https://web.archive.org/web/2*/' + ORIG_BASE + url_path(path)
    return out
