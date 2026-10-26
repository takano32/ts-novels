# T.17 取り下げ窓口の暫定修復 — 公開面 3 枚 (S.112, S.113)

- 状態: 未了
- 関連: S.112, S.113, S.134

- [ ] T.17.1 (八重洲さんの判断) 窓口メールアドレスと暫定文面 (S.134 の案を期限なしにしたもの) の承認
- [ ] T.17.2 (T.17.1 の後) content/pages/takedown.html から GitHub Issue の段落を消し、メール窓口と暫定文面に → `grep -c -E 'Issue|72' content/pages/takedown.html` = 0
- [ ] T.17.3 content/pages/about.html と removed.html の「72 時間以内」を暫定文面に → `grep -c 72 content/pages/*.html` が全ファイル 0
- [ ] T.17.4 配備: deploy.sh --apply → 本番 `wp ts sync-pages` が updated=3 → curl (`?_cb=`) で 3 ページに `Issue` と `72` が無くメール窓口が出る → キャッシュクリア依頼 (高野さんの作業)
