export const meta = {
  name: 'wp-library-design',
  description: '少年少女文庫 WordPress ライブラリの設計案パネル: 4独立案 → 3審査員 → 統合',
  phases: [
    { title: 'Design', detail: '4つの独立設計案' },
    { title: 'Judge', detail: '3審査員による採点' },
    { title: 'Synthesize', detail: '勝者ベースの統合設計' },
  ],
}
const BRIEF = `# 課題
閉鎖TS小説サイト「少年少女文庫」(1999-2021, 復元ミラー /home/takano32/GitHub/ts-novels, GitHub Pages で静的公開中) を
WordPress 上の「モダンで体系的な作品ライブラリ」として設計する。個人運営(takano32)、非営利の資料保存目的。

# 事前調査の結果 (8視点の実測データ)
まず必ず ${args.surveyPath} を Read し、8視点調査(目録スキーマ・作品モデル・分類語彙・作者・コミュニティ層・世代構造・本文形式・権利/運用)の実測データを全て把握してから設計せよ。ファイルは大きいので分割して読むこと。

# 設計に必ず含める項目
1. コンテンツモデル: 投稿タイプ/階層(作品・話・シリーズ)、タクソノミー設計(ジャンル/種別/キーワード/作者/世代)、post meta (原URL・出典アーカイブ・回収経路・当時の日付/サイズ)
2. インポートパイプライン: 目録2,977エントリのパース→WP投入(WXR/WP-CLI/REST)、冪等な再実行、本文HTML→ブロック変換方針、挿絵/画像の扱い
3. 情報設計とURL: トップ/作品/作者/シリーズ/索引(五十音/ジャンル/年代)/検索、パーマリンク設計
4. 読書体験: テーマ方針、リーダーUI(文字サイズ・ダーク・縦書きの要否)、話ナビ、原本(静的ミラー)への相互リンク
5. コミュニティ層の扱い: 当時の感想板ログ・推薦文・オススメ作品グラフの見せ方、新規コメントの可否
6. 静的ミラーとの併存: WP化する範囲と静的アネックスに残す範囲の境界、リンク戦略
7. 権利・PII・SEO: mailto除去、削除依頼窓口、noindex方針
8. 運用: ホスティング想定、プラグイン最小構成、バックアップ、性能(キャッシュ/静的化)
9. 実装ロードマップ: フェーズ分割と各フェーズの成果物、既存 scripts/ 資産の再利用

# 出力
上記9項目を全てカバーする具体的で実装可能な設計。抽象論でなく、CPT名・タクソノミー名・メタキー・URL例・使用プラグイン名まで書く。`
phase('Design')
const APPROACHES = [
  {key: 'preservation', stance: '保存忠実性ファースト: 原本の見た目と構造の保存を最優先し、WordPress は目録・発見性のレイヤーに徹する。本文は原本HTMLを尊重。'},
  {key: 'reader-ux', stance: '読書体験ファースト: 現代の Web 小説サイト (なろう/カクヨム/AO3) 水準のリーダーUXを最優先。本文は再組版してブロック化。'},
  {key: 'data-model', stance: '正準データモデルファースト: 目録メタデータを正規化した単一の真実源とし、すべての表示・索引・リンクをそこから導出する。移行の冪等性と将来の再構築可能性を最優先。'},
  {key: 'ops-simple', stance: '運用シンプルファースト: 個人が10年放置しても壊れない構成を最優先。プラグイン最小・静的書き出し・依存最小。'},
]
const DESIGN_SCHEMA = {type:'object', properties:{summary:{type:'string'}, content_model:{type:'string'}, import_pipeline:{type:'string'}, ia_urls:{type:'string'}, reading_ux:{type:'string'}, community:{type:'string'}, coexistence:{type:'string'}, rights:{type:'string'}, operations:{type:'string'}, roadmap:{type:'string'}, tradeoffs:{type:'string'}}, required:['summary','content_model','import_pipeline','ia_urls','reading_ux','community','coexistence','rights','operations','roadmap']}
const designs = await parallel(APPROACHES.map(a => () =>
  agent(`${BRIEF}\n\n# あなたの設計スタンス (これを貫け)\n${a.stance}\n\n他の観点も9項目すべて具体的に埋めること。スタンスは優先順位の付け方であって項目の省略理由ではない。`,
    {label: `design:${a.key}`, phase: 'Design', schema: DESIGN_SCHEMA, effort: 'high'})))
const named = designs.map((d, i) => ({key: APPROACHES[i].key, stance: APPROACHES[i].stance, design: d})).filter(x => x.design)
phase('Judge')
const JUDGE_SCHEMA = {type:'object', properties:{scores:{type:'array', items:{type:'object', properties:{key:{type:'string'}, fidelity:{type:'number'}, ux:{type:'number'}, feasibility:{type:'number'}, maintainability:{type:'number'}, rights_safety:{type:'number'}, total:{type:'number'}, critique:{type:'string'}}}}, winner:{type:'string'}, best_ideas_from_losers:{type:'array', items:{type:'string'}}}, required:['scores','winner','best_ideas_from_losers']}
const judges = await parallel(['アーカイブ司書(資料保存の専門家)', 'Web小説サイトのプロダクトデザイナー', 'WordPress 実装を10年保守してきたエンジニア'].map(role => () =>
  agent(`あなたは${role}。以下の4つの WordPress ライブラリ設計案を審査せよ。\n\n課題背景: 閉鎖TS小説サイト復元ミラー(17,218ファイル・目録2,977エントリ)の WordPress ライブラリ化。個人運営・非営利資料保存。\n\n${JSON.stringify(named)}\n\n各案を fidelity(保存忠実性)/ux(読書・発見体験)/feasibility(実装現実性)/maintainability(10年保守)/rights_safety(権利・PII) の5軸で1-10点採点し、total を出し、勝者を選び、敗者からも取り込むべき最良のアイデアを列挙せよ。辛口で具体的に。`,
    {label: `judge:${role.slice(0,12)}`, phase: 'Judge', schema: JUDGE_SCHEMA, effort: 'high'})))
phase('Synthesize')
const synthesis = await agent(`${BRIEF}\n\n# 4つの独立設計案\n${JSON.stringify(named)}\n\n# 3人の審査員の採点と講評\n${JSON.stringify(judges.filter(Boolean))}\n\n審査結果を踏まえ、勝者案を土台に敗者案・審査員指摘の最良要素をすべて取り込んだ最終統合設計を作れ。9項目すべてを、そのまま実装に着手できる具体度(CPT名・タクソノミー slug・メタキー・URL構造・プラグイン名・パイプラインのスクリプト構成・フェーズ別ロードマップ)で書け。審査で割れた論点は「決定」とその根拠を明記。ユーザー(サイト所有者)が選ぶべき残論点は「要決定事項」として選択肢+推奨付きで分離せよ。`,
    {label: 'synthesis', phase: 'Synthesize', effort: 'max', schema: {type:'object', properties:{final_design_md:{type:'string', description:'最終統合設計の完全な Markdown 本文(日本語)'}, decisions:{type:'array', items:{type:'string'}}, open_questions:{type:'array', items:{type:'string'}}}, required:['final_design_md','decisions','open_questions']}})
return {designs: named, judges: judges.filter(Boolean), synthesis}