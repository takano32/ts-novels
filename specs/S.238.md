# S.238 作品クラスタの手動修正の実装と slug の変化 (T.24.1〜T.24.3 の記録)

- 種類: 記録
- 状態: 有効
- 出典: 2026-10-30 の作業 (T.24.1〜T.24.3)。比較の基準は HEAD 7d4778f4 の `catalog/works.jsonl`
- 関連: T.24.1, T.24.2, T.24.3, T.24.6, S.146〜S.151, S.154, S.210, S.218

## 実装したもの (2026-10-30)

- **T.24.1** `scripts/wp/work_builder.py` が `catalog/work_overrides.yml` の `split` / `detach` / `merge_into` / `confirmed` を読む (S.146)。当てる順は `merge_into` → `split` → `detach` (S.147)。当てた作品の `evidence` に `override` が入る。検証は S.146 の 6 項目に加えて、次を誤りとして止める (誤りがあれば catalog を書かず終了コード 1): 知らない項目 / 鍵に当たるクラスタが 0 件か 2 件以上 (機械の題名が同じ別クラスタがあるとき) / 指示に挙げた話がそのクラスタの話でない / `merge_into` の先が自分自身・先もまた `merge_into` を持つ (連鎖) / `merge_into` と `split`・`detach` を同じ鍵に書く。`split` と `detach` でできた作品は人手で分けたものなので `needs_review` を立てない。
  - 既存の work_overrides.yml (雛形 368 件、`confirmed: true` 0 件) で `make catalog` の出力は差分 0 (works.jsonl・work_builder.json・slug_overrides.yml・work_overrides.yml・QA.md)。その後、work_overrides.yml の冒頭の説明に version 2 の書き方を足した (説明行だけの差分)。
  - 試験 (作業用の一時ファイル、リポジトリには置いていない): 実データの 1 作品に split 2 話 + detach 1 話 + confirmed で works +2・話数不変・orphan 0、別の作品の merge_into で works −1。誤りの 10 種 (実在しない話・同じ話が 2 指示・作者違いの併合・自分自身へ併合・併合先が無い・split の題名が空・知らない項目・クラスタに無い話・鍵が無い・連鎖) をすべて検出。T.24.3 のレビュー資料の一次案 71 件をそのまま当てても誤り 0・orphan 0 (works 1,491・needs_review 279 になる。リポジトリには当てていない)。
- **T.24.2** 題名の芯の規則 (S.148) に 第 N 回 / Lesson N / EPISODE N (`Episode #00_02` の形も) / stage / その N / 其の N / № N / No. N を足した。S.148 に無いが同じ趣旨で、巻号の数字に大字 (壱弐参…) と 〇・零 を足した (「プロフェッサーＫ 第壱話」「カオルの日常 第弐話」が「第」で切れていたため)。stage は数字つき (`stage 3`・`1st stage`) に限った (原本の形は「Experience 1st stage」で、「Back to stage」は巻号ではないため)。英語の語は直前が英字でないときだけ巻号と見る。
  - 結果: 連載の作品題名で末尾が助数詞のものは 13 件 → 0 件 (数えた語は 第・その・其の・回・Lesson・EPISODE・stage・No・№)。単発の作品 2 件 (「ジェネシス 第１回」「必殺鬼哭の剣第1話：…の回」) は話の題名をそのまま作品名にする規則 (S.175) なので対象外。
  - 題名の芯は規則 6 (題名の一致) の鍵でもあるので、クラスタも変わった: works **1,451 → 1,440** (単発 932 → 920 / 連載 519 → 520)、needs_review 331 → 333 (weak 272・multi-dir 39・large 22)。
  - **slug が変わった作品は 28** (うち他の作品と束ね直されたもの 16)。ほかに slug は変わらず別の作品を吸収した作品が 4 (`johdan-toraburu-supirittsu`・`marie-imajin`・`mikan_ame-hanamoriranmarukun`・`kotobuki_hikaru_ndcss-nazonoyokokucg`)。数え方は S.148 の slug_overrides.yml の差分ではなく、話の所属で旧 works と新 works を突き合わせた (slug_overrides.yml の鍵は機械の題名を含むので、題名が変わると鍵ごと入れ替わり、差分では対応が取れないため)。
- **T.24.3** `scripts/wp/cluster_review.py` → `catalog/review/clusters-2026-10.md`。71 作品 (弱い根拠 39 が先頭・強い根拠 32)。S.154 の基準値と同じ件数 (T.24.2 の後のデータで数えた)。一次案は、強い根拠のある作品は ○、弱い根拠だけの作品は芯の組ごとに分割・切り離し。S.151 の `--redirects` は T.24.6 で作る (まだ無い)。`--since <リビジョン>` で slug の変化の表を末尾に足せる。

## slug が変わった作品 (旧 slug → 新 slug)

本番に入っている (2026-08-31 投入) 作品の URL が、T.24.2 の catalog を投入すると変わる候補。T.24.6 の付け替え表は本番の `export-pathmap` の差分で作るので、この表はその下調べ (話の URL も作品の slug の下にあるので一緒に変わる)。T.24.5 の裁定の転記でさらに変わる。

