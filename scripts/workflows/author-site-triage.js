export const meta = {
  name: 'author-site-triage',
  description: '喪失作品195件×51作者のアーカイブ済みホームページ精査 → 発見分を敵対的検証',
  phases: [
    { title: 'Triage', detail: '作者単位でアーカイブ済みサイトを精査' },
    { title: 'Verify', detail: '発見主張の本文検証' },
  ],
}
const SP = '/tmp/claude-1000/-home-takano32-GitHub-ts-novels/9875998b-bd34-4f69-aae3-1a7a76884c0b/scratchpad'
const ROOT = '/home/takano32/GitHub/ts-novels'
const authors = args.authors
const big = authors.filter(a => a.lost.length >= 5)
const small = authors.filter(a => a.lost.length < 5)
const groups = [...big.map(a => [a])]
for (let i = 0; i < small.length; i += 4) groups.push(small.slice(i, i + 4))
log(`authors=${authors.length} groups=${groups.length}`)
const FIND_SCHEMA = {type:'object', properties:{findings:{type:'array', items:{type:'object', properties:{
  target:{type:'string'}, title:{type:'string'}, status:{type:'string', description:'found-staged / candidate / not-on-site / no-archive'},
  source_url:{type:'string'}, staged_path:{type:'string'}, evidence:{type:'string'}}, required:['target','status']}},
  site_notes:{type:'string'}}, required:['findings']}
const results = await pipeline(groups,
  (g, _o, gi) => agent(`あなたは閉鎖TS小説サイト「少年少女文庫」復元プロジェクトの調査員。担当作者の喪失作品を、作者のアーカイブ済みホームページから回収できるか徹底調査せよ。

# 担当作者と喪失作品 (target=リポジトリ欠落パス, title=目録上の題名)
${JSON.stringify(g)}

# 使える資料・道具
- ${SP}/author_cdx.json — 作者ホームページ175件の Wayback CDX 一括スイープ結果 (進行中の場合あり。担当作者の homepage が載っていなければ自分で CDX API を叩く: https://web.archive.org/cdx/search/cdx?url=<URL>&matchType=prefix&output=text&collapse=urlkey&fl=original,timestamp,statuscode,length&filter=statuscode:200)
- homepage が空の作者は、リポジトリ内の目録 (${ROOT}/lib*.html) や作品ページ・BBSから本人サイトURLの手掛かりを探してよい (mailto のドメインから個人サイトを推定する等は不可・実在リンクのみ)
- Wayback の原文取得は https://web.archive.org/web/<ts>id_/<URL> (raw モード)
- 特記: 作者「猫野丸太丸」= かなうたP = うたのつき の可能性が高い (感想板slug=utanotsuki)。当プロジェクトは本人の移転先 www.aetherworks.org を ${ROOT}/aetherworks.org/ に回収済み — まずそこ (小説作品ページ index@BEAEC0E2.html 等) を調べよ
- ライターマン (slug=delayed) は coara.or.jp 系サイトが2021年まで存在。バレット/旅わんこ は なろう mypage/285097・Novelabo author/3213 に再掲載歴 — Web上の再掲載ページも調査対象 (ただし本人への連絡はしない)

# 禁止事項 (過去ラウンドで枯渇確認済み — 再照会は時間の無駄)
- megalodon.jp・timetravel.mementoweb.org・archive.today への照会
- ts.novels.jp 系ドメイン自体の Wayback 再照会 (欠落はCDX全ダンプ照合済み)

# やること
1. 担当作者ごとに、アーカイブ済みホームページの捕獲URL一覧を入手し、サイト構造 (作品目次・小説置き場) を Wayback で読む
2. 喪失作品の題名・basename・話数に対応するファイルを探す (ファイル名が文庫と違う可能性に注意 — 題名で判断)
3. 見つけたら raw モードで取得し ${SP}/stage_authors/<target のリポジトリパス> に保存 (バイト無加工)。**必ず結果 JSON に source_url を記録** (scratchpad は消えることがある)
4. 各喪失作品に verdict を付ける: found-staged (取得済) / candidate (存在の兆候あり・要追加調査、evidence に根拠) / not-on-site (サイトはあるが作品なし) / no-archive (サイトのアーカイブ自体なし)

過去に本プロジェクトが回収済みの作品を「発見」と誤報しないこと — ${ROOT} に該当ファイルが既にあれば対象は「欠落パス」だけ。`,
    {label: `triage:${g.map(a=>a.author).join('+').slice(0,24)}`, phase: 'Triage', schema: FIND_SCHEMA}),
  (res, g) => {
    if (!res || !res.findings) return {group: g.map(a=>a.author), verified: [], raw: res}
    const finds = res.findings.filter(f => f.status === 'found-staged' && f.source_url)
    if (!finds.length) return {group: g.map(a=>a.author), findings: res.findings, verified: []}
    return parallel(finds.map(f => () =>
      agent(`敵対的検証: 以下の「喪失作品を発見した」という主張を疑って検証せよ。
主張: ${JSON.stringify(f)}
確認手順: (1) ${SP}/stage_authors/${f.staged_path || f.target} が存在し中身が読めるか (無ければ source_url ${f.source_url} を自分で取得して確認)
(2) 中身が本当に小説本文か (エラーページ/目次/別作品でないか)。題名「${f.title}」・話数と整合するか
(3) リポジトリ ${ROOT} に同内容が既収蔵でないか (basename検索+内容比較)
判定が少しでも怪しければ verified=false。`,
        {label: `verify:${(f.title||f.target).slice(0,20)}`, phase: 'Verify',
         schema: {type:'object', properties:{verified:{type:'boolean'}, reason:{type:'string'}}, required:['verified','reason']}})
      .then(v => ({...f, verified: v ? v.verified : false, verify_reason: v ? v.reason : 'verifier-died'}))
    )).then(vs => ({group: g.map(a=>a.author), findings: res.findings, verified: vs}))
  })
const flat = results.filter(Boolean)
const found = flat.flatMap(r => (r.verified||[]).filter(v => v.verified))
const candidates = flat.flatMap(r => (r.findings||[]).filter(f => f.status === 'candidate'))
log(`verified finds=${found.length} candidates=${candidates.length}`)
return {groups: flat, verified_finds: found, candidates}