# S.79 削除 ④: git 履歴からの除去

- 種類: 手順
- 状態: 有効
- 出典: docs/removal-runbook.md の「④ git 履歴からの除去」
- 関連: T.18.4

## ④ git 履歴からの除去 (依頼があったときのみ)

**既定では実施しない。** 依頼者が「配布そのものを止めてほしい」と明示した場合、
または高 PII・法的要求の場合に限る。理由: 履歴の書き換えは全 commit hash を変え、
他の全クローンとの互換を壊す破壊的操作であるため。

```sh
# 事前に必ず: バックアップ clone を私有ストレージに取る
git clone --mirror . /path/to/private/ts-novels-backup-$(date +%Y%m%d).git

pip install git-filter-repo
git filter-repo --invert-paths --path 'novel/200209/19204751/d_upboy06.htm' --force
git push --force origin master
git push --force origin --tags
```

### `raw-original` タグに残る事実 (重要)

このリポジトリには**回収直後の無変換状態を指す `raw-original` タグ**があり、
削除対象ファイルはそのタグの指すツリーにも含まれる。`git filter-repo` の
`--invert-paths` は**タグの指す履歴も書き換える**が、以下の残留経路がある:

1. **他者の既存クローン / fork**。すでに配布された分は回収不能。
2. **GitHub 側のダングリングオブジェクト**。force push 後も一定期間 API/`?w=` 経由で
   到達できることがある。GitHub サポートに GC を依頼するのが確実。
3. **Wayback / Software Heritage 等のアーカイブ**が公開リポジトリを取得している場合。

**対応方針**: 依頼者には ④ の限界を正直に伝える —
「公開面 (WP・Pages) は 72 時間で確実に止める。git 履歴は書き換えるが、
既に配布されたクローンと第三者アーカイブまでは遡及できない」。
この文言は `/takedown/` ページにも常設する (README の「マスクは秘匿でなく礼儀」と同じ姿勢)。
`raw-original` タグを削除して打ち直した場合は、その事実を `denylist.yml` の `notes` に記録する。
