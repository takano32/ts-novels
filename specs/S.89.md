# S.89 連絡の進捗と catalog の連動

- 種類: 手順
- 状態: 一部失効 (S.43 で上書き。72 時間)
- 出典: docs/author-outreach.md の「4. 台帳との連動」
- 関連: T.33.3

## 4. 連絡の進捗と catalog の連動

- `catalog/authors.json` の `contact_status`(uncontacted → notified → permitted / declined →
  takedown → removed)を、連絡の進捗に応じて更新する(実装は T.7.4)
- 許諾が取れない/連絡不達の作者も、要決定 Q4 の決定により**本文は公開し、削除依頼に 72h で応じる**
  (S.40)。ただし S.87 の 2-4 のような本人が転載条件を明示している場合はその意思を優先する
