# -*- coding: utf-8 -*-
"""uncatalogued.py — 目録に載っていない収蔵物を拾うロジック (T7.8、specs/S178.md)。

設計 (specs/S41.md)「最大掲載原則」— サルベージしたものはすべて掲載する。`novel/` 配下の本文
ファイルのうち、正規目録 (lib1–73) にも旧目録 (lib01–09) にも対応するエントリが無い
ものを `corpus=uncatalogued` として `catalog/episodes.jsonl` に足す。

メタデータの出所は次の順に試し、どれを使ったかを `metadata_source` に残す:

  1 lib-index    作家別五十音索引 (lib-index-aa〜etc / 1〜4) の題名+作者
  2 body-credit  本文中の「作：NAME」「作者：NAME」行
  3 body-title   本文の <title>(サイト共通の定型文は落とす)
  4 dir-author   同じ投稿ディレクトリに居る既収蔵話の作者 (1 ディレクトリ = 1 作者)
  5 filename     最後の手段

**掲載しない例外は 3 つだけ** (設計 S41) なので、ここで落とすのは「作品ではないもの」
だけに限る。落とした分は理由つきで `catalog/uncatalogued_excluded.jsonl` に全部残す:

  duplicate-of-catalogued  既収蔵ファイルと md5 が一致する別名コピー (alias として記録)
  title-page               シリーズタイトル/目次ページ (Work の説明文に転用するので話ではない)
  cgi-view                 CGI の並べ替えビューを捕獲したもの (index@… 形式)
  site-template            目録・投稿規定などサイト定型ページのコピー
  empty                    本文テキストも画像も無い
"""
import collections
import html
import os
import re
import unicodedata
from typing import NamedTuple

from tslib.fileio import read_text_or_empty, md5_of
from tslib.htmltext import RE_TAG, inline_text
from tslib.paths import ORIG_BASE, YAYS_PREFIX, DEFAULT_ANNEX_BASE
from tslib.catalog.keys import episode_id_of, url_path
from tslib.catalog.provenance import build_provenance_map, provenance_for
from tslib.kana_index import parse_index

CORPUS = 'uncatalogued'
RE_BODY_FILE = re.compile(r'\.(html?|txt)$', re.I)
RE_TITLE_TAG = re.compile(r'<title[^>]*>(.*?)</title>', re.S | re.I)
RE_NAV_NAME = re.compile(r'(?:^|/)(?:[a-z0-9_\-]*title[a-z0-9_\-]*|index\d*|contents\d*|menu)\.html?$',
                         re.I)
RE_CGI_VIEW = re.compile(r'@')
RE_SCRIPT = re.compile(r'(?is)<(script|style)[^>]*>.*?</\1>')
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
CREDIT_LINES = 80          # クレジット行を探す本文の先頭行数


class FileFacts(NamedTuple):
    """novel/ の 1 ファイルを読んで分かること (scan_file が作る)。"""
    path: str              # ルートからの相対パス
    src: str               # 中身 (読めなければ '')
    chars: int             # 本文の空白以外の字数 (.txt はそのまま、HTML はタグを落として数える)
    has_img: bool
    size_bytes: int
    yays_exists: bool      # ~yays/ の下に同じパスの写しがある
    digest: str            # md5


class Metadata(NamedTuple):
    """resolve_metadata の戻り値。タプルとして (title, author, sources) に展開できる。"""
    title: str
    author: str
    sources: list


def text_of_html(src):
    s = RE_SCRIPT.sub(' ', src)
    s = RE_TAG.sub(' ', s)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip()


def lines_of_html(src):
    """タグを改行に落としたテキスト。クレジット行の切り出しに使う。"""
    s = RE_SCRIPT.sub('\n', src)
    s = RE_TAG.sub('\n', s)
    s = html.unescape(s).replace('　', ' ')
    return '\n'.join(ln.strip() for ln in s.split('\n'))


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


def catalogued_index(keep, root):
    """既収蔵ファイルの md5 → source_path と、投稿ディレクトリごとの作者の数。"""
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
    return cat_md5, dir_author


def index_by_path(index_rows):
    """五十音目録の行を path (アンカーと ./ を落としたもの) で引く表。先に出た行を優先。"""
    index_of = {}
    for row in index_rows:
        p = re.sub(r'^(?:\./)+', '', (row['path'] or '').split('#')[0])
        if p and p not in index_of:
            index_of[p] = row
    return index_of


def scan_file(root, path):
    """ファイルを読み、字数・画像の有無・大きさ・~yays の写しの有無・md5 を FileFacts にする。"""
    full = os.path.join(root, path)
    src = read_text_or_empty(full)
    body_text = text_of_html(src) if not path.lower().endswith('.txt') else src
    return FileFacts(path=path, src=src, chars=len(re.sub(r'\s', '', body_text)),
                     has_img=bool(RE_IMG.search(src)), size_bytes=os.path.getsize(full),
                     yays_exists=os.path.exists(os.path.join(root, YAYS_PREFIX + path)),
                     digest=md5_of(full))