| 旧 work_slug | 新 work_slug | 旧題名 | 新題名 | 話数 (旧 → 新の作品) |
|---|---|---|---|---|
| `7cy-suukina-ikaryaku-episode` | `7cy-suukina-ikaryaku` | 数奇な(...以下略) Episode | 数奇な(...以下略 | 3 → 3 |
| `ainishisu-purofessaak-dai` | `ainishisu-purofessaak` | プロフェッサーK 第 | プロフェッサーK | 3 → 3 |
| `anoyoroshi-hantaasutooriirando-alphasono6-shimakuninoshirozake` | `anoyoroshi-hantaasutooriirando-alpha` | ハンターストーリーランド＋αその６　「島国の白酒」 | ハンターストーリーランド+α | 3 → 3 |
| `johdan-toraburu-supirittsu-episode3-henten` | `johdan-toraburu-supirittsu` | とらぶる☆すぴりっつ EPISODE3「変転」 | とらぶる☆すぴりっつ | 1 → 11 |
| `kamikawa_ayano-hontounojibun-dai` | `kamikawa_ayano-hontounojibun` | 本当の自分 第 | 本当の自分 | 4 → 6 |
| `kamikawa_ayano-hontounojibun-dai-2` | `kamikawa_ayano-hontounojibun` | 本当の自分 第 | 本当の自分 | 2 → 6 |
| `kion_rei-kizuna` | `kion_rei-kizuna-midochannoyuuutsu` | 絆 | きずな 〜ミドちゃんの憂鬱 | 4 → 6 |
| `kion_rei-kizuna-2` | `kion_rei-kizuna-midochannoyuuutsu-2` | 絆 | きずな ~ミドちゃんの憂鬱 | 4 → 6 |
| `kion_rei-kizuna-midochannoyuuutsu-dainihanashi-yurinohanayome` | `kion_rei-kizuna-midochannoyuuutsu` | きずな 〜ミドちゃんの憂鬱〜　第弐話「百合の花嫁」 | きずな 〜ミドちゃんの憂鬱 | 1 → 6 |
| `kion_rei-kizuna-midochannoyuuutsu-dainihanashi-yurinohanayome-2` | `kion_rei-kizuna-midochannoyuuutsu-2` | きずな ～ミドちゃんの憂鬱～ 第弐話「百合の花嫁」 | きずな ~ミドちゃんの憂鬱 | 1 → 6 |
| `kion_rei-kizuna-midochannoyuuutsu-daisanhanashi-baranoteien` | `kion_rei-kizuna-midochannoyuuutsu` | きずな 〜ミドちゃんの憂鬱〜　第参話「薔薇の庭園」 | きずな 〜ミドちゃんの憂鬱 | 1 → 6 |
| `kion_rei-kizuna-midochannoyuuutsu-daisanhanashi-baranoteien-2` | `kion_rei-kizuna-midochannoyuuutsu-2` | きずな ～ミドちゃんの憂鬱～ 第参話「薔薇の庭園」 | きずな ~ミドちゃんの憂鬱 | 1 → 6 |
| `kotobuki_hikaru_ndcss-nazonoyokokucg-sono2` | `kotobuki_hikaru_ndcss-nazonoyokokucg` | 謎の予告CG　その２ | 謎の予告CG | 1 → 2 |
| `marie-imajin-dai` | `marie-imajin` | イマジン 第 | イマジン | 6 → 7 |
| `mikan_ame-hanamoriranmarukun-sono2` | `mikan_ame-hanamoriranmarukun` | 華森蘭丸くん（その２） | 華森蘭丸くん | 1 → 2 |
| `muck-magical-twins-dai` | `muck-magical-twins` | MAGICAL TWINS 第 | MAGICAL TWINS | 4 → 4 |
| `nekono_gundam-kurosu-akoodo-bangaihen1` | `nekono_gundam-kurosu-akoodo` | くろす・あこーど　番外編１ | くろす・あこーど | 1 → 5 |
| `nekono_gundam-kurosu-akoodo-episode` | `nekono_gundam-kurosu-akoodo` | くろす・あこーど episode | くろす・あこーど | 4 → 5 |
| `panda5-ainopanda5tsgekijousono` | `panda5-ainopanda5tsgekijou` | 愛のパンダ5TS劇場その | 愛のパンダ5TS劇場 | 4 → 4 |
| `ruhfasu-irregular-gir` | `ruhfasu-irregular-girl` | Irregular Gir | Irregular Girl | 13 → 13 |
| `rurita-medarottom-episode` | `rurita-medarottom` | メダロットM Episode | メダロットM | 3 → 3 |
| `sato-kaorunonichijou-dainihanashi` | `sato-kaorunonichijou` | カオルの日常 第弐話 | カオルの日常 | 3 → 3 |
| `unattributed-tskyanonbooru-kakorogu-sono` | `unattributed-tskyanonbooru-kakorogu` | TSキャノンボール!」過去ログ(その | TSキャノンボール!」過去ログ | 7 → 7 |
| `yamu-misutoresutokimyounanakamatachi-sono` | `yamu-misutoresutokimyounanakamatachi` | ミストレスと奇妙な仲間達〜その | ミストレスと奇妙な仲間達 | 39 → 40 |
| `yamu-misutoresutokimyounanakamatachi-sono11-kaiteiban` | `yamu-misutoresutokimyounanakamatachi` | ミストレスと奇妙な仲間達〜その11（改訂版） | ミストレスと奇妙な仲間達 | 1 → 40 |
| `yamu-sanhimejunyuuki-ryuutouhimehensono` | `yamu-sanhimejunyuuki-ryuutouhimehen` | 三姫巡遊記 流闘姫編その | 三姫巡遊記 流闘姫編 | 2 → 2 |
| `zyuka-hantaasutooriirando-alphasono` | `zyuka-hantaasutooriirando-alpha` | ハンターストーリーランド+αその | ハンターストーリーランド+α | 5 → 5 |
| `zyuka-metamoru` | `zyuka-metamoru-vanpaia` | メタモル | メタモル・ヴァンパイア | 3 → 3 |
