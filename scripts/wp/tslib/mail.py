# -*- coding: utf-8 -*-
"""mail.py — メールアドレスの検出と除去 (S10 の「アドレスを DB に入れない」決まり)。

RE_BARE_MAIL は mailto から拾ったのではない「素のアドレス表記」。`index@08201937.html` 型の
ファイル名や `bbs@log_johdan.cgi` を誤検知しないよう、拡張子で終わる形を除外する。
目録 (catalog_build)・本文 (payload_build)・運営文書 (docs_build) の 3 経路が同じ正規表現を使う。

本文側では明白なプレースホルダ (hoge/example/localhost) は史料性を優先して残す。
残す語の集合は経路で少し違う (運営文書は Postfix の設定例 foo@/bar@ も残す) ので、
呼び出し側が keep を渡す。
"""
import re

RE_BARE_MAIL = re.compile(
    r'[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.'
    r'(?!s?html?\b|cgi\b|jpe?g\b|gif\b|png\b|txt\b|js\b)[A-Za-z]{2,}')
# 本文 (payload_build) で残すプレースホルダ
MAIL_KEEP_RE = re.compile(r'(?i)hoge|example|localhost|virtual\.domain')
# 運営文書 (docs_build) で残すもの。技術記事の設定例 (Postfix の virtual 設定など) は個人情報ではない
MAIL_KEEP_DOCS_RE = re.compile(r'(?i)hoge|example|localhost|virtual\.domain|foo@|bar@')
# `<a href="mailto:…">LABEL</a>` — リンクを解いて LABEL だけ残すときに使う
MAILTO_A_RE = re.compile(r'(?is)<a\b[^>]*href\s*=\s*["\']mailto:[^"\']*["\'][^>]*>(.*?)</a>')
MAILTO_ATTR_RE = re.compile(r'(?i)\s*href\s*=\s*["\']mailto:[^"\']*["\']')
REDACTED = '(メールアドレスは省略)'


def redact_bare(text, keep=MAIL_KEEP_RE, counter=None, key='bare'):
    """素のアドレスを REDACTED に伏せる。keep に当たるものは残す。
    counter (dict) を渡すと伏せた数を counter[key] に足す。"""
    def rep(m):
        if keep is not None and keep.search(m.group(0)):
            return m.group(0)
        if counter is not None:
            counter[key] = counter.get(key, 0) + 1
        return REDACTED
    return RE_BARE_MAIL.sub(rep, text)


def scrub_addresses(text, counter, keep=MAIL_KEEP_RE):
    """mailto リンクはリンクを解いて中身のテキストだけ残し、生アドレスは伏せる (payload_build 由来)。
    md と payload の両側に同じ適用をするので無損失検査とは干渉しない (visible は
    scrub 済み同士を比べる)。counter は {'mailto': n, 'bare': n}。"""
    def drop_link(m):
        counter['mailto'] += 1
        return m.group(1)
    text = MAILTO_A_RE.sub(drop_link, text)
    text = MAILTO_ATTR_RE.sub('', text)
    return redact_bare(text, keep, counter, 'bare')


def has_leak(text, keep=MAIL_KEEP_RE):
    """出荷前の検査: keep に当たらない生アドレスか mailto: リンクが残っているか。"""
    for m in RE_BARE_MAIL.finditer(text):
        if not keep.search(m.group(0)):
            return True
    return 'href="mailto:' in text or "href='mailto:" in text
