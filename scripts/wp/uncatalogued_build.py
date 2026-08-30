#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""uncatalogued_build.py — 目録に載っていない収蔵物を拾う (進行台帳 タスク 1.8)。

設計 v1.4「最大掲載原則」— サルベージしたものはすべて掲載する。`novel/` 配下の本文
ファイルのうち、正規目録 (lib1–73) にも旧目録 (lib01–09) にも対応するエントリが無い
ものを `corpus=uncatalogued` として `catalog/episodes.jsonl` に足す。

メタデータの出所は次の順に試し、どれを使ったかを `metadata_source` に残す:

  1 lib-index    作家別五十音索引 (lib-index-aa〜etc / 1〜4) の題名+作者
  2 body-credit  本文中の「作：NAME」「作者：NAME」行
  3 body-title   本文の <title>(サイト共通の定型文は落とす)
  4 dir-author   同じ投稿ディレクトリに居る既収蔵話の作者 (1 ディレクトリ = 1 作者)
  5 filename     最後の手段

**掲載しない例外は 3 つだけ** (設計 v1.4) なので、ここで落とすのは「作品ではないもの」
だけに限る。落とした分は理由つきで `catalog/uncatalogued_excluded.jsonl` に全部残す:

  duplicate-of-catalogued  既収蔵ファイルと md5 が一致する別名コピー (alias として記録)
  title-page               シリーズタイトル/目次ページ (Work の説明文に転用するので話ではない)
  cgi-view                 CGI の並べ替えビューを捕獲したもの (index@… 形式)
  site-template            目録・投稿規定などサイト定型ページのコピー
  empty                    本文テキストも画像も無い

