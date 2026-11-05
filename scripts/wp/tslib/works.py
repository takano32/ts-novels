# -*- coding: utf-8 -*-
"""works.py — Episode を Work にまとめるロジック (T7.5、specs/S175.md)。

**クラスタリングの根拠を強い順に積む** (どの規則で繋がったかは works.jsonl の
`evidence` に全部残す。弱い根拠しか無いクラスタは needs_review が立つ):

  1 series-title-nav  推薦文の【シリーズタイトルはこちら】が同じページを指す (最強)
  2 series-html       series.html の 1 行に列挙された各話リンク
  3 title-page        タイトルページ自身が目録エントリとして存在する場合の吸収
  4 episode-nav       【第N話はこちら】等が指す兄弟話
  5 filename-stem     同一ディレクトリ・同一作者で、末尾の連番を外したファイル名が同じ
  6 title-prefix      同一作者で題名の共通接頭辞が 4 文字以上 (最弱)

**Work は必ず 1 作者に閉じる**。共有世界 (華代ちゃん等) のタイトルページは 69 名の話を
1 つに束ねてしまうので、作者をまたぐ結合は行わない (シリーズとしての同一性は
ts_world タクソノミーが担う)。

入出力は scripts/wp/work_builder.py。人手の上書き (work_overrides.yml) の決まりは specs/S146.md・S147.md。
"""
import collections
import dataclasses
import os
import re
import unicodedata

from tslib import slugs as slugmod
from tslib.fileio import read_text, read_yaml, md5_of
from tslib.htmltext import RE_COMMENT, RE_TR, RE_ANCHOR, spaced_text, norm_path

RE_CELL = re.compile(r'<T[DH][^>]*>(.*?)</T[DH]>', re.S | re.I)   # <TD> と <TH> (htmltext.RE_TD は <TD> だけ)
RE_TITLE_PAGE = re.compile(r'(?:^|/)(?:[a-z0-9_\-]*title[a-z0-9_\-]*|index|contents)\.html?$', re.I)
RE_SERIES_NAV = re.compile(r'シリーズタイトル')
RE_EPISODE_NAV = re.compile(r'第[0-9０-９一二三四五六七八九十百]+[話章編]|前編|後編|中編|上巻|下巻')
# 題名から「第N話」「(前編)」などの巻号表記を落として作品名の芯を出す
# 巻号の数字 (漢数字と大字を含む。「第壱話」「其の弐」)
_NUM = r'[0-9０-９〇零一二三四五六七八九十百壱弐参肆伍陸漆捌玖拾]'
# 回 / Lesson N / EPISODE N / stage / その N / 其の N / № N (NFKC で No) / No. N (specs/S148.md)。
# 英語の語は直前が英字でないときだけ (Piano 5 の no を巻号と取らない)。stage は数字つきに限る
# (原本の形は「Experience 1st stage」。「Back to stage」は巻号ではない)
_EXTRA = (r'|(?<![A-Za-z])(?:lesson|episode)\s*#?\s*[0-9]+(?:_[0-9]+)*'
          r'|(?<![A-Za-z])stage\s*[0-9]+|[0-9]+(?:st|nd|rd|th)\s*stage'
          r'|(?:その|其の)\s*' + _NUM + r'+'
          r'|(?<![A-Za-z])no\.?\s*[0-9]+')
RE_TITLE_TRIM = re.compile(
    r'(?:\s|　)*(?:[（(【「『]?\s*(?:第\s*' + _NUM + r'+\s*[話章巻部編幕夜回]'
    r'|[0-9０-９]{1,3}|前編|後編|中編|完結編|最終話|序章|終章|外伝|番外編|特別編|Ｐａｒｔ\s*[0-9０-９]+'
    r'|part\s*[0-9]+|#[0-9]+|vol\.?\s*[0-9]+' + _EXTRA + r')\s*[）)】」』]?)+\s*$', re.I)
