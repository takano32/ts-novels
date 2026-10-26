# S.190 原本併読 QA (T.9.3、元の 3.3)

- 種類: 記録
- 状態: 有効
- 出典: docs/wp-implementation-tasks.md の 3.3
- 関連: T.9.3

- [x] 3.3 無作為 100 話の原本併読 QA(原本アーカイブの原本と並べて本文欠落・順序破壊がないか)。
      結果を `catalog/QA.md` に追記。**問題率 >2% なら 1.6 に戻る**
      - **実施 (2026-08-30、実行セッションによる独立検算)**。検証器は
        **`scripts/wp/qa_phase3_check.py`**(S.12 の所管の例外「独立検算は別の書き手」に該当。
        `body_convert.py` / `payload_build.py` の正規化関数は**一切流用していない** —
        原本の抽出は標準ライブラリの `html.parser` で書き直し、比較は
        「空白を全部落とした文字列の**連続部分列**か」で行う独立実装)。
        結果の生データは **`catalog/reports/qa_phase3_check.json`**。
      - **標本**: `payloads/` を持つ 3,646 話(= converted 3,642 + converted-text 2 +
        raw-fallback 2。placeholder 198 は対象外)から `random.seed(20260830)` で 100 話。
      - **最終結果 (2026-08-30 再実行。コミット `3f20b1a0` の修正後、同一 seed)**:
        **ok 97 / ok-trimmed 1 / ok-image 2 / ng 0 → 問題率 0.0%**(所要 47 秒)。
        **本文の欠落・順序破壊・文字化けは 0 件**。
        残る `ok-trimmed` 1 件 (`novel__200112__18230553__over_heat.html`) は
        原本末尾の**【文庫管理人より】の定型文 262 字**をクローム除去したもので設計どおり。
        `ok-image` 2 件は本文が画像 1 枚の作品で、ページが原本を指す `img` を持つことを確認。
        **`markdown_image_leak_total` は 17 → 0**
      - **初回結果 (修正前。経緯として残す)**: ok 81 / ok-texturize 15 / ok-trimmed 2 /
        ng-image 2 = **問題率 2.0%**。ここで検出した 2 件の指摘が下記のとおり修正された
      - **指摘 ① 表示時の字面変換 → `3f20b1a0` で停止 (原文尊重)**。
        修正後の実測: `apply_filters('the_content', 'A - B -- C ... D "quoted" E :-) F')` の
        出力が**入力とバイト同一**(en dash / em dash / 三点リーダ / 曲がり引用符 /
        絵文字 smiley のいずれも含まれない)。`run_wptexturize` に `false` を返すフィルタが入り、
        `use_smilies` は `false`。**`wptexturize` と `convert_smilies` は `the_content` に
        登録されたままだが無害化されている**(フィルタ自体を外すより副作用が小さい)。
        ページ側でも `Blue - μ` が ASCII ハイフンのまま出ることを確認
      - **指摘 ② 画像作品の Markdown 漏れ → `3f20b1a0` で解消**。
        payload の生記法は **17 → 0**、`wp:image` を持つ payload は **618 → 635**。
        本番 DB 側も**生記法 0 件 / `wp:image` 635 件**。
        受入: `/novel/s_haneoka-udoshi-2/` に
        `<img decoding="async" src="/assets/annex-img/novel/grp/bunny99.jpg" alt="卯年">` が出力され、
        画像は **200 image/jpeg 88,724B**、ページ内の生記法は 0 箇所
      - **(以下は修正前の記録) ok-texturize 15 話の中身 = 不具合ではないが要判断だった**:
        WordPress の `the_content` フィルタが**表示のときだけ**字面を変える。
        実測した変換は `wptexturize`(優先度 10)による
        **` - ` → `–`(en dash)・`--` → `—`・`...` → `…`・`"…"` → `“…”`**。
        **DB の中身は原本どおり**(該当話で ASCII `-` のまま・en dash なしを確認済み)なので
        無損失証明は保たれているが、**読者が見る字面は原本と違う**。
        さらに **`use_smilies=1` で `convert_smilies`(優先度 20)が有効**で、
        `:-)` が 🙂 の画像文字に置換される(`convert_smilies("やった :-) ね")` を実測)。
        2000 年代の和文テキストは顔文字を含みうるので、**原文尊重を優先するなら
        `remove_filter('the_content','wptexturize')` と `use_smilies=0` を検討**。
        T.10 のテーマ整備時に決めるのが自然(サイト所有者または親セッションの判断)
      - **(修正前の記録) ng-image 2 話 = 実在の不具合だった**:
        **画像作品の payload が Markdown の画像記法のままで、`wp:image` ブロックに
        変換されていない。** 例 `payloads/novel__grp__bunny99.jpg.html` の中身は
        `<!-- wp:paragraph --><p>![卯年](bunny99.jpg)</p><!-- /wp:paragraph -->`。
        このため **①画像が表示されない ②読者に生の `![卯年](bunny99.jpg)` が見える
        ③src が `/assets/annex-img/` に書き換わっていない**(3.2 前半の対象から漏れている)。
        **該当は標本の 2 話だけでなくコーパス全体で 17 話**
        (数え方: `grep -lE '!\[[^]]*\]\([^)]*\)' payloads/*.html` = 17。
        本番 DB 側も `post_content` が同記法の投稿 17 件で一致。全 episode_id は
        `catalog/reports/qa_phase3_check.json` の `markdown_image_leak_episodes`)。
        正しく `wp:image` になっている payload は 618 本あるので、
        **壊れているのは「本文が画像 1 枚だけ」の分類(設計 v1.2 の特殊エントリ ②)に限る**
      - **検証器自身のバグを 2 つ潰してから確定させた**(独立検算の信頼性のため。
        初回は問題率 4.0% と出たが、いずれも検証器側の誤りだった):
        (a) 原本の裸の `<` を逃がす正規表現が厳しすぎて、`<FONTCOLOR="#FFD700">`(空白抜けの
        壊れたタグ)や Word の `<o:p></o:p>` を**文字列として拾っていた**。
        ブラウザは `<` の直後が ASCII 英字ならタグとして読み捨てるので、
        HTML5 のトークナイザ規則どおり `<(?!/?[A-Za-z])` に直した
        (b) 原本が `.jpg` の画像作品を**バイナリのままテキスト比較**していた。
        画像作品は「ページが原本を指す img を持つか」で判定するよう分けた
