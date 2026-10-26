# T.20 「文庫前史 (1999)」→「にせ乙女パラダイス」(S.105)

- 状態: 未了
- 関連: S.105, S.14, S.15, S.51

- [ ] T.20.1 (八重洲さんの判断) 判断: 年の表記 / URL の変更 / 解題 1 行の文言 / 管理人名義の記載 (S.105 の未決)
- [ ] T.20.2 (T.20.1 の後) indexes.php の区分見出し・解題 1 行・年表脚注を S.105 の仕様 (表示ラベルと解題 1 行) どおりに → `php -l` OK
- [ ] T.20.3 scripts/wp/docs_build.py のラベルを揃え `docs_build.py` を再実行 → `payloads-docs/_manifest.json` の prehistory 8 本の `section_label` が新ラベル (本番 sync-docs は skipped でよい。S.105)
- [ ] T.20.4 配備: deploy.sh --apply → `/index/docs/` の `sec-prehistory` 見出しが新ラベルで解題が続く、`/index/timeline/` に `文庫前史` が無い、`/docs/prehistory-nyotai/` 200 → キャッシュクリア依頼 (高野さんの作業)
- [ ] T.20.5 S.14 の最旧の世代・S.15 の「旧サイト」行と S.51 の `yasai/` 行を S.105 の仕様「内部文書の訂正」どおりに訂正 → `grep -n 前史 specs/S.14.md specs/S.15.md specs/S.51.md` の残りが `~yaji` 関連だけ
