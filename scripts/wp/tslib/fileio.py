# -*- coding: utf-8 -*-
"""fileio.py — JSON / JSONL / YAML / テキストの読み書き。

出力の形は各段がこれまで書いていた形そのまま (JSONL は 1 行 1 件 + 改行、JSON は
ensure_ascii=False、レポートは indent=1 + 末尾の改行)。生成物を 1 バイトも変えないため、
indent と末尾の改行は呼び出し側が指定する。
"""
import hashlib
import json
import os
import sys

try:
    import yaml
except ImportError:                                       # pragma: no cover
    yaml = None


def require_yaml():
    if yaml is None:
        raise RuntimeError('PyYAML が必要です (pip install pyyaml)')
    return yaml


def exit_without_yaml(message='PyYAML が要ります'):
    """PyYAML が無ければ message を標準エラーに出して終了コード 2 で止める (入口の main の頭で呼ぶ)。"""
    if yaml is None:
        print(message, file=sys.stderr)
        raise SystemExit(2)


# --------------------------------------------------------------------------- テキスト

def read_text(path, encoding='utf-8', errors='strict'):
    with open(path, encoding=encoding, errors=errors) as fh:
        return fh.read()


def read_text_or_empty(path):
    """読めなければ空文字 (目録外走査のように壊れたファイルで止めたくないとき)。"""
    try:
        with open(path, encoding='utf-8', errors='replace') as fh:
            return fh.read()
    except OSError:
        return ''


def write_text(path, text, encoding='utf-8'):
    with open(path, 'w', encoding=encoding) as fh:
        fh.write(text)


def decode_guess(data):
    """ミラーは UTF-8 変換済みだが、変換対象外だった .txt に当時のままのものが残る。
    utf-8 → cp932 → euc_jp の順に試し、どれも通らなければ cp932 の置換復号。戻り値 (text, encoding)。"""
    for enc in ('utf-8', 'cp932', 'euc_jp'):
        try:
            return data.decode(enc), enc
        except UnicodeDecodeError:
            continue
    return data.decode('cp932', 'replace'), 'cp932-replace'


def read_text_guess(path):
    """文字コードを推定して読む。戻り値 (text, encoding)。"""
    with open(path, 'rb') as fh:
        return decode_guess(fh.read())


def md5_of(path):
    """ファイルの md5 (16 進)。読めなければ None。"""
    try:
        with open(path, 'rb') as fh:
            return hashlib.md5(fh.read()).hexdigest()
    except OSError:
        return None


# --------------------------------------------------------------------------- JSON / JSONL

def read_jsonl(path):
    with open(path, encoding='utf-8') as fh:
        return [json.loads(line) for line in fh]


def write_jsonl(path, records):
    with open(path, 'w', encoding='utf-8') as fh:
        for rec in records:
            fh.write(json.dumps(rec, ensure_ascii=False) + '\n')


def dumps_jsonl(records):
    """JSONL の全文 (自己検査がアドレスの残存を全文検索するときに使う)。"""
    return '\n'.join(json.dumps(rec, ensure_ascii=False) for rec in records)


def read_json(path):
    with open(path, encoding='utf-8') as fh:
        return json.load(fh)


def write_json(path, data, indent=1, newline=True):
    """ensure_ascii=False で書く。newline=True なら末尾に改行を 1 つ足す (レポートの従来形)。"""
    with open(path, 'w', encoding='utf-8') as fh:
        json.dump(data, fh, ensure_ascii=False, indent=indent)
        if newline:
            fh.write('\n')


def ensure_dir(path):
    os.makedirs(path, exist_ok=True)


def ensure_parent(path):
    os.makedirs(os.path.dirname(path), exist_ok=True)


# --------------------------------------------------------------------------- YAML

def read_yaml(path):
    """無ければ {}。空ファイルも {}。"""
    if not os.path.exists(path):
        return {}
    require_yaml()
    with open(path, encoding='utf-8') as fh:
        return yaml.safe_load(fh) or {}


def read_yaml_required(path):
    """read_yaml と同じだが、無ければ FileNotFoundError で止まる。無いことを {} とみなしてはいけない
    入力 (takedown/denylist.yml: 無いと取り下げを 1 件も飛ばさずに payload を作ってしまう) に使う。"""
    require_yaml()
    with open(path, encoding='utf-8') as fh:
        return yaml.safe_load(fh) or {}


def dump_yaml(fh, data):
    """このリポジトリの YAML の書き方 (unicode そのまま・鍵は整列・ブロック形式)。"""
    require_yaml().safe_dump(data, fh, allow_unicode=True, sort_keys=True, default_flow_style=False)


def write_yaml(path, data, header=''):
    """header (コメント) を先に書いてから YAML を書く。"""
    require_yaml()
    with open(path, 'w', encoding='utf-8') as fh:
        if header:
            fh.write(header)
        dump_yaml(fh, data)