def exclusion_reason(facts, cat_md5):
    """作品ではないものの判定。落とすなら (理由, 追加の欄) を、載せるなら None を返す。"""
    path = facts.path
    base = os.path.basename(path)
    if facts.digest and facts.digest in cat_md5:
        return 'duplicate-of-catalogued', {'alias_of_path': cat_md5[facts.digest]}
    if RE_CGI_VIEW.search(base):
        return 'cgi-view', {}
    if base in SITE_TEMPLATE_NAMES:
        return 'site-template', {}
    if RE_NAV_NAME.search(path):
        return 'title-page', {}
    if facts.chars == 0 and not facts.has_img:
        return 'empty', {}
    return None


def dir_author_of(path, dir_author):
    """投稿ディレクトリは 1 ディレクトリ = 1 作者。サブディレクトリに
    置かれた話は親を辿る (novel/200703/16205836/saila/… 等)。戻り値 (author, 出所) か (None, None)。"""
    d = os.path.dirname(path)
    names = None
    while d.startswith('novel/') and d.count('/') >= 1:
        if dir_author.get(d):
            names = dir_author[d]
            break
        d = os.path.dirname(d)
    if names and len(names) == 1:
        return names.most_common(1)[0][0], 'dir-author'
    if names:
        return names.most_common(1)[0][0], 'dir-author-majority'
    return None, None


def resolve_metadata(facts, row, dir_author):
    """題名と作者を出所の順に解決する。戻り値 Metadata (title, author, sources)。"""
    path, src = facts.path, facts.src
    title = author = None
    sources = []
    if row:
        title, author = row['title'], row['author']
        sources.append('lib-index')
    if not author:
        m = RE_CREDIT.search('\n'.join(lines_of_html(src).split('\n')[:CREDIT_LINES]))
        if m:
            author = inline_text(m.group(1))
            sources.append('body-credit')
    if not title:
        tm = RE_TITLE_TAG.search(src)
        title = clean_title(tm.group(1) if tm else None)
        if title:
            sources.append('body-title')
    if not author:
        author, how = dir_author_of(path, dir_author)
        if how:
            sources.append(how)
    if not title:
        title = re.sub(r'\.(html?|txt)$', '', os.path.basename(path), flags=re.I)
        sources.append('filename')
    return Metadata(title, author, sources)


def date_of_path(path):
    """novel/YYYYMM/ の投稿月を日付にする。戻り値 (date, precision)。"""
    dm = re.match(r'novel/(\d{4})(\d{2})/', path)
    if dm:
        return '%s-%s-01' % (dm.group(1), dm.group(2)), 'directory-batch'
    return None, 'unknown'


def make_record(facts, meta, row, annex_base, prov_map):
    """目録外の 1 話のレコード。鍵の並びは rec[...] の代入の順 (束から機械的に作らない)。"""
    path, chars, has_img = facts.path, facts.chars, facts.has_img
    title, author, sources = meta
    date, precision = date_of_path(path)
    yays_path = YAYS_PREFIX + path
    rec = collections.OrderedDict()
    rec['episode_id'] = episode_id_of(path, None)
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
    rec['size_kb'] = max(1, round(facts.size_bytes / 1024))
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
    rec['orig_url'] = ORIG_BASE + url_path(path)
    rec['annex_url'] = annex_base + url_path(path)
    rec['annex_yays_url'] = annex_base + url_path(yays_path) if facts.yays_exists else None
    rec['provenance'] = provenance_for(path, prov_map)
    return rec


def report_of(novel_files, catalogued, added, excluded, index_rows):
    """レポート。最後の uncovered_after は、novel/ の本文のうち既収蔵・追加・意図的除外のどれでもない数
    (0 であることが受け入れ条件)。"""
    already = sum(1 for p in novel_files if p in catalogued)
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/uncatalogued_build.py'),
        ('novel_body_files', len(novel_files)),
        ('already_catalogued', already),
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
        ('uncovered_after', len(novel_files) - already - len(added) - len(excluded)),
    ])


def build(root, records, annex_base=DEFAULT_ANNEX_BASE, use_provenance=True):
    """episodes.jsonl のレコードを受け取り、novel/ を走査して目録外の分を足す。
    戻り値 (keep, added, excluded, report)。keep は自分の corpus 以外のレコード。"""
    keep = [r for r in records if r['corpus'] != CORPUS]
    catalogued = {r['source_path'] for r in keep}
    cat_md5, dir_author = catalogued_index(keep, root)
    index_rows, _ = parse_index(root)
    index_of = index_by_path(index_rows)
    prov_map = build_provenance_map(root) if use_provenance else {}
    novel_files = scan_novel_files(root)

    added, excluded = [], []
    for path in novel_files:
        if path in catalogued:
            continue
        facts = scan_file(root, path)
        dropped = exclusion_reason(facts, cat_md5)
        if dropped:
            reason, extra = dropped
            rec = {'source_path': path, 'reason': reason, 'text_chars': facts.chars}
            rec.update(extra)
            excluded.append(rec)
            continue
        row = index_of.get(path)
        meta = resolve_metadata(facts, row, dir_author)
        added.append(make_record(facts, meta, row, annex_base, prov_map))

    return keep, added, excluded, report_of(novel_files, catalogued, added, excluded, index_rows)
