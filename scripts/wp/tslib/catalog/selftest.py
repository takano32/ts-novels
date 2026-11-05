# -*- coding: utf-8 -*-
"""selftest.py — 受け入れ条件をロジックで検査する。

survey 実測値との突き合わせは正規目録 (corpus=honkan) に対してのみ行う。
旧目録 (corpus=legacy) は件数・パース失敗・欄の解決率を別に見る。
"""
import collections

from tslib.checks import Checks
from tslib.fileio import dumps_jsonl
from tslib.mail import RE_BARE_MAIL
from tslib.catalog.honkan import EXPECTED_ENTRIES
from tslib.catalog.legacy import EXPECTED_LEGACY_BLOCKS
from tslib.catalog.records import strip_private

# survey の fields 節の実測値 (honkan のみ)
EXPECTED_HONKAN = (
    ('homepage 件数 == 918 (=771+147)', lambda r: r['homepage'], 918),
    ('画師あり == 477', lambda r: r['illustrator'], 477),
    ('オススメあり == 765', lambda r: r['osusume'], 765),
    ('特殊エントリ (画像/外部/anchor) == 28',
     lambda r: r['entry_type'] != 'html' or r['source_anchor'] or r['source_path'].startswith('special/'), 28),
    ('あらすじ空 == 182', lambda r: not r['arasuji'], 182),
    ('コメント空 == 290', lambda r: not r['comment'], 290),
    # 推薦文の「空」は survey の定義 (欄の生値が空) で数える。表示用の r['suisen'] は
    # 末尾の 【…】 ナビリンクを外した値なので、ナビだけの欄が空扱いになり数が合わない。
    ('推薦文空 == 147', lambda r: r['_suisen_full_empty'], 147),
    ('ジャンル空 == 238', lambda r: not r['genre'], 238),
    ('種別空 == 326', lambda r: not r['type'], 326),
    ('キーワード空 == 437', lambda r: not r['keywords'], 437),
)
EXPECTED_RAW_DISTINCT = (('genre_raw', 244), ('type_raw', 187), ('keywords_raw', 1083))
EXPECTED_KANSOU_SLUGS = 305
EXPECTED_TAKEDOWN_NOTICES = 9
REQUIRED_HONKAN = ('episode_id', 'source_path', 'title', 'author', 'date', 'date_raw',
                   'size_kb', 'kansou_slug', 'orig_url', 'catalog_ref')
REQUIRED_LEGACY = ('episode_id', 'source_path', 'title', 'date', 'catalog_ref')


def work_listed(rec, works, denylist_text):
    """停止告知のある話が work 単位で denylist に載っているか (works.jsonl のレコード経由で照合)。
    works が None (ファイルが無い) なら False。"""
    if works is None:
        return False
    for w in works:
        eids = [e['episode_id'] if isinstance(e, dict) else e for e in w['episodes']]
        if rec['episode_id'] in eids:
            return w['work_slug'] in denylist_text
    return False


def check_mailto_residue(c, records):
    """「捨てたはずのアドレス」を全部集め、出力 JSONL の全文に 1 つも出てこないことを確かめる。
    ファイル名の @ (index@08201937.html) や homepage の URL を誤検知しないよう、
    検査対象は「mailto: リンクから実際に抽出した文字列」そのものに限定する。"""
    addresses = set()
    for r in records:
        for a in r.get('_dropped_mailto') or []:
            if a:
                addresses.add(a)
    payload = dumps_jsonl(strip_private(r) for r in records)
    residue = sorted(a for a in addresses if a in payload)
    c.check('mailto から抽出したアドレスの残存 0 (%d 件を検査)' % len(addresses),
            not residue, '残存 %s' % residue[:5])
    c.check("出力に 'mailto:' が現れない", 'mailto:' not in payload,
            '出現位置あり' if 'mailto:' in payload else '')
    # 「アドレスらしき文字列」が新たに現れていないことも見る。ただし
    # `index@08201937.html` 型のファイル名や `bbs@log_<id>.cgi` を誤検知しないよう、
    # 拡張子で終わる形は RE_BARE_MAIL 側で除外してある。
    bare = sorted(set(RE_BARE_MAIL.findall(payload)))
    c.check('出力にアドレス形の文字列が無い', not bare, '検出 %s' % bare[:5])
    # 注: メールのローカルパート単独 (kagerou6 等) は感想板 slug と一致することがあり
    # (板 id はアドレスから採られた例が多い)、これを残存とみなすと誤検知になる。
    # 板 id は .cgi のファイル名由来であって mailto 由来ではないので検査対象にしない。


