export const meta = {
  name: 'wp-library-understand',
  description: '少年少女文庫ミラーの構造・メタデータ・コミュニティ層を WordPress 設計のために全数調査',
  phases: [{ title: 'Survey', detail: '8視点の並列調査' }],
}
phase('Survey')
const ROOT = '/home/takano32/GitHub/ts-novels'
const S = (props) => ({type: 'object', properties: props, additionalProperties: true})
const results = await parallel([
  () => agent(`${ROOT} は閉鎖サイト「少年少女文庫」(TS小説投稿サイト)の静的復元ミラー。これを WordPress の作品ライブラリに変換する設計のため、目録ページの正確なスキーマを確定せよ。
lib1.html〜lib30前後 と lib2.html を Python で機械パースし (utf-8)、作品エントリ(<TABLE BORDER=1>ブロック、【あらすじ】含む)の全フィールドを列挙: 題名+本文リンク/作者名+mailto+Homepage/挿絵クレジット(「挿画：」「イラストなし」等の揺れ)/日付形式/サイズKB/感想リンク(~ts/kansou/bbs@log_*.cgi)/あらすじ/コメント/オススメ作品(リンク+作者)/推薦文/ジャンル/種別/キーワード。
最低5ページ・200エントリをパースし、フィールド出現率・形式の揺れ・パース不能ケースを定量報告。全lib*.htmlの総エントリ数も数えよ。結果は機械実装可能なパース仕様として返せ。`,
    {label: 'catalog-schema', schema: S({total_entries:{type:'number'}, fields:{type:'array'}, variants:{type:'array'}, parse_strategy:{type:'string'}, unparseable_pct:{type:'number'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) の「作品・シリーズ・話」の構造モデルを確定せよ(WordPress のコンテンツモデル設計のため)。
調査: (1) series.html のシリーズ一覧構造をパース(シリーズ名→各話リンク?)。(2) novel/YYYYMM/DDHHMMSS/ ディレクトリは作者の投稿単位で複数作品が同居する(例: novel/201212/18232308/ に calendargirl.htm と twins01-03.htm)。ディレクトリ数・1ディレクトリあたりファイル数分布を集計。(3) 同一作品の連載話が別ディレクトリに散る例 (d_upboy 等) を確認。(4) novel/ 直下のフラット時代ファイル(旧世代)の扱い。(5) lib-index-*.html (五十音) と bunrui.html/genre.html の索引構造。
返答: シリーズ表現の実態、作品(work)を一意に定める鍵の提案(目録エントリ vs ファイル)、統計。`,
    {label: 'work-model', schema: S({series_structure:{type:'string'}, dir_stats:{type:'string'}, work_identity:{type:'string'}, flat_era:{type:'string'}, indexes:{type:'string'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) の分類語彙を全数抽出せよ(WordPress タクソノミー設計のため)。
全 lib*.html (utf-8) から【ジャンル】【種別】【キーワード】の値を Python で全数抽出し、スペース区切りをトークン化して頻度集計。上位50件ずつと、総異なり数・表記揺れ(例: 全角/半角・類義)を報告。ジャンル/種別が閉じた語彙(選択式)か自由記述かを判定。キーワードの自由度も評価。`,
    {label: 'taxonomies', schema: S({genres:{type:'array'}, types:{type:'array'}, keywords_top:{type:'array'}, vocab_assessment:{type:'string'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) の作者インベントリを作れ(WordPress の作者アーカイブ設計のため)。
全 lib*.html から作者名を全数抽出・頻度集計(上位30と総数)。~ts/kansou/ の bbs@log_*.cgi ファイル一覧から作者別感想板の slug 一覧を取得し、目録の感想リンクとの対応を確認。作者の Homepage リンクの生存性は調べなくてよいが URL パターンを分類(nifty/geocities等)。注意: mailto アドレスは個人情報 — 公開サイトでの扱いの論点として件数だけ報告。`,
    {label: 'authors', schema: S({author_count:{type:'number'}, top_authors:{type:'array'}, kansou_logs:{type:'number'}, homepage_patterns:{type:'array'}, pii_note:{type:'string'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) のコミュニティ層を調査せよ(WordPress 移行での扱い判断のため)。
対象: ~ts/kansou/ (作品感想板: bbs@log_*.cgi と bbs@res_*.cgi の件数・1ファイルの構造 — 投稿者/日付/本文が抽出可能か1-2ファイル読んで判定)、~ts/bbs/ (雑談・2ndbbs)、ts.novels.name/rounge/ (ラウンジBBS スレッド数)、~ezpe/cgi-bin/noteky/ (推薦ノート)、~yays/cgi-bin/ (resbbs/paintbbs)、comittee/ columns/ dialy/ (運営コンテンツ)。
各: ファイル数・内容の性質・「感想をWPコメントとして作品に紐付けるインポート」の実現性(構造化パース可能性)を評価。`,
    {label: 'community', schema: S({kansou:{type:'string'}, bbs:{type:'string'}, rounge:{type:'string'}, noteky:{type:'string'}, importability:{type:'string'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) の世代構造と重複を調査せよ(WordPress サイトの情報設計のため)。
世代: ~ezpe (1999 tomato期) → ~yays + ~yays/library (八重洲メディアリサーチ期) → リポジトリ直下 (ts.novels.jp 本体 2002-2021) → ts.novels.name/kirika.novels.name/ts.raa0121.info (閉鎖後姉妹) → ts-novels.jp (2018再建)。各世代のファイル数・novel系コンテンツの重複度(~yays/library/novel/ と novel/ で同一basename数を集計)・世代固有コンテンツ(galleryのCG等)を報告。「ライブラリ本体(WP化)」と「史料アネックス(静的のまま)」の境界の提案も。`,
    {label: 'generations', schema: S({generation_stats:{type:'string'}, duplication:{type:'string'}, unique_per_gen:{type:'string'}, boundary_proposal:{type:'string'}})}),
  () => agent(`${ROOT} (少年少女文庫ミラー) の本文 HTML の形式品質を調査せよ(WordPress 本文変換パイプライン設計のため)。
novel/ 配下の .htm/.html を10-15ファイル無作為抽出して読み、判定: 文書構造(手書きHTML? テンプレ? framesets?)、本文マークアップ(<BR>連打? <P>? ルビ<ruby>? 縦書き指定?)、装飾(FONT/色/中央寄せ)、挿絵の参照方法、ナビゲーション(次話/目次リンク)、既にUTF-8化済みであること。Gutenberg ブロックへの変換戦略(本文抽出→整形)の難易度と、自動抽出できる範囲(タイトル/本文境界)を評価。`,
    {label: 'content-format', schema: S({structure:{type:'string'}, markup_patterns:{type:'array'}, conversion_difficulty:{type:'string'}, auto_extractable:{type:'string'}})}),
  () => agent(`閉鎖TS小説サイト「少年少女文庫」の復元ミラー ${ROOT} (README.md 参照) を WordPress の公開ライブラリにする際の、権利・倫理・運用の論点を整理せよ。
README を読み、以下を検討: (1) 各作品の著作権は原著者(サイトは資料保存目的の非営利アーカイブ)— WP化(=再編集・再公開の色が強まる)で追加で配慮すべき点、削除依頼への備え。(2) 目録に mailto と個人サイトURLが露出 — マスクすべきか。(3) BBS投稿者名・メールの扱い。(4) 検索エンジンへの露出方針(noindex?)。(5) 既存 GitHub Pages ミラーとの併存戦略。あなたの結論を推奨として明確に。`,
    {label: 'rights-ops', schema: S({copyright:{type:'string'}, pii:{type:'string'}, seo:{type:'string'}, coexistence:{type:'string'}, recommendations:{type:'array'}})}),
])
return Object.fromEntries(['catalog','workModel','taxonomies','authors','community','generations','contentFormat','rightsOps'].map((k,i)=>[k, results[i]]))