STEM_TRIM = re.compile(r'[-_]?(?:[0-9]{1,3})(?:[-_.][0-9]{1,3})?$')
# 巻号表記が題名の途中に来る形 (「女神仮面ミィミル 第一話 女神テオドラの帰還」) は
# そこで切ると作品名の芯が残る
RE_TITLE_CUT = re.compile(
    r'\s*(?:[（(【「『]\s*)?(?:第\s*' + _NUM + r'+\s*[話章巻部編幕夜回]'
    r'|前編|後編|中編|完結編|最終話|序章|終章|外伝|番外編|特別編'
    r'|Ｐａｒｔ\s*[0-9０-９]+|part\s*[0-9]+|#\s*[0-9]+|vol\.?\s*[0-9]+' + _EXTRA + r')', re.I)

STRONG = {'series-title-nav', 'series-html', 'title-page', 'episode-nav'}

class Union:
    """union-find。どの規則で繋いだかを why に残す。"""

    def __init__(self):
        self.parent = {}
        self.why = collections.defaultdict(set)

    def add(self, x):
        self.parent.setdefault(x, x)

    def find(self, x):
        self.add(x)
        while self.parent[x] != x:
            self.parent[x] = self.parent[self.parent[x]]
            x = self.parent[x]
        return x

    def union(self, a, b, rule):
        ra, rb = self.find(a), self.find(b)
        self.why[a].add(rule)
        self.why[b].add(rule)
        if ra != rb:
            self.parent[rb] = ra


def trim_title(title):
    """題名から巻号表記を落として作品名の芯を出す。"""
    t = unicodedata.normalize('NFKC', title or '').strip()
    cut = RE_TITLE_CUT.search(t)
    if cut and len(t[:cut.start()].strip(' 　-–—~〜「」『』()（）')) >= 2:
        t = t[:cut.start()]
    prev = None
    while prev != t:
        prev = t
        t = RE_TITLE_TRIM.sub('', t).strip(' 　-–—~〜「」『』()（）')
    return t or unicodedata.normalize('NFKC', title or '').strip()


def stem_of(path):
    base = os.path.basename(path)
    base = re.sub(r'\.html?$', '', base, flags=re.I)
    return STEM_TRIM.sub('', base) or base


def common_prefix(strings):
    if not strings:
        return ''
    a, b = min(strings), max(strings)
    i = 0
    while i < min(len(a), len(b)) and a[i] == b[i]:
        i += 1
    return a[:i]


# --------------------------------------------------------------------------- シード

def parse_series_rows(src):
    """series.html の全文 → [{author, work_title, title_page, episode_paths[]}] (コメント行は除く)。"""
    src = RE_COMMENT.sub('', src)
    out = []
    for row in RE_TR.findall(src):
        cells = RE_CELL.findall(row)
        if len(cells) < 2:
            continue
        author = spaced_text(cells[0])
        links = RE_ANCHOR.findall(cells[1])
        if not author or author == '作者名':
            continue
        title_page, eps = None, []
        for g in links:
            href = norm_path(g[0] or g[1] or g[2])
            label = spaced_text(g[3]) or ''
            if not href:
                continue
            if RE_SERIES_NAV.search(label):
                title_page = href
            else:
                eps.append(href)
        work_title = spaced_text(RE_ANCHOR.sub(' ', cells[1]))
        out.append({'author': author, 'work_title': work_title,
                    'title_page': title_page, 'episode_paths': eps})
    return out


def parse_series_html(root):
    """series.html (完結シリーズ一覧) を読む。無ければ []。"""
    path = os.path.join(root, 'series.html')
    if not os.path.exists(path):
        return []
    return parse_series_rows(read_text(path))