`catalog_build.py` の後・`terms_build.py` の前に実行すること (episodes.jsonl を
読んで自分の corpus 分を差し替えて書き戻す = 何度実行しても同じ結果になる)。
"""
import argparse
import collections
import hashlib
import html
import json
import os
import re
import sys
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import catalog_build as cb                                # noqa: E402
import authors_build as ab                                # noqa: E402

DEFAULT_ROOT = cb.DEFAULT_ROOT
CORPUS = 'uncatalogued'
RE_BODY_FILE = re.compile(r'\.(html?|txt)$', re.I)
RE_TITLE_TAG = re.compile(r'<title[^>]*>(.*?)</title>', re.S | re.I)
RE_NAV_NAME = re.compile(r'(?:^|/)(?:[a-z0-9_\-]*title[a-z0-9_\-]*|index\d*|contents\d*|menu)\.html?$',
                         re.I)
RE_CGI_VIEW = re.compile(r'@')
RE_SCRIPT = re.compile(r'(?is)<(script|style)[^>]*>.*?</\1>')
RE_TAG = re.compile(r'<[^>]+>')
RE_IMG = re.compile(r'<img\b', re.I)
# 「作：NAME」はタグ境界で終わる。タグを改行に落とした本文に対して行単位で当てる
# (空白ごと潰したテキストに当てると、本文の続きまで名前として食ってしまう)。
RE_CREDIT = re.compile(
    r'^\s*(?:作|著|作者|Author)\s*[：:・]\s*(\S[^\n]{0,29})\s*$', re.M)
# サイト共通の <title> 定型。これしか入っていないページから題名は取れない
TITLE_BOILER = re.compile(
    r'^(?:■?\s*)?(?:少年少女文庫|TS小説|ts novels|無題ドキュメント|untitled|新しいページ\s*\d*)'
    r'\s*(?:[・\-–—:：]\s*)?$', re.I)
SITE_TEMPLATE_NAMES = {'library.html', 'standard_format.html', 'boshuu.html', 'manual.html',
                       'feedback.html', 'entrance.html', 'introduction.html'}


def text_of_html(src):
    s = RE_SCRIPT.sub(' ', src)
    s = RE_TAG.sub(' ', s)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip()


def lines_of_html(src):
    """タグを改行に落としたテキスト。クレジット行の切り出しに使う。"""
    s = RE_SCRIPT.sub('\n', src)
    s = RE_TAG.sub('\n', s)
    s = html.unescape(s).replace('\u3000', ' ')
    return '\n'.join(ln.strip() for ln in s.split('\n'))


def read_text(path):
    try:
        with open(path, encoding='utf-8', errors='replace') as fh:
            return fh.read()
    except OSError:
        return ''


def md5_of(path):
    try:
        with open(path, 'rb') as fh:
            return hashlib.md5(fh.read()).hexdigest()
    except OSError:
        return None


def clean_title(raw):
    if not raw:
        return None
    t = re.sub(r'\s+', ' ', html.unescape(RE_TAG.sub('', raw))).strip()
    t = t.strip('■□◆●・-–— 　')
    t = re.sub(r'^少年少女文庫\s*[・\-–—:：]?\s*', '', t)
    t = re.sub(r'\s*[・\-–—:：]?\s*少年少女文庫\s*$', '', t)
    t = t.strip()
    if not t or TITLE_BOILER.match(t):
        return None
    return t


def scan_novel_files(root):
    out = []
    top = os.path.join(root, 'novel')
    for dirpath, _dirs, files in os.walk(top):
        for name in files:
            if RE_BODY_FILE.search(name):
                out.append(os.path.relpath(os.path.join(dirpath, name), root).replace(os.sep, '/'))
    return sorted(out)


def build(root, annex_base=cb.DEFAULT_ANNEX_BASE, use_provenance=True):
    cat = os.path.join(root, 'catalog')
    epath = os.path.join(cat, 'episodes.jsonl')
    records = [json.loads(line) for line in open(epath, encoding='utf-8')]
    keep = [r for r in records if r['corpus'] != CORPUS]
    catalogued = {r['source_path'] for r in keep}

    # 既収蔵ファイルの md5 と、投稿ディレクトリごとの作者
    cat_md5, dir_author = {}, collections.defaultdict(collections.Counter)
    for r in keep:
        if r['source_kind'] == 'local':
            full = os.path.join(root, r['source_path'])
            if os.path.exists(full):
                dg = md5_of(full)
                if dg:
                    cat_md5.setdefault(dg, r['source_path'])
            if r.get('author'):
                dir_author[os.path.dirname(r['source_path'])][r['author']] += 1

    index_rows, _ = ab.parse_index(root)
    index_of = {}
    for row in index_rows:
        p = re.sub(r'^(?:\./)+', '', (row['path'] or '').split('#')[0])
        if p and p not in index_of:
            index_of[p] = row

    prov_map = cb.build_provenance_map(root) if use_provenance else {}

    added, excluded = [], []
    for path in scan_novel_files(root):
        if path in catalogued:
            continue
        full = os.path.join(root, path)
        base = os.path.basename(path)
        src = read_text(full)
        body_text = text_of_html(src) if not path.lower().endswith('.txt') else src
        chars = len(re.sub(r'\s', '', body_text))
        has_img = bool(RE_IMG.search(src))

        def drop(reason, **extra):
            rec = {'source_path': path, 'reason': reason, 'text_chars': chars}
            rec.update(extra)
            excluded.append(rec)

        dg = md5_of(full)
        if dg and dg in cat_md5:
            drop('duplicate-of-catalogued', alias_of_path=cat_md5[dg])
            continue
        if RE_CGI_VIEW.search(base):
            drop('cgi-view')
            continue
        if base in SITE_TEMPLATE_NAMES:
            drop('site-template')
            continue
        if RE_NAV_NAME.search(path):
            drop('title-page')
            continue
        if chars == 0 and not has_img:
            drop('empty')
            continue

        row = index_of.get(path)
        title = author = None
        sources = []
        if row:
            title, author = row['title'], row['author']
            sources.append('lib-index')
        if not author:
            m = RE_CREDIT.search('\n'.join(lines_of_html(src).split('\n')[:80]))
            if m:
                author = ab.text_of(m.group(1))
                sources.append('body-credit')
        if not title:
            tm = RE_TITLE_TAG.search(src)
            title = clean_title(tm.group(1) if tm else None)
            if title:
                sources.append('body-title')
        if not author:
            # 投稿ディレクトリは 1 ディレクトリ = 1 作者。サブディレクトリに
            # 置かれた話は親を辿る (novel/200703/16205836/saila/… 等)
            d = os.path.dirname(path)
            names = None
            while d.startswith('novel/') and d.count('/') >= 1:
                if dir_author.get(d):
                    names = dir_author[d]
                    break
                d = os.path.dirname(d)
            if names and len(names) == 1:
                author = names.most_common(1)[0][0]
                sources.append('dir-author')
            elif names:
                author = names.most_common(1)[0][0]
                sources.append('dir-author-majority')
        if not title:
            title = re.sub(r'\.(html?|txt)$', '', base, flags=re.I)
            sources.append('filename')

        dm = re.match(r'novel/(\d{4})(\d{2})/', path)
        if dm:
            date, precision = '%s-%s-01' % (dm.group(1), dm.group(2)), 'directory-batch'
        else:
            date, precision = None, 'unknown'

        eid = cb.episode_id_of(path, None)
        yays_path = cb.YAYS_PREFIX + path
        rec = collections.OrderedDict()
        rec['episode_id'] = eid
        rec['corpus'] = CORPUS
        rec['source_path'] = path
        rec['source_anchor'] = None
        rec['source_kind'] = 'local'
        rec['source_exists'] = True
        rec['source_shared_by'] = 1
        rec['entry_type'] = 'image' if (chars < 40 and has_img) else 'html'
        rec['title'] = unicodedata.normalize('NFKC', title) if title else None
        rec['author'] = author
        rec['homepage'] = row['homepage'] if row else None
        rec['illustrator'] = []
        rec['illustrator_url'] = None
        rec['date'] = date
        rec['date_raw'] = None
        rec['date_precision'] = precision
        rec['weekday'] = None
        rec['size_kb'] = max(1, round(os.path.getsize(full) / 1024))
        rec['files_n'] = None
        rec['kansou_slug'] = None
        rec['kansou_annex_url'] = None
        rec['arasuji'] = None
        rec['comment'] = None
        rec['suisen'] = None
        rec['osusume'] = None
        rec['nav_links'] = []
        rec['inline_links'] = []
        rec['genre'] = []
        rec['genre_raw'] = []
        rec['type'] = []
        rec['type_raw'] = []
        rec['keywords'] = []
        rec['keywords_raw'] = []
        rec['zokusei'] = []
        rec['entry_role'] = 'work' if author else 'unattributed'
        rec['metadata_source'] = sources
        rec['text_chars'] = chars
        rec['catalog_ref'] = row['page'] if row else None
        rec['orig_url'] = cb.ORIG_BASE + cb.url_path(path)
        rec['annex_url'] = annex_base + cb.url_path(path)
        rec['annex_yays_url'] = (annex_base + cb.url_path(yays_path)
                                 if os.path.exists(os.path.join(root, yays_path)) else None)
        rec['provenance'] = cb.provenance_for(path, prov_map)
        added.append(rec)

    report = collections.OrderedDict([
        ('generated_by', 'scripts/wp/uncatalogued_build.py'),
        ('novel_body_files', len(scan_novel_files(root))),
        ('already_catalogued', sum(1 for p in scan_novel_files(root) if p in catalogued)),
        ('added', len(added)),
        ('excluded', len(excluded)),
        ('excluded_by_reason', dict(collections.Counter(x['reason'] for x in excluded))),
        ('metadata_sources', dict(collections.Counter(
            ','.join(r['metadata_source']) for r in added))),
        ('title_resolved', sum(1 for r in added if r['title'])),
        ('author_resolved', sum(1 for r in added if r['author'])),
        ('author_unresolved', [r['source_path'] for r in added if not r['author']][:50]),
        ('author_unresolved_count', sum(1 for r in added if not r['author'])),
        ('date_precision', dict(collections.Counter(r['date_precision'] for r in added))),
        ('text_chars_buckets', dict(collections.Counter(
            ('0' if r['text_chars'] == 0 else '<200' if r['text_chars'] < 200 else
             '<800' if r['text_chars'] < 800 else '<2000' if r['text_chars'] < 2000
             else '>=2000') for r in added))),
        ('provenance_covered', sum(1 for r in added if r['provenance'])),
        ('index_rows_parsed', len(index_rows)),
    ])
    return keep, added, excluded, report


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--root', default=DEFAULT_ROOT)
    ap.add_argument('--annex-base', default=cb.DEFAULT_ANNEX_BASE)
    ap.add_argument('--no-provenance', action='store_true')
    ap.add_argument('--check', action='store_true')
    args = ap.parse_args(argv)
    root = os.path.abspath(args.root)
    keep, added, excluded, report = build(root, args.annex_base, not args.no_provenance)

    total = report['novel_body_files']
    uncovered = total - report['already_catalogued'] - report['added'] - report['excluded']
    report['uncovered_after'] = uncovered
    print('novel/ 本文ファイル %d = 既収蔵 %d + 追加 %d + 意図的除外 %d (残 %d)' %
          (total, report['already_catalogued'], report['added'], report['excluded'], uncovered))
    print('除外の内訳: %s' % report['excluded_by_reason'])
    print('題名 %d / 作者 %d (未解決 %d)  出所: %s' %
          (report['title_resolved'], report['author_resolved'],
           report['author_unresolved_count'],
           dict(collections.Counter(s for r in added for s in r['metadata_source']))))
    ok = uncovered == 0
    print('  [%s] novel/ の本文で catalog に載らないものが 0 (意図的除外を除く)'
          % ('OK ' if ok else 'NG '))

    if not args.check:
        cat = os.path.join(root, 'catalog')
        with open(os.path.join(cat, 'episodes.jsonl'), 'w', encoding='utf-8') as fh:
            for r in keep + added:
                fh.write(json.dumps(r, ensure_ascii=False) + '\n')
        with open(os.path.join(cat, 'uncatalogued_excluded.jsonl'), 'w', encoding='utf-8') as fh:
            for r in excluded:
                fh.write(json.dumps(r, ensure_ascii=False) + '\n')
        os.makedirs(os.path.join(cat, 'reports'), exist_ok=True)
        with open(os.path.join(cat, 'reports', 'uncatalogued_build.json'),
                  'w', encoding='utf-8') as fh:
            json.dump(report, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
        print('wrote catalog/episodes.jsonl (+%d), catalog/uncatalogued_excluded.jsonl, '
              'catalog/reports/uncatalogued_build.json' % len(added))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
