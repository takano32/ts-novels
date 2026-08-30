export const meta = {
  name: 'repost-hunt',
  description: '喪失作品のWeb転載・再掲載を検索横断で探索 → 発見分を検証',
  phases: [
    { title: 'Hunt', detail: '作品系列ごとにWeb検索+掲示板過去ログ探索' },
    { title: 'Verify', detail: '発見の本文実在検証' },
  ],
}
const QUARRY = [
  {key: 'vistia', author: 'ほたる', works: 'ヴィスティア chapter 07/08/10/12〜16・番外編・リンクの妖精', notes: '作者は連絡先非公開。ヴィスティア09は魚拓から回収済 (再発見不要)'},
  {key: 'dislike', author: 'おもちばこ', works: 'きらいなもの→ 第3,7〜16,18,20話・雨女', notes: '作者は pixiv userid 1888568 / X @omochibako で活動歴。5/17話は回収済'},
  {key: 'dualworld', author: 'バレット(旅わんこ)', works: 'デュアルワールド 全楽章・リライト・AG/アナログ所さん各話', notes: 'なろう mypage/285097・Novelabo author/3213 に再掲載歴 — 全作品リストを照合し、デュアルワールド系・アナログ所さん系の再掲載有無を網羅確認せよ'},
  {key: 'warasi', author: 'なまけもの', works: 'ざしきわらし 二〜十四 (全13話)', notes: ''},
  {key: 'delayed', author: 'ライターマン/MONDO', works: '仮面ライターディレイド 第2/3/6/7話・機甲天女ヴァルキュリア Scramble7/8・天女の末裔', notes: 'ライターマンは www3.coara.or.jp/~kagawa/writerman/ (2021終了)'},
  {key: 'kirika', author: 'きりか進ノ介', works: 'ザ・ゴールデンロード2〜4・フレグラント各話・ガラスの靴', notes: '現ドメイン bc-cafe.net が生存 — サイト内も探索対象 (連絡はしない)'},
  {key: 'lgmb', author: 'いとう', works: '少年以上、少女未満 第七〜十一話', notes: ''},
  {key: 'nekono', author: '猫野丸太丸', works: 'こるり改訂版3,5〜8・二代目はダークエルフ・KAI・KEN・GO 巻の3・くろす・あこーど ep2', notes: 'gnekono 名義。www.lain.ais.ne.jp/~gnekono/ が旧サイト'},
  {key: 'goodlife', author: '雅良生・英雄・無糖', works: 'グッド・ライフ4〜6 / 錦繍の夢(前編)・黄金色の夜(完結編) / いんげんさん第3後編〜第4話', notes: ''},
  {key: 'roam7cy', author: 'ろーむ・７Ｃｙ', works: '仲良き家族は美しきかな? Part6/7/あふたー1〜3 / 大祝言・大兄妹物語・大・闇への追儺', notes: ''},
  {key: 'yamu-misc', author: '夜夢・龍酒・泉美樹', works: 'ミストレスと奇妙な仲間達 21/22/NG・ヴァンゼール騒動記2 / NOVEL4 / XX-FILES ver.2・夢魔狩り', notes: '夜夢は www16.ocn.ne.jp/~kariyado'},
  {key: 'singles-a', author: '鈴忌紫・泉谷パーム・南文堂・よしおか', works: 'エクス・マキナ第十二話 / 狂魂3 / 鏡の国の静香 / ＴＳ戒', notes: 'geocities.jp/izumiyapaamu は IA に閉鎖時グラブあり'},
  {key: 'singles-b', author: 'ハクリ・にわたたみ・鴨南蛮・スッス・タッチャン', works: '魔法世界を創ろう！② / ロボ娘は電気羊の夢をみる？第2話 / ジェネシス第1回 / グランス・タスファクト最終奏 / 小悪魔とマッドサイエンティスト', notes: ''},
]
const SP = '/tmp/claude-1000/-home-takano32-GitHub-ts-novels/9875998b-bd34-4f69-aae3-1a7a76884c0b/scratchpad'
const SCHEMA = {type:'object', properties:{finds:{type:'array', items:{type:'object', properties:{
  work:{type:'string'}, where:{type:'string'}, url:{type:'string'}, kind:{type:'string', description:'full-text / partial / mention-only'},
  staged_path:{type:'string'}, evidence:{type:'string'}}, required:['work','url','kind']}},
  searched:{type:'string'}}, required:['finds','searched']}
const results = await pipeline(QUARRY,
  q => agent(`あなたは閉鎖TS小説サイト「少年少女文庫」(ts.novels.jp, 1997-2021) 復元プロジェクトの探索員。以下の喪失作品の**本文転載・再掲載**が Web 上のどこかに残っていないか徹底捜索せよ。

# 捜索対象
作者: ${q.author}
作品: ${q.works}
補足: ${q.notes}

# 捜索手段 (すべて使う)
1. WebSearch: 「作品名 作者名」「作品名 少年少女文庫」「作品名 TS」等の組み合わせ (日本語)。表記揺れも試す
2. 小説投稿サイトの作者内検索: なろう(api.syosetu.com の userid 検索可)・pixiv・Novelabo・カクヨム・NOVEL DAYS — 作者の再掲載を作品リストレベルで確認
3. 2ch/5ch 過去ログ (TSF/性転換小説スレ等): WebSearch で site:mimizun.com や「スレ タイトル 作品名」を検索。全文転載・長文引用の有無
4. 個人ブログ・まとめサイトでの転載

# 判定基準
- kind=full-text: 本文全体が読める (これだけが回収価値あり)
- kind=partial: 一部引用のみ
- kind=mention-only: 言及のみ (記録として1-2件まで)
- full-text を見つけたら内容を取得し ${SP}/stage_repost/<作品を表すファイル名> に保存し、**結果に必ず url を記録**
- 感想・レビュー・タイトル一覧は本文ではない。誤認するな

# 禁止
- 作者本人・関係者への連絡や書き込み
- megalodon.jp / timetravel / archive.today の照会 (枯渇確認済み)
- 少年少女文庫自体のミラー (takano32.github.io や web.archive.org の ts.novels.jp) を「発見」と報告すること`,
    {label: `hunt:${q.key}`, phase: 'Hunt', schema: SCHEMA}),
  (res, q) => {
    if (!res) return null
    const ft = res.finds.filter(f => f.kind === 'full-text')
    if (!ft.length) return {key: q.key, finds: res.finds, verified: []}
    return parallel(ft.map(f => () =>
      agent(`敵対的検証: 「喪失作品の全文転載を発見した」という以下の主張を疑って検証せよ。
${JSON.stringify(f)}
手順: url を実際に取得し、(1) 本文が実在し全文か (2) 対象作品と同一か (題名・作者・内容) (3) 少年少女文庫ミラー自体や既回収コンテンツの再発見でないか (/home/takano32/GitHub/ts-novels に既存確認)。怪しければ false。`,
        {label: `verify:${f.work.slice(0,16)}`, phase: 'Verify',
         schema: {type:'object', properties:{verified:{type:'boolean'}, reason:{type:'string'}}, required:['verified','reason']}})
      .then(v => ({...f, verified: v ? v.verified : false, verify_reason: v ? v.reason : 'verifier-died'}))
    )).then(vs => ({key: q.key, finds: res.finds, verified: vs}))
  })
const flat = results.filter(Boolean)
const confirmed = flat.flatMap(r => (r.verified||[]).filter(v => v.verified))
log(`confirmed full-text reposts: ${confirmed.length}`)
return {results: flat, confirmed}