def title_pages_on_disk(root):
    out = set()
    for base in ('novel', os.path.join('~yays', 'library', 'novel')):
        top = os.path.join(root, base)
        if not os.path.isdir(top):
            continue
        for dirpath, _dirs, files in os.walk(top):
            for name in files:
                rel = os.path.relpath(os.path.join(dirpath, name), root)
                if RE_TITLE_PAGE.search(rel):
                    out.add(rel)
    return out


def authors_index(authors):
    """authors.json の authors → (episode_id → 作者 slug, episode_id → 表示名)。"""
    author_of, author_name = {}, {}
    for a in authors:
        for eid in a['episodes']:
            author_of[eid] = a['slug']
            author_name[eid] = a['display_name']
    return author_of, author_name


# --------------------------------------------------------------------------- クラスタリング

def cluster(records, author_of, series_rows):
    """6 段の根拠で話を繋ぐ。戻り値は Union (find で代表、why で根拠)。"""
    u = Union()
    for rec in records:
        u.add(rec['episode_id'])
    by_path = collections.defaultdict(list)
    for rec in records:
        by_path[rec['source_path']].append(rec)

    def link(a, b, rule):
        if a is b:
            return
        if author_of.get(a['episode_id']) != author_of.get(b['episode_id']):
            return                                  # Work は 1 作者に閉じる
        u.union(a['episode_id'], b['episode_id'], rule)

    # 1 series-title-nav — 同じシリーズタイトルページを指す話どうし
    nav_groups = collections.defaultdict(list)
    for rec in records:
        for nav in rec.get('nav_links') or []:
            if RE_SERIES_NAV.search(nav.get('label') or ''):
                tgt = norm_path(nav['href'])
                if tgt:
                    nav_groups[tgt].append(rec)
    for tgt, members in nav_groups.items():
        for m in members[1:]:
            link(members[0], m, 'series-title-nav')
        # 3 title-page — そのタイトルページ自体が目録エントリなら吸収する
        for host in by_path.get(tgt, []):
            link(members[0], host, 'title-page')

    # 2 series-html — 1 行に列挙された各話リンク
    for row in series_rows:
        paths = list(row['episode_paths'])
        if row['title_page']:
            paths.append(row['title_page'])
        members = [r for p in paths for r in by_path.get(p, [])]
        for m in members[1:]:
            link(members[0], m, 'series-html')

    # 4 episode-nav — 【第N話はこちら】が指す兄弟話
    for rec in records:
        for nav in rec.get('nav_links') or []:
            label = nav.get('label') or ''
            if RE_SERIES_NAV.search(label) or not RE_EPISODE_NAV.search(label):
                continue
            tgt = norm_path(nav['href'])
            for other in by_path.get(tgt or '', []):
                link(rec, other, 'episode-nav')

    # 5 filename-stem — 同一ディレクトリ・同一作者の連番ファイル
    stem_groups = collections.defaultdict(list)
    for rec in records:
        if rec['source_kind'] != 'local':
            continue
        d = os.path.dirname(rec['source_path'])
        stem = stem_of(rec['source_path'])
        if len(stem) >= 3:
            stem_groups[(author_of.get(rec['episode_id']), d, stem)].append(rec)
    for members in stem_groups.values():
        for m in members[1:]:
            link(members[0], m, 'filename-stem')

    # 6 title-prefix — 同一作者・巻号を落とした題名が一致
    title_groups = collections.defaultdict(list)
    for rec in records:
        core = trim_title(rec.get('title'))
        if len(core) >= 4:
            title_groups[(author_of.get(rec['episode_id']), core)].append(rec)
    for members in title_groups.values():
        for m in members[1:]:
            link(members[0], m, 'title-prefix')
    return u


# --------------------------------------------------------------------------- 組み立ての小道具

def episode_order_key(rec):
    m = re.search(r'(\d+)(?:\.html?)?$', re.sub(r'\.html?$', '', rec['source_path']))
    num = int(m.group(1)) if m else 0
    return (rec.get('date') or '', num, rec['source_path'])