def check_survey_values(c, honkan):
    """仕様の実測値との突き合わせ (survey の fields 節。honkan のみ)。"""
    def rate(pred):
        return sum(1 for r in honkan if pred(r))
    for name, pred, expected in EXPECTED_HONKAN:
        n = rate(pred)
        c.check(name, n == expected, '実測 %d' % n)
    slugs = len({r['kansou_slug'] for r in honkan})
    c.check('感想板 slug の異なり == %d' % EXPECTED_KANSOU_SLUGS, slugs == EXPECTED_KANSOU_SLUGS,
            '実測 %d' % slugs)
    for field, expected in EXPECTED_RAW_DISTINCT:
        n = len({t for r in honkan for t in r[field]})
        c.check('%s の異なり == %d (NFKC 前)' % (field, expected), n == expected, '実測 %d' % n)


def check_required(c, records, fields, label):
    """必須フィールドが 1 件も欠けていないこと。"""
    for field in fields:
        missing = sum(1 for r in records if r.get(field) in (None, '', []))
        c.check('必須欄 %s に欠落なし (%s)' % (field, label), missing == 0, '欠落 %d 件' % missing)


def check_legacy(c, legacy, legacy_stats):
    c.check('旧目録ブロック数 == %d (lib01-06 の 252 + lib07-09 の 84)' % EXPECTED_LEGACY_BLOCKS,
            legacy_stats['blocks_parsed'] == EXPECTED_LEGACY_BLOCKS,
            '実測 %d 件' % legacy_stats['blocks_parsed'])
    c.check('旧目録のパース失敗 0', legacy_stats['parse_failures'] == 0,
            '失敗 %d 件%s' % (legacy_stats['parse_failures'],
                            (': ' + legacy_stats['parse_failure_detail'][0])
                            if legacy_stats['parse_failure_detail'] else ''))
    c.check('旧目録の追加件数 == blocks - 正規目録との重複',
            legacy_stats['added'] ==
            legacy_stats['blocks_parsed'] - legacy_stats['dropped_as_honkan_duplicate'],
            '追加 %d / 重複除外 %d' % (legacy_stats['added'],
                                      legacy_stats['dropped_as_honkan_duplicate']))
    check_required(c, legacy, REQUIRED_LEGACY, 'legacy')
    # author は T7.4 の受け入れ条件「全 episode の author が解決」に直結するので個別に見る
    # (entry_role=notice は作品ではなく編集部の告知ブロックなので対象外)
    noauthor = [r['catalog_ref'] for r in legacy
                if not r['author'] and r['entry_role'] == 'work']
    c.check('legacy(work) の author 解決率 100%', not noauthor,
            '未解決 %d 件 %s' % (len(noauthor), noauthor[:5]))


def check_takedown_notices(c, records, denylist_text, works):
    """作者からの公開停止の申し出は、必ず takedown/denylist.yml に載っていること。
    目録に停止告知があるのに denylist に無い = 取り下げ済み作品を再公開している。"""
    notices = [r for r in records if r.get('takedown_notice')]
    c.check('公開停止の告知を検出 == %d 件' % EXPECTED_TAKEDOWN_NOTICES,
            len(notices) == EXPECTED_TAKEDOWN_NOTICES, '実測 %d 件' % len(notices))
    unlisted = [r['catalog_ref'] for r in notices
                if r['source_path'] not in denylist_text and not work_listed(r, works, denylist_text)]
    c.check('停止告知のある話が denylist に載っている', not unlisted,
            '未掲載 %d 件 %s' % (len(unlisted), unlisted[:5]))


def selftest(records, failures, legacy_stats=None, denylist_text='', works=None):
    """受け入れ条件をロジックで検査する。戻り値 (ok, [(name, ok, detail)])。

    denylist_text は takedown/denylist.yml の全文 (無ければ '')、works は catalog/works.jsonl の
    レコード (無ければ None)。停止告知の照合に使う。
    """
    c = Checks()
    honkan = [r for r in records if r['corpus'] == 'honkan']
    legacy = [r for r in records if r['corpus'] == 'legacy']

    c.check('正規目録エントリ数 == %d' % EXPECTED_ENTRIES, len(honkan) == EXPECTED_ENTRIES,
            '実測 %d 件' % len(honkan))
    c.check('パース失敗 0', not failures,
            '失敗 %d 件%s' % (len(failures), (': ' + failures[0]) if failures else ''))

    ids = [r['episode_id'] for r in records]
    dup = [k for k, v in collections.Counter(ids).items() if v > 1]
    c.check('episode_id の重複 0', not dup, '重複 %d 件 %s' % (len(dup), dup[:5]))

    check_mailto_residue(c, records)
    check_survey_values(c, honkan)
    check_required(c, honkan, REQUIRED_HONKAN, 'honkan')
    if legacy_stats is not None:
        check_legacy(c, legacy, legacy_stats)
    check_takedown_notices(c, records, denylist_text, works)
    return c.as_tuple()