def group_author(members, author_of):
    """クラスタの作者 slug。作者に紐づかない話の受け皿は 2 つ。

    目録外収蔵で作者が特定できなかったもの (entry_role=unattributed) は「作者不詳」だが、
    シリーズ目次ページ (entry_role=series-index。目録の作者欄が「シェアワールド」だった
    姫神奇譚の目次) は**作者不詳ではなく目次**なので別の受け皿に分ける。
    """
    aslug = author_of.get(members[0]['episode_id'])
    if not aslug:
        aslug = ('series-index'
                 if {m.get('entry_role') for m in members} == {'series-index'}
                 else 'unattributed')
    return aslug


def machine_title(members):
    """機械が付ける作品名 (members は episode_order_key 順)。
    タイトルページの題名 > 巻号を落とした題名の一致 > 共通接頭辞。"""
    cores = [trim_title(m.get('title')) for m in members]
    core_counts = collections.Counter(c for c in cores if c)
    if len(members) == 1:
        return members[0].get('title')
    if core_counts and core_counts.most_common(1)[0][1] >= max(2, len(members) // 2):
        return core_counts.most_common(1)[0][0]
    pref = common_prefix([unicodedata.normalize('NFKC', m.get('title') or '')
                          for m in members]).strip(' 　-–—「」『』()（）')
    return pref if len(pref) >= 3 else (members[0].get('title') or '')


def alias_map(members, digest_of):
    """重複ファイル (md5 一致) の正本を 1 つ選ぶ。戻り値 (alias_of {episode_id: 正本の episode_id}, alias_paths)。
    digest_of(source_path) はファイルの md5 (読めなければ None)。"""
    digests = collections.defaultdict(list)
    for m in members:
        if m.get('source_exists'):
            dg = digest_of(m['source_path'])
            if dg:
                digests[dg].append(m)
    alias_of, alias_paths = {}, []
    for dg, dup in digests.items():
        if len(dup) > 1:
            canon = min(dup, key=episode_order_key)
            for other in dup:
                if other is not canon:
                    alias_of[other['episode_id']] = canon['episode_id']
                    alias_paths.append(other['source_path'])
    return alias_of, alias_paths


# --------------------------------------------------------------------------- 人手の上書き

# work_overrides.yml の 1 鍵に書けるもの (specs/S146.md)。episodes / evidence は雛形が書く参考値で、読まない
OV_KEYS = {'confirmed', 'note', 'work_title', 'split', 'detach', 'merge_into', 'episodes', 'evidence'}
OV_SPLIT_KEYS = {'work_title', 'episodes'}


def has_directive(ov):
    """クラスタの形か needs_review を変える指示 (version 2) があるか。"""
    return bool(ov.get('split') or ov.get('detach') or ov.get('merge_into')
                or ov.get('confirmed') is True)


def load_work_overrides(path):
    """catalog/work_overrides.yml の works セクション (無ければ {})。"""
    return read_yaml(path).get('works') or {}


def apply_overrides(groups, work_overrides, author_of, order_of):
    """union-find のクラスタに work_overrides.yml の指示を当てる (specs/S146.md・S147.md)。

    順序は merge_into → split → detach。戻り値は (units, errors)。unit は
      members  … 話のレコード (episodes.jsonl の順)
      key      … 上書きを引く鍵 (cluster のみ。当てる前の "<作者slug>|<機械の題名>")
      kind     … cluster (union-find のまま、または指示の残り) / split / detach
      title    … split の work_title (split のみ)
      override … 指示 (version 2) を当てたか (evidence に override を足す)
      confirmed… 人手で確認済み (needs_review を落とす)
    errors が空でなければ catalog を書かずに止める。
    """
    errors = []
    units = []
    by_key = collections.defaultdict(list)
    for g in groups:
        members = sorted(g, key=episode_order_key)
        un = {'members': list(g), 'kind': 'cluster', 'title': None,
              'override': False, 'confirmed': False,
              'key': '%s|%s' % (group_author(members, author_of), machine_title(members))}
        units.append(un)
        by_key[un['key']].append(un)

    directives = {}
    for key, ov in sorted(work_overrides.items(), key=lambda kv: str(kv[0])):
        if not isinstance(ov, dict):
            errors.append('%s: 値が辞書ではない' % key)
            continue
        unknown = set(ov) - OV_KEYS
        if unknown:
            errors.append('%s: 知らない項目 %s' % (key, sorted(unknown)))
        if 'confirmed' in ov and not isinstance(ov['confirmed'], bool):
            errors.append('%s: confirmed は true / false' % key)
        if not has_directive(ov):
            continue                    # work_title / note / 雛形だけの鍵は従来どおり (当たらなくてもよい)
        hit = by_key.get(key) or []
        if len(hit) != 1:
            errors.append('%s: 鍵に当たるクラスタが %d 件 (1 件であること)' % (key, len(hit)))
            continue
        directives[key] = ov

    # merge_into — 併合先は当てる前の鍵で書く。連鎖・自分自身・作者違いは誤り
    for key, ov in directives.items():
        tgt = ov.get('merge_into')
        if not tgt:
            continue
        src_un = by_key[key][0]
        tgt_hit = by_key.get(tgt) or []
        if not isinstance(tgt, str) or len(tgt_hit) != 1:
            errors.append('%s: merge_into の先 %r に当たるクラスタが %d 件 (1 件であること)'
                          % (key, tgt, len(tgt_hit)))
            continue
        if tgt == key:
            errors.append('%s: merge_into の先が自分自身' % key)
            continue
        if (directives.get(tgt) or {}).get('merge_into'):
            errors.append('%s: merge_into の先 %s も merge_into を持つ (連鎖は書かない)' % (key, tgt))
            continue
        if ov.get('split') or ov.get('detach'):
            errors.append('%s: merge_into と split / detach を同じ鍵に書いている' % key)
            continue
        tgt_un = tgt_hit[0]
        if key.split('|', 1)[0] != tgt.split('|', 1)[0]:
            errors.append('%s: merge_into の先 %s と作者が違う' % (key, tgt))
            continue
        tgt_un['members'].extend(src_un['members'])
        tgt_un['members'].sort(key=lambda r: order_of[r['episode_id']])
        src_un['members'] = []
        tgt_un['override'] = True

    # split → detach — 列挙した話をそのクラスタから抜き出す
    seen = {}

    def take(key, un, eid, what):
        if not isinstance(eid, str) or eid not in order_of:
            errors.append('%s: %s の %r は実在しない episode_id' % (key, what, eid))
            return None
        if eid in seen:
            errors.append('%s: %s の %s は %s にも現れる (同じ話は 1 つの指示だけ)'
                          % (key, what, eid, seen[eid]))
            return None
        seen[eid] = '%s の %s' % (key, what)
        for i, m in enumerate(un['members']):
            if m['episode_id'] == eid:
                return un['members'].pop(i)
        errors.append('%s: %s の %s はこのクラスタの話ではない' % (key, what, eid))
        return None

    for phase in ('split', 'detach'):
        for key, ov in directives.items():
            if ov.get('merge_into') or not ov.get(phase):
                continue
            un = by_key[key][0]
            if not un['members']:
                errors.append('%s: merge_into で併合済みのクラスタに %s を書いている' % (key, phase))
                continue
            items = ov[phase]
            if not isinstance(items, list):
                errors.append('%s: %s はリスト' % (key, phase))
                continue
            for item in items:
                if phase == 'detach':
                    rec = take(key, un, item, 'detach')
                    if rec is not None:
                        units.append({'members': [rec], 'kind': 'detach', 'title': None,
                                      'override': True, 'confirmed': False, 'key': None})
                    continue
                if not isinstance(item, dict) or set(item) - OV_SPLIT_KEYS:
                    errors.append('%s: split の各項目は work_title と episodes だけ' % key)
                    continue
                title = item.get('work_title')
                if not isinstance(title, str) or not title.strip():
                    errors.append('%s: split の work_title が空' % key)
                    continue
                eps = item.get('episodes')
                if not isinstance(eps, list) or not eps:
                    errors.append('%s: split 「%s」の episodes が空' % (key, title))
                    continue
                recs = [r for r in (take(key, un, e, 'split 「%s」' % title) for e in eps) if r]
                if recs:
                    recs.sort(key=lambda r: order_of[r['episode_id']])
                    units.append({'members': recs, 'kind': 'split', 'title': title.strip(),
                                  'override': True, 'confirmed': False, 'key': None})
            un['override'] = True

    for key, ov in directives.items():
        if ov.get('confirmed') is True:
            un = by_key[key][0]
            un['confirmed'] = True
            un['override'] = True
    return [un for un in units if un['members']], errors


# --------------------------------------------------------------------------- 組み立て

@dataclasses.dataclass(frozen=True)
class WorkContext:
    """work_of の繰り返しの間で変わらない入力 (assemble が 1 回作る)。"""
    union: object                  # cluster() の Union (話ごとの結合の根拠 why を引く)
    author_of: dict                # episode_id → 作者 slug
    author_name: dict = dataclasses.field(default_factory=dict)      # episode_id → 作者の表示名
    prev_slugs: dict = dataclasses.field(default_factory=dict)       # slug_overrides.yml の works
    work_overrides: dict = dataclasses.field(default_factory=dict)   # work_overrides.yml の works
    disk_title_pages: frozenset = frozenset()
    digest_of: object = lambda source_path: None                     # source_path → md5


def work_of(unit, ctx, used_slugs):
    """1 unit → (work レコード, slug_entries の 1 行の (key, entry), needs_review)。
    used_slugs は呼ぶごとに増える (作った slug を足す) ので ctx に入れない。"""
    members = unit['members']
    members.sort(key=episode_order_key)
    eids = [m['episode_id'] for m in members]
    aslug = group_author(members, ctx.author_of)
    rules = set()
    for eid in eids:
        rules |= ctx.union.why.get(eid, set())
    if unit['override']:
        rules.add('override')
    if unit['kind'] == 'split':
        work_title = unit['title']
    elif unit['kind'] == 'detach':
        work_title = members[0].get('title')
    else:
        work_title = machine_title(members)
    title_pages = sorted({m['source_path'] for m in members
                          if RE_TITLE_PAGE.search(m['source_path'])}
                         | {norm_path(n['href']) for m in members
                            for n in (m.get('nav_links') or [])
                            if RE_SERIES_NAV.search(n.get('label') or '')
                            and norm_path(n['href'])})

    alias_of, alias_paths = alias_map(members, ctx.digest_of)

    multi_dir = len({os.path.dirname(m['source_path']) for m in members}) > 1
    needs_review = bool(
        len(members) > 1 and (not (rules & STRONG) or multi_dir or len(members) >= 15))
    if unit['confirmed'] or unit['kind'] != 'cluster':
        needs_review = False        # 人手で確認した / 人手で分けた作品

    key = '%s|%s' % (aslug, work_title)
    ov = (ctx.work_overrides.get(unit['key']) or {}) if unit['kind'] == 'cluster' else {}
    if not isinstance(ov, dict):
        ov = {}
    if ov.get('work_title'):
        work_title = ov['work_title']

    old = ctx.prev_slugs.get(key)
    if isinstance(old, dict) and old.get('status') == 'confirmed' and old.get('slug'):
        slug, source, status = old['slug'], old.get('source'), 'confirmed'
    else:
        roman, source = slugmod.romanize(work_title)
        slug = '%s-%s' % (aslug, roman) if roman else aslug
        status = 'auto' if source == 'ascii' else 'candidate'
    slug = slugmod.unique_slug(slug, used_slugs, fallback_prefix='%s-work' % aslug)

    work = collections.OrderedDict([
        ('work_slug', slug),
        ('title', work_title),
        ('author_slug', aslug),
        ('author_display', ctx.author_name.get(eids[0])
         or ('シリーズ目次' if aslug == 'series-index' else '作者不詳')),
        ('episode_count', len(members)),
        ('corpora', dict(collections.Counter(m['corpus'] for m in members))),
        ('first_date', min((m.get('date') or '' for m in members), default=None)),
        ('last_date', max((m.get('date') or '' for m in members), default=None)),
        ('title_pages', [p for p in title_pages if p]),
        ('title_page_on_disk', [p for p in title_pages if p in ctx.disk_title_pages]),
        ('evidence', sorted(rules)),
        ('needs_review', needs_review),
        ('multi_directory', multi_dir),
        ('alias_paths', sorted(set(alias_paths))),
        ('slug_source', source),
        ('slug_status', status),
        ('episodes', [collections.OrderedDict([
            ('episode_id', m['episode_id']),
            ('menu_order', i + 1),
            ('title', m.get('title')),
            ('source_path', m['source_path']),
            ('date', m.get('date')),
            ('alias_of', alias_of.get(m['episode_id'])),
        ]) for i, m in enumerate(members)]),
    ])
    return work, (key, {'slug': slug, 'status': status, 'source': source}), needs_review


def report_of(records, works, review, series_rows, disk_title_pages):
    orphans = [r['episode_id'] for r in records
               if r['episode_id'] not in {e['episode_id'] for w in works for e in w['episodes']}]
    return collections.OrderedDict([
        ('generated_by', 'scripts/wp/work_builder.py'),
        ('episodes', len(records)),
        ('works', len(works)),
        ('works_single_episode', sum(1 for w in works if w['episode_count'] == 1)),
        ('works_multi_episode', sum(1 for w in works if w['episode_count'] > 1)),
        ('episodes_in_works', sum(w['episode_count'] for w in works)),
        ('orphans', orphans),
        ('needs_review', len(review)),
        ('needs_review_reasons', dict(collections.Counter(
            'weak-evidence' if not (set(w['evidence']) & STRONG) else
            ('multi-directory' if w['multi_directory'] else 'large-cluster') for w in review))),
        ('evidence_histogram', dict(collections.Counter(
            tuple(w['evidence']) and ','.join(w['evidence']) or 'single' for w in works))),
        ('series_html_rows', len(series_rows)),
        ('series_html_rows_with_links',
         sum(1 for r in series_rows if r['episode_paths'] or r['title_page'])),
        ('title_pages_on_disk', len(disk_title_pages)),
        ('works_with_title_page', sum(1 for w in works if w['title_pages'])),
        ('alias_paths_total', sum(len(w['alias_paths']) for w in works)),
        ('largest_works', [[w['work_slug'], w['episode_count']] for w in works[:10]]),
        ('slug_sources', dict(collections.Counter(w['slug_source'] for w in works))),
        ('slug_pending_review', sum(1 for w in works if w['slug_status'] == 'candidate')),
    ])


def assemble(records, authors, series_rows, disk_title_pages, overrides, work_overrides,
             digest_of=lambda source_path: None):
    """読み込んだ入力から works.jsonl とレポートを組む。

    戻り値 (works, report, overrides, review, override_errors)。review は
    [(上書きの鍵, work)] (needs_review が立った作品)。digest_of(source_path) はファイルの md5。
    """
    author_of, author_name = authors_index(authors)
    u = cluster(records, author_of, series_rows)
    groups = collections.defaultdict(list)
    for rec in records:
        groups[u.find(rec['episode_id'])].append(rec)

    prev_slugs = overrides.get('works') or {}
    # 人手の上書き (merge_into → split → detach、confirmed) を当ててから題名・slug を決める (specs/S147.md)
    order_of = {r['episode_id']: i for i, r in enumerate(records)}
    units, override_errors = apply_overrides(list(groups.values()), work_overrides,
                                             author_of, order_of)

    ctx = WorkContext(union=u, author_of=author_of, author_name=author_name, prev_slugs=prev_slugs,
                      work_overrides=work_overrides, disk_title_pages=disk_title_pages, digest_of=digest_of)
    used_slugs, works, slug_entries, review_keys = set(), [], {}, []
    ordered = sorted(units, key=lambda un: (-len(un['members']), un['members'][0]['episode_id']))
    for unit in ordered:
        work, (key, entry), needs_review = work_of(unit, ctx, used_slugs)
        slug_entries[key] = entry
        if needs_review:
            review_keys.append(unit['key'])
        works.append(work)

    overrides = slugmod.merge_section(overrides, 'works', slug_entries)
    review = [w for w in works if w['needs_review']]
    assert len(review) == len(review_keys)
    report = report_of(records, works, review, series_rows, disk_title_pages)
    return works, report, overrides, list(zip(review_keys, review)), override_errors


def digest_in(root):
    """root 配下のファイルの md5 を引く関数 (assemble の digest_of)。"""
    return lambda source_path: md5_of(os.path.join(root, source_path))


# --------------------------------------------------------------------------- work_overrides.yml の雛形

WORK_OVERRIDES_HEADER = """\
# Work クラスタの人手上書き (scripts/wp/work_builder.py が読む)。
#
# 鍵は "<作者slug>|<機械が付けた作品名>"。値に書けるもの (specs/S146.md・S150.md):
#   work_title: 正しい作品名   … これを直すと slug の候補も作り直される
#   note:       メモ
#   confirmed:  true           … 人手で確認した (needs_review を落とす)
#   split:      [{work_title: 新しい作品名, episodes: [episode_id, …]}, …]
#                              … 列挙した話を新しい作品に分ける。残りは元の作品に残る
#   detach:     [episode_id, …] … 1 話ずつ単独の作品にする (題名は話の題名)
#   merge_into: "<鍵>"         … このクラスタの全話を同じ作者の別のクラスタへ併合する
#                                (先の鍵も機械が付けた作品名で書く)
# 当てる順は merge_into → split → detach (specs/S147.md)。参照の誤りは --check が止める。
#
# 下に並んでいるのは needs_review が立ったクラスタ (根拠が弱い / 複数ディレクトリに
# 散らばる / 15 話以上) の雛形です。**正しければ何もしなくて構いません**。
# T7.5.1 (specs/S176.md) がこのファイルの確認タスクです。
"""


def overrides_stub(existing, review):
    """needs_review が立った作品の雛形を既存の work_overrides に足す (既存の鍵は触らない)。"""
    stub = dict(existing)
    for key, w in review:
        stub.setdefault(key, {'work_title': w['title'], 'episodes': w['episode_count'],
                              'evidence': w['evidence'], 'confirmed': False})
    return stub


def confirmed_but_review(review, work_overrides):
    """confirmed: true なのに needs_review が立った作品の鍵 (自己検査用)。"""
    return [k for k, w in review if (work_overrides.get(k) or {}).get('confirmed') is True]


def summary_lines(report):
    return [
        'works=%d (単発 %d / 連載 %d)  episodes=%d  orphan=%d' %
        (report['works'], report['works_single_episode'], report['works_multi_episode'],
         report['episodes_in_works'], len(report['orphans'])),
        'needs_review=%d %s' % (report['needs_review'], report['needs_review_reasons']),
        'title page を持つ work=%d / series.html の有効行=%d / alias=%d' %
        (report['works_with_title_page'], report['series_html_rows_with_links'],
         report['alias_paths_total']),
        'slug: %s 確認待ち=%d' % (report['slug_sources'], report['slug_pending_review']),
    ]
