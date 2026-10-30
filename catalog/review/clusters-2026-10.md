# 作品クラスタのレビュー (2026-10)

`scripts/wp/cluster_review.py` の出力 (仕様は specs/S.149.md、裁定の書き方は specs/S.150.md)。手で直さず、作り直すときはスクリプトを走らせる。裁定はこのファイルの写しか返信でもらい、`catalog/work_overrides.yml` に転記する (T.24.5)。

- 対象: 連載 (2 話以上) のうち、話の題名の芯が 2 種以上あり、芯どうしの共通接頭辞が 2 文字未満の作品
- 件数: **71 作品** (根拠が規則 5/6 だけ = 弱い根拠 39 を先頭に、強い根拠のあるもの 32 を後に)
- 一次案は芯ごとの機械の振り分けで、裁定ではない。強い根拠のある作品は ○、弱い根拠だけの作品は話数の最も多い芯の組を残し、他の組を分割 (2 話以上) か切り離し (1 話) にしている

## 弱い根拠だけの作品 (39)

### 1. `a_mode-rental-body-umiderb` — RENTAL BODY: 海でRB

- 根拠: `filename-stem` / 話数: 2 / 作者: a-mode (`a_mode`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__rental__oem__rb_beach.html` | RENTAL BODY: 海でRB | rb_beach.html | 2000-08-11 | honkan |
| `novel__rental__oem__rb_beach2.html` | 海でＲＢ−２ | rb_beach2.html | 2000-09-16 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「RENTAL BODY: 海でRB」の 1 話)
  - 切り離し → 単独の作品「海でＲＢ−２」: `novel__rental__oem__rb_beach2.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "a_mode|RENTAL BODY: 海でRB":
    confirmed: true
    detach: [novel__rental__oem__rb_beach2.html]
```

</details>

### 2. `aki_michiru-bees-saishuukai-towbeesyoeienni` — Bees!最終回「TowBeesよ永遠に」

- 根拠: `filename-stem` / 話数: 4 / 作者: 亜希みちる (`aki_michiru`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200106__16231634__bees.html` | Bees!最終回「TowBeesよ永遠に」 | bees.html | 2001-06-16 | honkan |
| `novel__200106__16231634__bees2.html` | Two Bees!「最終回又は第一回後編(笑)」 | bees2.html | 2001-06-26 | honkan |
| `novel__200106__16231634__bees3.html` | TwoBees!二回（笑）「生きている妖精」 | bees3.html | 2001-07-04 | honkan |
| `novel__200106__16231634__bees4.html` | TwoBees!三回　「眼鏡」 | bees4.html | 2001-07-22 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「Two Bees!「最終回又は」の 3 話)
  - 切り離し → 単独の作品「Bees!最終回「TowBeesよ永遠に」」: `novel__200106__16231634__bees.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "aki_michiru|Bees!最終回「TowBeesよ永遠に」":
    confirmed: true
    detach: [novel__200106__16231634__bees.html]
```

</details>

### 3. `anoyoroshi-hantaasutooriirando-alpha` — ハンターストーリーランド+α

- 根拠: `filename-stem`, `title-prefix` / 話数: 3 / 作者: あのよろし (`anoyoroshi`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__extra__hunter_story_land_a6.html` | ハンターストーリーランド＋αその６　「島国の白酒」 | hunter_story_land_a6.html | 2005-07-22 | honkan |
| `novel__kayo_chan__extra__hunter_story_land_a7.html` | ハンターストーリーランド＋αその７　「白衣の天使」 | hunter_story_land_a7.html | 2005-07-24 | honkan |
| `novel__kayo_chan__extra__hunter_story_land_a8.html` | 月白の巫女 | hunter_story_land_a8.html | 2005-08-16 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「ハンターストーリーランド+α」の 2 話)
  - 切り離し → 単独の作品「月白の巫女」: `novel__kayo_chan__extra__hunter_story_land_a8.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "anoyoroshi|ハンターストーリーランド+α":
    confirmed: true
    detach: [novel__kayo_chan__extra__hunter_story_land_a8.html]
```

</details>

### 4. `goldarm-izumotaisha-1` — 出雲大社#1

- 根拠: `filename-stem` / 話数: 3 / 作者: ゴールドアーム (`goldarm`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200012__09021621__izumo_1.html` | 出雲大社#1 | izumo_1.html | 2000-12-01 | uncatalogued |
| `novel__200012__09021621__izumo_2.html` | izumo_2 | izumo_2.html | 2000-12-01 | uncatalogued |
| `novel__200012__09021621__izumo_3.html` | 出雲大社の事件簿＃３ | izumo_3.html | 2000-12-09 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「出雲大社」の 2 話)
  - 切り離し → 単独の作品「izumo_2」: `novel__200012__09021621__izumo_2.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "goldarm|出雲大社#1":
    confirmed: true
    detach: [novel__200012__09021621__izumo_2.html]
```

</details>

### 5. `haradaseiya-hanayochanshiriizu-okujou` — 華代ちゃんシリーズ 「屋上」

- 根拠: `filename-stem` / 話数: 4 / 作者: 原田聖也 (`haradaseiya`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__kayo_chan09.html` | 華代ちゃんシリーズ 「屋上」 | kayo_chan09.html | 1999-08-19 | honkan |
| `novel__kayo_chan__kayo_chan9.html` | 屋上（華代ちゃんシリーズ） | kayo_chan9.html | 1999-08-19 | legacy |
| `novel__kayo_chan__kayo_chan13.html` | 華代ちゃんシリーズ 「固茹」 | kayo_chan13.html | 1999-08-21 | honkan |
| `novel__kayo_chan__kayo_chan23.html` | 華代ちゃんシリーズ 「短縮」 | kayo_chan23.html | 1999-08-24 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「華代ちゃんシリーズ 「屋上」の 3 話)
  - 切り離し → 単独の作品「屋上（華代ちゃんシリーズ）」: `novel__kayo_chan__kayo_chan9.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "haradaseiya|華代ちゃんシリーズ 「屋上」":
    confirmed: true
    detach: [novel__kayo_chan__kayo_chan9.html]
```

</details>

### 6. `haradaseiya-shinsoushinri` — 真相真理

- 根拠: `filename-stem` / 話数: 2 / 作者: 原田聖也 (`haradaseiya`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__lilian__lilian00.html` | 真相真理 | lilian00.html | 1999-08-06 | honkan |
| `novel__lilian__lilian01.html` | 異質戦士 | lilian01.html | 1999-08-26 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「真相真理」の 1 話)
  - 切り離し → 単独の作品「異質戦士」: `novel__lilian__lilian01.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "haradaseiya|真相真理":
    confirmed: true
    detach: [novel__lilian__lilian01.html]
```

</details>

### 7. `hibino-snow-bright` — ＳＮＯＷ　ＢＲＩＧＨＴ

- 根拠: `filename-stem` / 話数: 2 / 作者: 日比野 (`hibino`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200203__23203640__snow_bright.html` | ＳＮＯＷ　ＢＲＩＧＨＴ | snow_bright.html | 2002-07-20 | honkan |
| `novel__200203__23203640__snow_bright02.htm` | 空に咲く花 | snow_bright02.htm | 2002-08-05 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「SNOW BRIGHT」の 1 話)
  - 切り離し → 単独の作品「空に咲く花」: `novel__200203__23203640__snow_bright02.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "hibino|ＳＮＯＷ　ＢＲＩＧＨＴ":
    confirmed: true
    detach: [novel__200203__23203640__snow_bright02.htm]
```

</details>

### 8. `horii_sinobu-monogatarinotaitoru-nagaitaitoruhashouryakukeide` — 物語のタイトル(長いタイトルは省略形で)

- 根拠: `filename-stem` / 話数: 2 / 作者: 堀井　忍 (`horii_sinobu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__short__professor_k01.html` | 物語のタイトル(長いタイトルは省略形で) | professor_k01.html | — | uncatalogued |
| `novel__short__professor_k02.html` | プロフェッサー K | professor_k02.html | — | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「物語のタイトル(長いタイトルは省略形で」の 1 話)
  - 切り離し → 単独の作品「プロフェッサー K」: `novel__short__professor_k02.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "horii_sinobu|物語のタイトル(長いタイトルは省略形で)":
    confirmed: true
    detach: [novel__short__professor_k02.html]
```

</details>

### 9. `izumi_miki-gunshuunonakadenokodoku` — 群衆の中での孤独

- 根拠: `filename-stem` / 話数: 3 / 作者: 泉美樹 (`izumi_miki`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200507__09195017__all_alone_01.html` | 群衆の中での孤独 | all_alone_01.html | 2005-09-17 | honkan |
| `novel__200507__09195017__all_alone_02.htm` | 夢魔狩り | all_alone_02.htm | 2005-12-30 | honkan |
| `novel__200507__09195017__all_alone_03.htm` | TSガール・ミーツ・TSガール | all_alone_03.htm | 2006-04-25 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「群衆の中での孤独」の 1 話)
  - 切り離し → 単独の作品「夢魔狩り」: `novel__200507__09195017__all_alone_02.htm`
  - 切り離し → 単独の作品「TSガール・ミーツ・TSガール」: `novel__200507__09195017__all_alone_03.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "izumi_miki|群衆の中での孤独":
    confirmed: true
    detach: [novel__200507__09195017__all_alone_02.htm, novel__200507__09195017__all_alone_03.htm]
```

</details>

### 10. `johdan-hanayochanshiriizubangaihen-hantaashiriizugaiden-hantaagaisuto` — 華代ちゃんシリーズ番外編　ハンターシリーズ外伝「ハンターガイスト」

- 根拠: `filename-stem` / 話数: 5 / 作者: 城弾 (`johdan`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__extra__kayo_ex28.html` | 華代ちゃんシリーズ番外編　ハンターシリーズ外伝「ハンターガイスト」 | kayo_ex28.html | 2003-11-16 | honkan |
| `novel__kayo_chan__extra__kayo_ex43.htm` | ハンターシリーズ「ゴウゴウの奇妙な災難」 | kayo_ex43.htm | 2006-06-03 | honkan |
| `novel__kayo_chan__extra__kayo_ex59.htm` | ハンターシリーズ「ラスト・ファーストキス」 | kayo_ex59.htm | 2008-03-30 | honkan |
| `novel__kayo_chan__extra__kayo_ex61.htm` | ハンターシリーズ「君が主のごとく」 | kayo_ex61.htm | 2008-07-14 | honkan |
| `novel__kayo_chan__extra__kayo_ex62.htm` | ハンターシリーズ「可憐バトラーセバ」 | kayo_ex62.htm | 2008-07-26 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「ハンターシリーズ「ゴウゴウの奇妙な災難」の 4 話)
  - 切り離し → 単独の作品「華代ちゃんシリーズ番外編　ハンターシリーズ外伝「ハンターガイスト」」: `novel__kayo_chan__extra__kayo_ex28.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "johdan|華代ちゃんシリーズ番外編　ハンターシリーズ外伝「ハンターガイスト」":
    confirmed: true
    detach: [novel__kayo_chan__extra__kayo_ex28.html]
```

</details>

### 11. `johdan-oorusutaakurosuoobaa` — オールスタークロスオーバー

- 根拠: `filename-stem`, `title-prefix` / 話数: 4 / 作者: 城弾 (`johdan`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200703__16205836__allstar01.htm` | 城弾シアター50万ヒット記念「オールスタークロスオーバー」 | allstar01.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__allstar02.htm` | オールスタークロスオーバー Part2 | allstar02.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__allstar03.htm` | オールスタークロスオーバーPart3 | allstar03.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__allstar04.htm` | 50万ヒット記念「オールスタークロスオーバー」Part4 | allstar04.htm | 2007-03-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「オールスタークロスオーバー」の 2 話)
  - 切り離し → 単独の作品「城弾シアター50万ヒット記念「オールスタークロスオーバー」」: `novel__200703__16205836__allstar01.htm`
  - 切り離し → 単独の作品「50万ヒット記念「オールスタークロスオーバー」Part4」: `novel__200703__16205836__allstar04.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "johdan|オールスタークロスオーバー":
    confirmed: true
    detach: [novel__200703__16205836__allstar01.htm, novel__200703__16205836__allstar04.htm]
```

</details>

### 12. `kca-hijiriinochikinodaichi-1-mikotoyouhei` — 聖命樹の大地 1.巫女と傭兵

- 根拠: `filename-stem`, `title-prefix` / 話数: 5 / 作者: ＫＣＡ (`kca`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__sephi__sephi3.html` | セフィロト5章 | sephi3.html | — | uncatalogued |
| `novel__sephi__sephi1.html` | 聖命樹の大地　１．巫女と傭兵 | sephi1.html | 1998-05-07 | honkan |
| `novel__sephi__sephi2.html` | 聖命樹の大地　２ | sephi2.html | 1998-05-17 | honkan |
| `novel__sephi__sephi0.html+19981202` | 聖命樹の大地　１．巫女と傭兵 | sephi0.html | 1998-12-02 | honkan |
| `novel__sephi__sephi0.html+19990604` | 聖命樹の大地３ | sephi0.html | 1999-06-04 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「聖命樹の大地 1.巫女と傭兵」の 4 話)
  - 切り離し → 単独の作品「セフィロト5章」: `novel__sephi__sephi3.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kca|聖命樹の大地 1.巫女と傭兵":
    confirmed: true
    detach: [novel__sephi__sephi3.html]
```

</details>

### 13. `kca-pararerutssutoorii-setsubou-shiriizu` — パラレルＴＳストーリー 「切望」シリーズ

- 根拠: `filename-stem` / 話数: 2 / 作者: ＫＣＡ (`kca`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__setsubou__setsubou.html` | パラレルＴＳストーリー 「切望」シリーズ | setsubou.html | 1999-10-16 | honkan |
| `novel__setsubou__setsubou01.html` | 切望: 伊藤真由美の場合 | setsubou01.html | 1999-10-16 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「パラレルTSストーリー 「切望」シリーズ」の 1 話)
  - 切り離し → 単独の作品「切望: 伊藤真由美の場合」: `novel__setsubou__setsubou01.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kca|パラレルＴＳストーリー 「切望」シリーズ":
    confirmed: true
    detach: [novel__setsubou__setsubou01.html]
```

</details>

### 14. `kebo-kazenoaika-raito-raito-sutooriigaiden` — 風の哀歌〜ライト・ライト・ストーリー外伝〜

- 根拠: `filename-stem` / 話数: 3 / 作者: KEBO (`kebo`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__light_light__light_ex01.html` | 風の哀歌〜ライト・ライト・ストーリー外伝〜 | light_ex01.html | 2000-06-25 | honkan |
| `novel__light_light__light_ex02.html` | オーロラの彼方に…　〜ライト・ライト・ストーリー外伝２〜 | light_ex02.html | 2000-09-17 | honkan |
| `novel__light_light__light_ex03.html` | 黒い塔の騎士　〜ライト・ライト・ストーリー外伝３〜 | light_ex03.html | 2000-12-09 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「風の哀歌〜ライト・ライト・ストーリー」の 1 話)
  - 切り離し → 単独の作品「オーロラの彼方に…　〜ライト・ライト・ストーリー外伝２〜」: `novel__light_light__light_ex02.html`
  - 切り離し → 単独の作品「黒い塔の騎士　〜ライト・ライト・ストーリー外伝３〜」: `novel__light_light__light_ex03.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kebo|風の哀歌〜ライト・ライト・ストーリー外伝〜":
    confirmed: true
    detach: [novel__light_light__light_ex02.html, novel__light_light__light_ex03.html]
```

</details>

### 15. `kebo-uminikanrunichi` — 海に還る日

- 根拠: `filename-stem`, `title-prefix` / 話数: 4 / 作者: KEBO (`kebo`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__mermaid2.html` | 海に還る日 | mermaid2.html | — | uncatalogued |
| `novel__200112__20002028__mermaid.html` | 人魚の涙 | mermaid.html | 1998-08-08 | honkan |
| `novel__mermaid.html` | 人魚の涙 | mermaid.html | 1998-08-08 | legacy |
| `novel__200112__20002028__mermaid2.html` | 海に還る日 | mermaid2.html | 2000-05-12 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **分割** (芯ごとの機械の振り分け。元の作品に残すのは芯「海に還る日」の 2 話)
  - 分割 → 新しい作品「人魚の涙」: `novel__200112__20002028__mermaid.html`, `novel__mermaid.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kebo|海に還る日":
    confirmed: true
    split:
      - work_title: "人魚の涙"
        episodes: [novel__200112__20002028__mermaid.html, novel__mermaid.html]
```

</details>

### 16. `kion_rei-kizuna-midochannoyuuutsu-2` — きずな ~ミドちゃんの憂鬱

- 根拠: `filename-stem`, `title-prefix` / 話数: 6 / 作者: 樹遠零 (`kion_rei`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kizuna__kizuna.html` | 絆 | kizuna.html | 1999-06-03 | legacy |
| `novel__kizuna__kizuna2.html` | 絆　～ライナ～ | kizuna2.html | 1999-07-11 | legacy |
| `novel__kizuna__kizuna3.html` | きずな　～ミドちゃんの憂鬱～　第壱話「アイシャ、襲来」 | kizuna3.html | 1999-07-22 | legacy |
| `novel__kizuna__kizuna4.html` | 絆　～全てを見通す者～ | kizuna4.html | 1999-08-03 | legacy |
| `novel__kizuna__kizuna3a.html` | きずな ～ミドちゃんの憂鬱～ 第弐話「百合の花嫁」 | kizuna3a.html | 1999-09-27 | legacy |
| `novel__kizuna__kizuna3b.html` | きずな ～ミドちゃんの憂鬱～ 第参話「薔薇の庭園」 | kizuna3b.html | 1999-10-01 | legacy |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **分割 + 切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「きずな ~ミドちゃんの憂鬱」の 3 話)
  - 分割 → 新しい作品「絆 ~ライナ」: `novel__kizuna__kizuna2.html`, `novel__kizuna__kizuna4.html`
  - 切り離し → 単独の作品「絆」: `novel__kizuna__kizuna.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kion_rei|きずな ~ミドちゃんの憂鬱":
    confirmed: true
    split:
      - work_title: "絆 ~ライナ"
        episodes: [novel__kizuna__kizuna2.html, novel__kizuna__kizuna4.html]
    detach: [novel__kizuna__kizuna.html]
```

</details>

### 17. `kotobuki_hikaru_ndcss-miraaringushiriizukisosettei` — ミラーリングシリーズ基礎設定

- 根拠: `filename-stem` / 話数: 2 / 作者: ことぶきひかる (`kotobuki_hikaru_ndcss`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__mirror_ring__ring00.html` | ミラーリングシリーズ基礎設定 | ring00.html | 1999-09-26 | honkan |
| `novel__mirror_ring__ring01.html` | 鏡の指輪のアリス | ring01.html | 1999-09-26 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「ミラーリングシリーズ基礎設定」の 1 話)
  - 切り離し → 単独の作品「鏡の指輪のアリス」: `novel__mirror_ring__ring01.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kotobuki_hikaru_ndcss|ミラーリングシリーズ基礎設定":
    confirmed: true
    detach: [novel__mirror_ring__ring01.html]
```

</details>

### 18. `makoto-arukemisuto-saaga` — あるけみすと☆さーが

- 根拠: `filename-stem`, `title-prefix` / 話数: 4 / 作者: マコト (`makoto`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200508__27023833__alche01.htm` | あるけみすと☆さーが 第一話 | alche01.htm | 2005-08-01 | uncatalogued |
| `novel__200508__27023833__alche02.htm` | あるけみすと☆さーが 第二話 | alche02.htm | 2005-08-01 | uncatalogued |
| `novel__200508__27023833__alche03.htm` | 第三話 | alche03.htm | 2005-08-01 | uncatalogued |
| `novel__200508__27023833__alche05.htm` | あるけみすと☆さーが 第五話 | alche05.htm | 2005-08-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「あるけみすと☆さーが」の 3 話)
  - 切り離し → 単独の作品「第三話」: `novel__200508__27023833__alche03.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "makoto|あるけみすと☆さーが":
    confirmed: true
    detach: [novel__200508__27023833__alche03.htm]
```

</details>

### 19. `makoto-dainihanashi` — 第二話

- 根拠: `filename-stem` / 話数: 2 / 作者: マコト (`makoto`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200508__27023833__novel2.html` | 第二話 | novel2.html | 2005-08-01 | uncatalogued |
| `novel__200508__27023833__novel3.html` | 第三話 | novel3.html | 2005-08-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「第二話」の 1 話)
  - 切り離し → 単独の作品「第三話」: `novel__200508__27023833__novel3.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "makoto|第二話":
    confirmed: true
    detach: [novel__200508__27023833__novel3.html]
```

</details>

### 20. `marie-kyaria-dei-97` — キャリア・デイ　９７

- 根拠: `filename-stem`, `title-prefix` / 話数: 10 / 作者: Ｍａｒｉｅ (`marie`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__toukou01.html+19971204` | キャリア・デイ　９７ | toukou01.html | 1997-12-04 | legacy |
| `novel__toukou01.html+19971209` | 未来から来た女 | toukou01.html | 1997-12-09 | legacy |
| `novel__toukou01.html+19971221` | 虹色のイヤリング | toukou01.html | 1997-12-21 | legacy |
| `novel__toukou02.html+19980130` | イチゴのお弁当箱 | toukou02.html | 1998-01-30 | legacy |
| `novel__career23.html` | キャリア・デイ２０２３ | career23.html | 1998-05-03 | legacy |
| `novel__200202__03004409__career97.html` | キャリア・デイ　９７ | career97.html | 2002-02-03 | honkan |
| `novel__200202__03004409__earring.html` | 虹色のイヤリング | earring.html | 2002-02-24 | honkan |
| `novel__200202__03004409__future.html` | 未来から来た女 | future.html | 2002-02-24 | honkan |
| `novel__200202__03004409__ichigo.html` | イチゴのお弁当箱 | ichigo.html | 2002-08-29 | honkan |
| `novel__200202__03004409__career2023.html` | キャリア・デイ　２０２３ | career2023.html | 2003-01-18 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **分割** (芯ごとの機械の振り分け。元の作品に残すのは芯「キャリア・デイ」の 4 話)
  - 分割 → 新しい作品「未来から来た女」: `novel__toukou01.html+19971209`, `novel__200202__03004409__future.html`
  - 分割 → 新しい作品「虹色のイヤリング」: `novel__toukou01.html+19971221`, `novel__200202__03004409__earring.html`
  - 分割 → 新しい作品「イチゴのお弁当箱」: `novel__toukou02.html+19980130`, `novel__200202__03004409__ichigo.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "marie|キャリア・デイ　９７":
    confirmed: true
    split:
      - work_title: "未来から来た女"
        episodes: [novel__toukou01.html+19971209, novel__200202__03004409__future.html]
      - work_title: "虹色のイヤリング"
        episodes: [novel__toukou01.html+19971221, novel__200202__03004409__earring.html]
      - work_title: "イチゴのお弁当箱"
        episodes: [novel__toukou02.html+19980130, novel__200202__03004409__ichigo.html]
```

</details>

### 21. `mashiro_yuu-ahujouri01` — AHujouri01.

- 根拠: `filename-stem`, `title-prefix` / 話数: 15 / 作者: 真城　悠 (`mashiro_yuu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__mashiro__new_year__AHujouri01.1.htm` | AHujouri01 | AHujouri01.1.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.2.htm` | AHujouri01 | AHujouri01.2.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.3.htm` | AHujouri01 | AHujouri01.3.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.4.htm` | AHujouri01 | AHujouri01.4.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.5.htm` | AHujouri01 | AHujouri01.5.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.6.htm` | AHujouri01.6 | AHujouri01.6.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.7.htm` | AHujouri01.7 | AHujouri01.7.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.8.htm` | AHujouri01.8 | AHujouri01.8.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.9.htm` | AHujouri01.9 | AHujouri01.9.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.10.htm` | AHujouri01.10 | AHujouri01.10.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.11.htm` | AHujouri01.11 | AHujouri01.11.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.12.htm` | AHujouri01.12 | AHujouri01.12.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.13.htm` | AHujouri01.13 | AHujouri01.13.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.14.htm` | AHujouri01.14 | AHujouri01.14.htm | — | uncatalogued |
| `novel__mashiro__new_year__AHujouri01.0.htm` | 新春サービス・完全版 | AHujouri01.0.htm | 2000-04-30 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「AHujouri01.」の 14 話)
  - 切り離し → 単独の作品「新春サービス・完全版」: `novel__mashiro__new_year__AHujouri01.0.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mashiro_yuu|AHujouri01.":
    confirmed: true
    detach: [novel__mashiro__new_year__AHujouri01.0.htm]
```

</details>

### 22. `mashiro_yuu-jigenkanrininfosutaa-eiji-obu-aisu-uooru` — 次元管理人フォスター：エイジ・オブ・アイス・ウォール

- 根拠: `filename-stem` / 話数: 6 / 作者: 真城　悠 (`mashiro_yuu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__corrector__foster01.html` | 次元管理人フォスター：エイジ・オブ・アイス・ウォール | foster01.html | 1999-09-02 | honkan |
| `novel__corrector__foster02.html` | 次元管理人フォスター：フレンズ・オブ・ピス | foster02.html | 1999-09-02 | honkan |
| `novel__corrector__foster03.html` | 次元管理人フォスター：エターナル・ドラッグ | foster03.html | 1999-09-02 | honkan |
| `novel__corrector__foster04.html` | 次元管理人フォスター：インペンティング・ディザスター | foster04.html | 1999-09-25 | honkan |
| `novel__corrector__foster05.html` | フォスター： アンゴルモア・ジャスティス | foster05.html | 1999-11-15 | honkan |
| `novel__corrector__foster10.htm` | 次元管理人フォスター　第十話　「エクスティンクション・レベル・イベント」 | foster10.htm | 2003-01-19 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「次元管理人フォスター:エイジ・オブ・アイス・ウォール」の 5 話)
  - 切り離し → 単独の作品「フォスター： アンゴルモア・ジャスティス」: `novel__corrector__foster05.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mashiro_yuu|次元管理人フォスター：エイジ・オブ・アイス・ウォール":
    confirmed: true
    detach: [novel__corrector__foster05.html]
```

</details>

### 23. `million-tenshinooshigoto` — 天使のお仕事

- 根拠: `filename-stem`, `title-prefix` / 話数: 14 / 作者: 100万Hit (`million`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200104__20050818__ac-01.htm` | 天使のお仕事 | ac-01.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-ex1.html` | 天使のお仕事 番外編 「ミス・天ヶ丘コンテスト」 | ac-ex1.html | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-02.htm` | 天使のお仕事 第二話 | ac-02.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-ex2.html` | いんまなあの子へHeavenly☆Present!! | ac-ex2.html | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-03.htm` | 天使のお仕事 第三話 | ac-03.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-ex3.html` | 知らないって事は幸福だね♪ | ac-ex3.html | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-ex4.html` | 天使のお仕事・番外篇4 | ac-ex4.html | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-ex5.html` | セパとヘアクリップ | ac-ex5.html | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-07.htm` | 天使のお仕事 第七話 | ac-07.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-08.htm` | 天使のお仕事 第八話 | ac-08.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-10.htm` | 天使のお仕事 第10話 | ac-10.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-12.htm` | 天使のお仕事第12話「瑞樹は瑞樹前編」 | ac-12.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__ac-13.htm` | 天使のお仕事第13話「瑞樹は瑞樹後編」 | ac-13.htm | 2001-04-01 | uncatalogued |
| `novel__200104__20050818__100title.htm` | 『天使のお仕事』 | 100title.htm | 2001-11-19 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「天使のお仕事」の 11 話)
  - 切り離し → 単独の作品「いんまなあの子へHeavenly☆Present!!」: `novel__200104__20050818__ac-ex2.html`
  - 切り離し → 単独の作品「知らないって事は幸福だね♪」: `novel__200104__20050818__ac-ex3.html`
  - 切り離し → 単独の作品「セパとヘアクリップ」: `novel__200104__20050818__ac-ex5.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "million|天使のお仕事":
    confirmed: true
    detach: [novel__200104__20050818__ac-ex2.html, novel__200104__20050818__ac-ex3.html, novel__200104__20050818__ac-ex5.html]
```

</details>

### 24. `mizutani_akio-angel00` — angel00

- 根拠: `filename-stem` / 話数: 7 / 作者: 水谷秋夫 (`mizutani_akio`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__dirty__angel00.html` | angel00 | angel00.html | — | uncatalogued |
| `novel__dirty__angel04.html` | 「顛末」 | angel04.html | — | uncatalogued |
| `novel__dirty__angel01.html` | D.A.：ダーティーエンジェル誕生 | angel01.html | 2000-03-05 | honkan |
| `novel__dirty__angel02.html` | D.A.：雪山に血の滴 | angel02.html | 2000-03-05 | honkan |
| `novel__dirty__angel03.html` | D.A.：あなたは死なせない | angel03.html | 2000-03-05 | honkan |
| `novel__dirty__angel06.html` | D.A.：医者はどこだ | angel06.html | 2000-07-02 | honkan |
| `novel__dirty__angel10.html` | D.A.：愛していた | angel10.html | 2003-01-11 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「D.A.:ダーティーエンジェル誕生」の 5 話)
  - 切り離し → 単独の作品「angel00」: `novel__dirty__angel00.html`
  - 切り離し → 単独の作品「「顛末」」: `novel__dirty__angel04.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mizutani_akio|angel00":
    confirmed: true
    detach: [novel__dirty__angel00.html, novel__dirty__angel04.html]
```

</details>

### 25. `nanagiri-jigenkanrininfosutaa-goddesu-obu-za-san` — 次元管理人フォスター　ゴッデス・オブ・ザ・サン

- 根拠: `filename-stem` / 話数: 5 / 作者: 七斬 (`nanagiri`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__corrector__foster06.htm` | 次元管理人フォスター　ゴッデス・オブ・ザ・サン | foster06.htm | 2001-08-11 | honkan |
| `novel__corrector__foster07.htm` | 次元管理人フォスター　シッティング・ビューティー | foster07.htm | 2001-09-30 | honkan |
| `novel__corrector__foster08.htm` | 次元管理人フォスター　ファンタジック・ロマンス | foster08.htm | 2001-09-30 | honkan |
| `novel__corrector__foster09.htm` | 次元管理人フォスター　ネバーエンディング・ジャーニー | foster09.htm | 2001-09-30 | honkan |
| `novel__corrector__foster16.html` | フォスター・シリーズ　ザ・フラッグ・オブ・シンサーティ | foster16.html | 2004-07-21 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「次元管理人フォスター ゴッデス・オブ・ザ・サン」の 4 話)
  - 切り離し → 単独の作品「フォスター・シリーズ　ザ・フラッグ・オブ・シンサーティ」: `novel__corrector__foster16.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "nanagiri|次元管理人フォスター　ゴッデス・オブ・ザ・サン":
    confirmed: true
    detach: [novel__corrector__foster16.html]
```

</details>

### 26. `rurita-hanayochanshiriizubangaihen-kochounoyume` — 華代ちゃんシリーズ番外編「こちょうのゆめ」

- 根拠: `filename-stem` / 話数: 2 / 作者: 流離太 (`rurita`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__extra__kayo_ex41.htm` | 華代ちゃんシリーズ番外編「こちょうのゆめ」 | kayo_ex41.htm | 2006-05-22 | honkan |
| `novel__kayo_chan__extra__kayo_ex53.html` | ハンター・シリーズ「ジゴラがやってきた」 | kayo_ex53.html | 2006-11-26 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「華代ちゃんシリーズ」の 1 話)
  - 切り離し → 単独の作品「ハンター・シリーズ「ジゴラがやってきた」」: `novel__kayo_chan__extra__kayo_ex53.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "rurita|華代ちゃんシリーズ番外編「こちょうのゆめ」":
    confirmed: true
    detach: [novel__kayo_chan__extra__kayo_ex53.html]
```

</details>

### 27. `ryuhju-katouienoneko` — 加藤家の猫

- 根拠: `filename-stem` / 話数: 2 / 作者: 龍酒 (`ryuhju`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__18230553__kato.html` | 加藤家の猫 | kato.html | 2005-01-17 | honkan |
| `novel__200112__18230553__kato2.html` | 続加藤家の猫 | kato2.html | 2006-01-06 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「加藤家の猫」の 1 話)
  - 切り離し → 単独の作品「続加藤家の猫」: `novel__200112__18230553__kato2.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "ryuhju|加藤家の猫":
    confirmed: true
    detach: [novel__200112__18230553__kato2.html]
```

</details>

### 28. `ryuhju-paintar` — paintar

- 根拠: `filename-stem` / 話数: 3 / 作者: 龍酒 (`ryuhju`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__18230553__ekaki.htm` | paintar | ekaki.htm | 2001-12-01 | uncatalogued |
| `novel__200112__18230553__ekaki_1.html` | ある絵描きの独白 | ekaki_1.html | 2006-03-23 | honkan |
| `novel__200112__18230553__ekaki_2.html` | とある絵描きの独白 | ekaki_2.html | 2006-06-29 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「paintar」の 1 話)
  - 切り離し → 単独の作品「ある絵描きの独白」: `novel__200112__18230553__ekaki_1.html`
  - 切り離し → 単独の作品「とある絵描きの独白」: `novel__200112__18230553__ekaki_2.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "ryuhju|paintar":
    confirmed: true
    detach: [novel__200112__18230553__ekaki_1.html, novel__200112__18230553__ekaki_2.html]
```

</details>

### 29. `shining_heaven-toirenohanakosantanjouhiwa-ibunroku` — トイレの花子さん誕生秘話・異聞録

- 根拠: `filename-stem` / 話数: 2 / 作者: 天爛 (`shining_heaven`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200607__02124625__nvl__hanako.html` | トイレの花子さん誕生秘話・異聞録 | hanako.html | 2006-07-01 | uncatalogued |
| `novel__200607__02124625__nvl__hanako_3.html` | 花子の悩み相談室! 第三話『彷徨う銅像』 | hanako_3.html | 2006-07-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「トイレの花子さん誕生秘話・異聞録」の 1 話)
  - 切り離し → 単独の作品「花子の悩み相談室! 第三話『彷徨う銅像』」: `novel__200607__02124625__nvl__hanako_3.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "shining_heaven|トイレの花子さん誕生秘話・異聞録":
    confirmed: true
    detach: [novel__200607__02124625__nvl__hanako_3.html]
```

</details>

### 30. `sunasan-rental-body-gakkou-i` — RENTAL BODY: 学校 (I)

- 根拠: `filename-stem` / 話数: 2 / 作者: すな (`sunasan`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__rental__oem__rb_goes_school01.html` | RENTAL BODY: 学校 (I) | rb_goes_school01.html | 2000-09-10 | honkan |
| `novel__rental__oem__rb_goes_school02.html` | レンタルボディ・シリーズ　学校　（２） | rb_goes_school02.html | 2001-02-07 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「RENTAL BODY: 学校 (I」の 1 話)
  - 切り離し → 単独の作品「レンタルボディ・シリーズ　学校　（２）」: `novel__rental__oem__rb_goes_school02.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "sunasan|RENTAL BODY: 学校 (I)":
    confirmed: true
    detach: [novel__rental__oem__rb_goes_school02.html]
```

</details>

### 31. `takakihiromu-nazunachan-shinkansendaitotsugeki` — なずなちゃん「新幹線大突撃」

- 根拠: `filename-stem` / 話数: 2 / 作者: 高樹ひろむ (`takakihiromu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__extra__kayo_ex49.html` | なずなちゃん「新幹線大突撃」 | kayo_ex49.html | 2006-09-13 | honkan |
| `novel__kayo_chan__extra__kayo_ex50.html` | ハンター・シリーズ「77番目のドジっ子」 | kayo_ex50.html | 2006-09-23 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「なずなちゃん「新幹線大突撃」の 1 話)
  - 切り離し → 単独の作品「ハンター・シリーズ「77番目のドジっ子」」: `novel__kayo_chan__extra__kayo_ex50.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "takakihiromu|なずなちゃん「新幹線大突撃」":
    confirmed: true
    detach: [novel__kayo_chan__extra__kayo_ex50.html]
```

</details>

### 32. `tenki-kyarashoukai-zweihandervts-a1` — キャラ紹介 - ZweihanderVTS<A1>

- 根拠: `filename-stem` / 話数: 2 / 作者: 華村天稀 (`tenki`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200512__22203201__zhvts_a01.html` | キャラ紹介 - ZweihanderVTS<A1> | zhvts_a01.html | 2005-12-01 | uncatalogued |
| `novel__200512__22203201__zhvts_a02.html` | 異世界全貌 - ZweihanderVTS<A2> | zhvts_a02.html | 2005-12-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「キャラ紹介 - ZweihanderVTS<A1>」の 1 話)
  - 切り離し → 単独の作品「異世界全貌 - ZweihanderVTS<A2>」: `novel__200512__22203201__zhvts_a02.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "tenki|キャラ紹介 - ZweihanderVTS<A1>":
    confirmed: true
    detach: [novel__200512__22203201__zhvts_a02.html]
```

</details>

### 33. `unattributed-taninzuusankagatakeijibanibento-t-s-heart` — 多人数参加型掲示板イベント「T's☆Heart」

- 根拠: `filename-stem` / 話数: 4 / 作者: 作者不詳 (`unattributed`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__20210702__tsheart.htm` | 多人数参加型掲示板イベント「T's☆Heart」 | tsheart.htm | 2001-12-01 | uncatalogued |
| `novel__200112__20210702__tsheart02_0.htm` | 「T’s☆Heart」テストプレイ・ログ記録 | tsheart02_0.htm | 2001-12-01 | uncatalogued |
| `novel__200112__20210702__tsheart01.htm` | 「T's☆Heart」生徒名簿 | tsheart01.htm | 2001-12-01 | uncatalogued |
| `novel__200112__20210702__tsheart03.htm` | 多人数参加型掲示板イベント「T's☆Heart」Q&A | tsheart03.htm | 2001-12-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「多人数参加型掲示板イベント「T's☆Heart」の 2 話)
  - 切り離し → 単独の作品「「T’s☆Heart」テストプレイ・ログ記録」: `novel__200112__20210702__tsheart02_0.htm`
  - 切り離し → 単独の作品「「T's☆Heart」生徒名簿」: `novel__200112__20210702__tsheart01.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "unattributed|多人数参加型掲示板イベント「T's☆Heart」":
    confirmed: true
    detach: [novel__200112__20210702__tsheart02_0.htm, novel__200112__20210702__tsheart01.htm]
```

</details>

### 34. `ushitora-ashitanomienaibokura` — 明日の見えない僕ら

- 根拠: `filename-stem` / 話数: 2 / 作者: 丑寅 (`ushitora`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200302__10213812__asita.htm` | 明日の見えない僕ら | asita.htm | 2003-04-12 | honkan |
| `novel__200302__10213812__asita02.htm` | 狼と魔女の宴 | asita02.htm | 2003-04-19 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「明日の見えない僕ら」の 1 話)
  - 切り離し → 単独の作品「狼と魔女の宴」: `novel__200302__10213812__asita02.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "ushitora|明日の見えない僕ら":
    confirmed: true
    detach: [novel__200302__10213812__asita02.htm]
```

</details>

### 35. `yaji-oniichantoyobasete-1` — おにいちゃんと呼ばせて(１)

- 根拠: `filename-stem` / 話数: 2 / 作者: 矢治浩平 (`yaji`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__my_brother__ani_1.html` | おにいちゃんと呼ばせて(１) | ani_1.html | 1998-10-14 | honkan |
| `novel__my_brother__ani_2.html` | お兄ちゃんと呼ばせて(２) | ani_2.html | 1998-10-18 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「おにいちゃんと呼ばせて」の 1 話)
  - 切り離し → 単独の作品「お兄ちゃんと呼ばせて(２)」: `novel__my_brother__ani_2.html`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yaji|おにいちゃんと呼ばせて(１)":
    confirmed: true
    detach: [novel__my_brother__ani_2.html]
```

</details>

### 36. `yays-awasekagaminokoibitotachi` — 合わせ鏡の恋人たち

- 根拠: `filename-stem` / 話数: 2 / 作者: 八重洲二世 (`yays`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__story2.html` | 合わせ鏡の恋人たち | story2.html | 1997-11-15 | honkan |
| `novel__story3.html@ＢＢＳ` | ＢＢＳ☆ハプニング！(原題：BBS OOOOOOPS!) | story3.html | 1997-11-22 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「合わせ鏡の恋人たち」の 1 話)
  - 切り離し → 単独の作品「ＢＢＳ☆ハプニング！(原題：BBS OOOOOOPS!)」: `novel__story3.html@ＢＢＳ`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yays|合わせ鏡の恋人たち":
    confirmed: true
    detach: [novel__story3.html@ＢＢＳ]
```

</details>

### 37. `yays-yuushaouga-ga-ga-dai-hanashi-kietachoukan` — 勇者王ガ○ガ○ガ○　第？話「消えた長官」

- 根拠: `filename-stem`, `title-prefix` / 話数: 6 / 作者: 八重洲二世 (`yays`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200012__16230626__aniparo-ggg.html` | 勇者王ガ○ガ○ガ○　第？話「消えた長官」 | aniparo-ggg.html | 1997-11-20 | honkan |
| `novel__aniparo01.html+19971120` | 勇者王ガ○ガ○ガ○　第？話「消えた長官」 | aniparo01.html | 1997-11-20 | legacy |
| `novel__200012__16230626__aniparo-seira.html` | 美少女戦士セラムンＲ　第＃話「うさぎは元基で衛が亜美」 | aniparo-seira.html | 1997-11-23 | honkan |
| `novel__aniparo01.html+19971123` | 美少女戦士セラムンＲ　第＃話「うさぎは元基で衛が亜美」 | aniparo01.html | 1997-11-23 | legacy |
| `novel__200012__16230626__aniparo-eva.html` | 世紀末エヴァーンゲリオーンＺ | aniparo-eva.html | 1997-11-30 | honkan |
| `novel__aniparo01.html+19971130` | 世紀末エヴァーンゲリオーンＺ | aniparo01.html | 1997-11-30 | legacy |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **分割** (芯ごとの機械の振り分け。元の作品に残すのは芯「勇者王ガ○ガ○ガ○ 第?話「消えた長官」の 2 話)
  - 分割 → 新しい作品「美少女戦士セラムンR 第#話「うさぎは元基で衛が亜美」: `novel__200012__16230626__aniparo-seira.html`, `novel__aniparo01.html+19971123`
  - 分割 → 新しい作品「世紀末エヴァーンゲリオーンZ」: `novel__200012__16230626__aniparo-eva.html`, `novel__aniparo01.html+19971130`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yays|勇者王ガ○ガ○ガ○　第？話「消えた長官」":
    confirmed: true
    split:
      - work_title: "美少女戦士セラムンR 第#話「うさぎは元基で衛が亜美"
        episodes: [novel__200012__16230626__aniparo-seira.html, novel__aniparo01.html+19971123]
      - work_title: "世紀末エヴァーンゲリオーンZ"
        episodes: [novel__200012__16230626__aniparo-eva.html, novel__aniparo01.html+19971130]
```

</details>

### 38. `yoshioka-tskai` — ＴＳ戒

- 根拠: `filename-stem` / 話数: 2 / 作者: よしおか (`yoshioka`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200106__03172433__the_ark.htm` | ＴＳ戒 | the_ark.htm | 2005-12-09 | honkan |
| `novel__200106__03172433__the_ark02.htm` | 「失われたアーク」の真実 | the_ark02.htm | 2005-12-09 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「TS戒」の 1 話)
  - 切り離し → 単独の作品「「失われたアーク」の真実」: `novel__200106__03172433__the_ark02.htm`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yoshioka|ＴＳ戒":
    confirmed: true
    detach: [novel__200106__03172433__the_ark02.htm]
```

</details>

### 39. `you_-kayo-chan69` — kayo_chan69

- 根拠: `filename-stem` / 話数: 2 / 作者: you' (`you_`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__kayo_chan69.html` | kayo_chan69 | kayo_chan69.html | — | uncatalogued |
| `novel__kayo_chan__kayo_chan70.html+20020814` | 華代ちゃんシリーズ 「グランドスラム」 | kayo_chan70.html | 2002-08-14 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **切り離し** (芯ごとの機械の振り分け。元の作品に残すのは芯「kayo_chan」の 1 話)
  - 切り離し → 単独の作品「華代ちゃんシリーズ 「グランドスラム」」: `novel__kayo_chan__kayo_chan70.html+20020814`

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "you_|kayo_chan69":
    confirmed: true
    detach: [novel__kayo_chan__kayo_chan70.html+20020814]
```

</details>

## 強い根拠のある作品 (32)

### 40. `7cy-jinseihanamankai-oohourigen01-daishuugen-ichi-dehanaku-daishuugenzerowan-toyomutonankajinzouningenmitaidekakkouyoidesune` — 人生花満開・大祝言０１（“だいしゅうげんいち”ではなく、“だいしゅうげんゼロワン”と読むとなんか人造人間みたいで格好好いですね）

- 根拠: `episode-nav`, `filename-stem` / 話数: 3 / 作者: ７Ｃｙ (`7cy`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200502__15000436__wedding01.html` | 人生花満開・大祝言０１（“だいしゅうげんいち”ではなく、“だいしゅうげんゼロワン”と読むとなんか人造人間みたいで格好好いですね） | wedding01.html | 2007-06-09 | honkan |
| `novel__200502__15000436__wedding02.html` | 大祝言２ | wedding02.html | 2008-01-10 | honkan |
| `novel__200502__15000436__wedding02_b.html` | 本来なら大祝言２の尻にくっ付けて纏めて投稿する予定だったクリスマス・ネタの小話 | wedding02_b.html | 2008-01-21 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「人生花満開・大祝言01(“だいしゅうげんいち”ではなく、“だいしゅうげんゼロワン”と読むとなんか人造人間みたいで格好好いですね」: 1 話
  - 芯「大祝言」: 1 話
  - 芯「本来なら大祝言2の尻にくっ付けて纏めて投稿する予定だったクリスマス・ネタの小話」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "7cy|人生花満開・大祝言０１（“だいしゅうげんいち”ではなく、“だいしゅうげんゼロワン”と読むとなんか人造人間みたいで格好好いですね）":
    confirmed: true
```

</details>

### 41. `dendoro-bishoujoyuusharibagaigaa` — 美少女勇者リバガイガー

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 18 / 作者: デンドロビウム (`dendoro`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200111__03091515__revagaigar.htm` | 美少女勇者リバガイガー第１話『美少女勇者誕生！』 | revagaigar.htm | 2001-11-03 | honkan |
| `novel__200111__03091515__revagaigar02.htm` | 美少女勇者リバガイガー第２話『駆け抜ける嵐』 | revagaigar02.htm | 2001-11-07 | honkan |
| `novel__200111__03091515__revagaigar03.htm` | 美少女勇者リバガイガー第３話『貴方の『よいしょ』にときめきラブ？』 | revagaigar03.htm | 2001-11-08 | honkan |
| `novel__200111__03091515__revagaigar04.htm` | 美少女勇者リバガイガー第４話『がそぽじ 〜むじょうきょうじと呼ばないでっ！〜』 | revagaigar04.htm | 2001-11-11 | honkan |
| `novel__200111__03091515__revagaigar05.html` | 美少女勇者リバガイガー第５話『俺達ガンプラ部』 | revagaigar05.html | 2001-11-25 | honkan |
| `novel__200111__03091515__revagaigar06.htm` | 美少女勇者リバガイガー第６話『粋でいなせなあの男』 | revagaigar06.htm | 2001-12-02 | honkan |
| `novel__200111__03091515__revagaigar_ex1.html` | 美少女勇者リバガイガーＥＸエピソード・その１「おへやごそごそ」 | revagaigar_ex1.html | 2001-12-11 | honkan |
| `novel__200111__03091515__revagaigar_ex2.htm` | リバガイガーＥＸエピソード・その２「くるべきもの？」 | revagaigar_ex2.htm | 2001-12-15 | honkan |
| `novel__200111__03091515__revagaigar07.htm` | 美少女勇者リバガイガー第７話『ヒトの造りしモノ』 | revagaigar07.htm | 2001-12-15 | honkan |
| `novel__200111__03091515__revagaigar_ex3.htm` | 美少女勇者リバガイガーＥＸエピソード３「メリー・メリー・クリスマス」 | revagaigar_ex3.htm | 2001-12-24 | honkan |
| `novel__200111__03091515__revagaigar08.htm` | 美少女勇者リバガイガー第８話『モゲと呼ばれたＧ』 | revagaigar08.htm | 2002-01-04 | honkan |
| `novel__200111__03091515__revagaigar09.htm` | 美少女勇者リバガイガー第９話「博士と助手の冬休み自由研究」 | revagaigar09.htm | 2002-01-27 | honkan |
| `novel__200111__03091515__revagaiger_ex4.htm` | 美少女勇者リバガイガーＥＸエピソード『ぱちもん』 | revagaiger_ex4.htm | 2002-03-08 | honkan |
| `novel__200111__03091515__revagaigar9.5.htm` | リバガイガー第９．５話『銀髪娘の前奏曲』 | revagaigar9.5.htm | 2002-04-02 | honkan |
| `novel__200111__03091515__revagaigar10.htm` | リバガイガー第１０話『仮面走者の再起曲』 | revagaigar10.htm | 2002-04-02 | honkan |
| `novel__200111__03091515__revagaigar11.html` | 美少女勇者リバガイガー第１１話『白き箱船』 | revagaigar11.html | 2002-05-10 | honkan |
| `novel__200111__03091515__revagaigar_gai.html` | 美少女勇者リバガイガー・外伝『空の端っこで』 | revagaigar_gai.html | 2002-05-13 | honkan |
| `novel__200111__03091515__revagaiger_ex4.5.htm` | 美少女勇者リバガイガーＥＸエピソード4.5『グフより蒼し』 | revagaiger_ex4.5.htm | 2002-05-20 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「美少女勇者リバガイガー」: 15 話
  - 芯「リバガイガーEXエピソード・」: 3 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "dendoro|美少女勇者リバガイガー":
    confirmed: true
```

</details>

### 42. `flat-sakamichi` — 坂道

- 根拠: `filename-stem`, `series-html` / 話数: 3 / 作者: ふらっと (`flat`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200109__15004009__slope.html` | 坂道 | slope.html | 2001-09-01 | uncatalogued |
| `novel__200109__15004009__slope1.html` | 坂道 | slope1.html | 2001-09-15 | honkan |
| `novel__200109__15004009__slope2-1.html` | 続・坂道（ふらっと版） | slope2-1.html | 2002-06-22 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「坂道」: 2 話
  - 芯「続・坂道(ふらっと版」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "flat|坂道":
    confirmed: true
```

</details>

### 43. `hideo-kinshuunoyume` — 錦繍の夢

- 根拠: `filename-stem`, `series-title-nav`, `title-page`, `title-prefix` / 話数: 11 / 作者: 英雄 (`hideo`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200606__28222647__kinsyu_index.html` | 錦繍の夢 | kinsyu_index.html | 2006-06-01 | uncatalogued |
| `novel__200606__28222647__hatsuyuki.html` | 紅い初雪 | hatsuyuki.html | 2006-06-28 | honkan |
| `novel__200606__28222647__wkoibito.htm` | 白い恋人 | wkoibito.htm | 2006-07-08 | honkan |
| `novel__200606__28222647__aoomokage1.htm` | 碧の面影（前編） | aoomokage1.htm | 2006-07-22 | honkan |
| `novel__200606__28222647__aoomokage2_1.htm` | 碧の面影（後編） | aoomokage2_1.htm | 2006-08-21 | honkan |
| `novel__200606__28222647__koganeyoru1.htm` | 黄金色の夜（前編） | koganeyoru1.htm | 2006-09-19 | honkan |
| `novel__200606__28222647__koganeyoru2.htm` | 黄金色の夜（中編） | koganeyoru2.htm | 2006-10-21 | honkan |
| `novel__200606__28222647__koganeyoru3.htm` | 黄金色の夜（後編） | koganeyoru3.htm | 2007-01-13 | honkan |
| `novel__200606__28222647__koganeyoru4.htm` | 黄金色の夜（完結編） | koganeyoru4.htm | 2007-04-08 | honkan |
| `novel__200606__28222647__w_d.htm` | 錦繍の夢　番外編　白い恋人、そして | w_d.htm | 2007-10-06 | honkan |
| `novel__200606__28222647__kinsyu1.htm` | 錦繍の夢（前編） | kinsyu1.htm | 2008-07-28 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「黄金色の夜」: 4 話
  - 芯「錦繍の夢」: 3 話
  - 芯「碧の面影」: 2 話
  - 芯「紅い初雪」: 1 話
  - 芯「白い恋人」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "hideo|錦繍の夢":
    confirmed: true
```

</details>

### 44. `hotaru-visuteia-chapter` — ヴィスティア (chapter

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 17 / 作者: ほたる (`hotaru`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__201005__20141534__vistia_01.htm` | ヴィスティア　（chapter 01） | vistia_01.htm | 2011-02-14 | honkan |
| `novel__201005__20141534__vistia_02.htm` | ヴィスティア　（chapter 02） | vistia_02.htm | 2011-03-10 | honkan |
| `novel__201005__20141534__vistia_03.htm` | ヴィスティア　（chapter 03） | vistia_03.htm | 2011-05-16 | honkan |
| `novel__201005__20141534__vistia_04.htm` | ヴィスティア　（chapter 04） | vistia_04.htm | 2011-06-28 | honkan |
| `novel__201005__20141534__vistia_05.htm` | ヴィスティア　（chapter 05） | vistia_05.htm | 2011-07-20 | honkan |
| `novel__201005__20141534__vistia_06.htm` | ヴィスティア　（chapter 06） | vistia_06.htm | 2011-09-11 | honkan |
| `novel__201005__20141534__vistia_07.htm` | ヴィスティア　（chapter 07） | vistia_07.htm | 2011-10-14 | honkan |
| `novel__201005__20141534__vistia_bangai_01.htm` | 番外編ヴィスティア　『こんなところで！　望まないせんとう』 | vistia_bangai_01.htm | 2011-11-02 | honkan |
| `novel__201005__20141534__vistia_08.htm` | ヴィスティア　（chapter 08） | vistia_08.htm | 2011-11-26 | honkan |
| `novel__201005__20141534__vistia_09.htm` | ヴィスティア　（chapter 09） | vistia_09.htm | 2011-12-17 | honkan |
| `novel__201005__20141534__vistia_10.htm` | ヴィスティア　（chapter 10） | vistia_10.htm | 2012-01-01 | honkan |
| `novel__201005__20141534__vistia_11.htm` | ヴィスティア　（chapter 11） | vistia_11.htm | 2012-01-27 | honkan |
| `novel__201005__20141534__vistia_12.htm` | ヴィスティア　（chapter 12） | vistia_12.htm | 2012-03-02 | honkan |
| `novel__201005__20141534__vistia_13.htm` | ヴィスティア　（chapter 13） | vistia_13.htm | 2012-04-29 | honkan |
| `novel__201005__20141534__vistia_14.htm` | ヴィスティア　（chapter 14） | vistia_14.htm | 2012-06-17 | honkan |
| `novel__201005__20141534__vistia_15.htm` | ヴィスティア　（chapter 15） | vistia_15.htm | 2012-08-02 | honkan |
| `novel__201005__20141534__vistia_16.htm` | ヴィスティア【終】　「望みを失わないために、できること」 | vistia_16.htm | 2012-08-29 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ヴィスティア (chapter」: 16 話
  - 芯「番外編ヴィスティア 『こんなところで! 望まないせんとう」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "hotaru|ヴィスティア (chapter":
    confirmed: true
```

</details>

### 45. `johdan-antenashoppu` — アンテナショップ

- 根拠: `episode-nav`, `filename-stem`, `title-prefix` / 話数: 7 / 作者: 城弾 (`johdan`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200703__16205836__angel_voice_j.htm` | Angel Voice | angel_voice_j.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__antena02_j.htm` | アンテナショップPart2 | antena02_j.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__antena03_j.htm` | アンテナショップ後編 | antena03_j.htm | 2007-03-01 | uncatalogued |
| `novel__200803__01214225__antena01a.htm` | アンテナショップ 前編 | antena01a.htm | 2008-03-01 | honkan |
| `novel__200803__01214225__antena02.htm` | アンテナショップ 中編 | antena02.htm | 2009-01-16 | honkan |
| `novel__200803__01214225__antena03.htm` | アンテナショップ 後編 | antena03.htm | 2009-02-15 | honkan |
| `novel__201212__18232308__angelvoice.htm` | Ａｎｇｅｌ　Ｖｏｉｃｅ | angelvoice.htm | 2013-03-24 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「アンテナショップ」: 5 話
  - 芯「Angel Voice」: 2 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "johdan|アンテナショップ":
    confirmed: true
```

</details>

### 46. `johdan-maid-of-fire` — Maid of Fire

- 根拠: `episode-nav`, `filename-stem`, `series-html`, `title-prefix` / 話数: 13 / 作者: 城弾 (`johdan`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200209__19204751__maid_of_fire01.htm` | Ｍａｉｄ ｏｆ Ｆｉｒｅ −１ | maid_of_fire01.htm | 2002-10-01 | honkan |
| `novel__200209__19204751__maid_of_fire02.htm` | Ｍａｉｄ　ｏｆ　Ｆｉｒｅ Vol2 | maid_of_fire02.htm | 2002-10-17 | honkan |
| `novel__200209__19204751__maid_of_fire03.htm` | Ｍａｉｄ　ｏｆ　ｆｉｒｅ Vol3 | maid_of_fire03.htm | 2002-12-04 | honkan |
| `novel__200209__19204751__maid_of_fire04a.htm` | Ｍａｉｄ　ｏｆ　ｆｉｒｅ F | maid_of_fire04a.htm | 2002-12-17 | honkan |
| `novel__200209__19204751__maid_of_fire04b.htm` | Ｍａｉｄ　ｏｆ　Ｆｉｒｅ　Ｆ（Another Ending） | maid_of_fire04b.htm | 2003-06-01 | honkan |
| `novel__200209__19204751__alice_holiday.htm` | ありすの休日 | alice_holiday.htm | 2003-10-19 | honkan |
| `novel__200703__16205836__maid_of_fire01.htm` | Maid of Fire | maid_of_fire01.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__maid_of_fire02.htm` | Maid of Fire Vol2 | maid_of_fire02.htm | 2007-03-01 | uncatalogued |
| `novel__200907__19181513__maid_of_fire01.htm` | Maid of Fire | maid_of_fire01.htm | 2009-07-01 | uncatalogued |
| `novel__200907__19181513__maid_of_fire02.htm` | Maid of Fire Vol2 | maid_of_fire02.htm | 2009-07-01 | uncatalogued |
| `novel__200907__19181513__maid_of_fire03.htm` | Maid of Fire Vol3 | maid_of_fire03.htm | 2009-07-01 | uncatalogued |
| `novel__201009__16215412__maid_of_fire01.htm` | Maid of Fire | maid_of_fire01.htm | 2010-09-01 | uncatalogued |
| `novel__201009__16215412__maid_of_fire02.htm` | Maid of Fire Vol2 | maid_of_fire02.htm | 2010-09-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` / `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「Maid of Fire」: 12 話
  - 芯「ありすの休日」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "johdan|Maid of Fire":
    confirmed: true
```

</details>

### 47. `johdan-yunomachikidan` — 湯の街奇談

- 根拠: `episode-nav`, `filename-stem`, `series-html`, `title-prefix` / 話数: 19 / 作者: 城弾 (`johdan`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200209__19204751__yunomachi01.html` | 「湯の街奇談」第一話 | yunomachi01.html | 2003-11-23 | honkan |
| `novel__200209__19204751__yunomachi02.html` | 「湯の街奇談」第二話 | yunomachi02.html | 2004-01-31 | honkan |
| `novel__200209__19204751__yunomachi03.html` | 「湯の街奇談」第三話 | yunomachi03.html | 2004-06-24 | honkan |
| `novel__200209__19204751__yunomachi04.html` | 「湯の街奇談」第四話 | yunomachi04.html | 2005-04-27 | honkan |
| `novel__200209__19204751__yunomachi05.html` | 『湯の街奇談・最終話』 | yunomachi05.html | 2006-01-28 | honkan |
| `novel__200209__19204751__yodan.html` | 湯の街余談 | yodan.html | 2006-02-14 | honkan |
| `novel__200703__16205836__yodan_j.htm` | 湯の街余談 | yodan_j.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__yunomachi04a.htm` | 湯の街奇談第四話「長寿の湯」 | yunomachi04a.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__yunomachi05a.htm` | 湯の街奇談・最終話 | yunomachi05a.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__kidan01.htm` | 湯の町奇談 第一話 | kidan01.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__yunomachi01.htm` | 湯の街レディ 第一話「子宝の湯」 | yunomachi01.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__kidan02.htm` | 湯の街奇談 第二話 リライトバージョン | kidan02.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__yunomachi02.htm` | 湯の街奇談第二話「混浴温泉」 | yunomachi02.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__kidan03.htm` | kidan03 | kidan03.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__yunomachi03.htm` | 湯の街奇談第三話「美人の湯」 | yunomachi03.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__kidan04.htm` | 湯の町奇談 第四話リライトバージョン | kidan04.htm | 2007-03-01 | uncatalogued |
| `novel__200703__16205836__kidan05.htm` | 湯の街奇談 最終話リライトバージョン | kidan05.htm | 2007-03-01 | uncatalogued |
| `novel__200907__19181513__yunomachi01.htm` | 湯の街レディ 第一話「子宝の湯」 | yunomachi01.htm | 2009-07-01 | uncatalogued |
| `novel__200907__19181513__yunomachi02.htm` | 湯の街奇談第二話「混浴温泉」 | yunomachi02.htm | 2009-07-01 | uncatalogued |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` / `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「湯の街奇談」: 18 話
  - 芯「kidan」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "johdan|湯の街奇談":
    confirmed: true
```

</details>

### 48. `juurouta-easy-come-easy-go` — EASY COME EASY GO

- 根拠: `series-html`, `title-prefix` / 話数: 3 / 作者: 十郎太 (`juurouta`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200011__03192112__easy_come_easy_go.html` | ＥＡＳＹ ＣＯＭＥ ＥＡＳＹ ＧＯ | easy_come_easy_go.html | 1999-06-25 | honkan |
| `novel__easy_come_easy_go.html` | ＥＡＳＹ ＣＯＭＥ ＥＡＳＹ ＧＯ | easy_come_easy_go.html | 1999-06-25 | legacy |
| `novel__200011__03192112__welcome_to_xrevenge.html` | Xrevenge | welcome_to_xrevenge.html | 2000-11-03 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「EASY COME EASY GO」: 2 話
  - 芯「Xrevenge」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "juurouta|EASY COME EASY GO":
    confirmed: true
```

</details>

### 49. `kagerou6-hiroin` — ヒロイン

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 24 / 作者: kagerou6 (`kagerou6`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200009__17163907__the_heroine01.html` | ヒロイン | the_heroine01.html | 2000-09-01 | uncatalogued |
| `novel__200206__04234833__the_heroine01.html` | ヒロイン | the_heroine01.html | 2000-09-17 | honkan |
| `novel__200206__04234833__the_heroine02.html` | コンテスト・ヒロインII | the_heroine02.html | 2000-09-17 | honkan |
| `novel__200206__04234833__the_heroine03.html` | ポートレート・ヒロインIII | the_heroine03.html | 2000-09-29 | honkan |
| `novel__200206__04234833__the_heroine04.html` | ビーナス・ヒロインIV | the_heroine04.html | 2000-09-29 | honkan |
| `novel__200010__07181905__the_heroine05.html` | モールプリンセス・ヒロインV | the_heroine05.html | 2000-10-01 | uncatalogued |
| `novel__200010__22035539__the_heroine08.html` | サマーキャンプ・ヒロインVIII | the_heroine08.html | 2000-10-01 | uncatalogued |
| `novel__200206__04234833__the_heroine05.html` | モールプリンセス・ヒロインＶ | the_heroine05.html | 2000-10-08 | honkan |
| `novel__200206__04234833__the_heroine06.html` | ドラマティック・ヒロインVI | the_heroine06.html | 2000-10-08 | honkan |
| `novel__200206__04234833__the_heroine07.html` | ダークプリンセス・ヒロインVII | the_heroine07.html | 2000-10-14 | honkan |
| `novel__200206__04234833__the_heroine08.html` | サマーキャンプ・ヒロインVIII | the_heroine08.html | 2000-10-22 | honkan |
| `novel__200206__04234833__the_heroine09.html` | スポーツデイ・ヒロインIX | the_heroine09.html | 2000-11-12 | honkan |
| `novel__200206__04234833__the_heroine10.html` | ステキな贈り物・ヒロインX | the_heroine10.html | 2000-12-13 | honkan |
| `novel__200206__04234833__the_heroine11-b.html` | セカンドヒロインの前振り・ヒロイン11th前 | the_heroine11-b.html | 2002-06-04 | honkan |
| `novel__200206__04234833__the_heroine11.html` | ヒロイン１１th | the_heroine11.html | 2002-06-29 | honkan |
| `novel__200206__04234833__the_heroine12.html` | ふぁーすと・こんたくと　ヒロイン１２th | the_heroine12.html | 2002-07-29 | honkan |
| `novel__200206__04234833__the_heroine13.html` | ぶろーくん・ふぁーざー　ヒロイン１３ｔh | the_heroine13.html | 2002-10-01 | honkan |
| `novel__200206__04234833__the_heroine14.html` | えんじぇる・ふぁいる　ヒロイン１４th | the_heroine14.html | 2003-01-14 | honkan |
| `novel__200206__04234833__the_heroine15.html` | こけてぃっしゅ　ヒロイン１５th | the_heroine15.html | 2003-02-22 | honkan |
| `novel__200206__04234833__the_heroine16.html` | ぷーるさいど・らぷそでぃ ヒロイン１６th | the_heroine16.html | 2003-06-14 | honkan |
| `novel__200206__04234833__the_heroine17.html` | とれいど・がーる　ヒロイン１７th | the_heroine17.html | 2003-07-06 | honkan |
| `novel__200206__04234833__the_heroine18.html` | ふぁーすと・すてーじ　ヒロイン１８th | the_heroine18.html | 2003-08-26 | honkan |
| `novel__200206__04234833__the_heroine19.html` | ぷれーん・あいどる？　ヒロイン１９th | the_heroine19.html | 2004-01-01 | honkan |
| `novel__200206__04234833__the_heroine20.html` | 貴方の為のヒロイン・　ヒロイン２０th | the_heroine20.html | 2004-02-12 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ヒロイン」: 3 話
  - 芯「モールプリンセス・ヒロインV」: 2 話
  - 芯「サマーキャンプ・ヒロインVIII」: 2 話
  - 芯「ふぁーすと・こんたくと ヒロイン12th」: 2 話
  - 芯「コンテスト・ヒロインII」: 1 話
  - 芯「ポートレート・ヒロインIII」: 1 話
  - 芯「ビーナス・ヒロインIV」: 1 話
  - 芯「ドラマティック・ヒロインVI」: 1 話
  - 芯「ダークプリンセス・ヒロインVII」: 1 話
  - 芯「スポーツデイ・ヒロインIX」: 1 話
  - 芯「ステキな贈り物・ヒロインX」: 1 話
  - 芯「セカンドヒロインの前振り・ヒロイン11th前」: 1 話
  - 芯「ぶろーくん・ふぁーざー ヒロイン13th」: 1 話
  - 芯「えんじぇる・ふぁいる ヒロイン14th」: 1 話
  - 芯「こけてぃっしゅ ヒロイン15th」: 1 話
  - 芯「ぷーるさいど・らぷそでぃ ヒロイン16th」: 1 話
  - 芯「とれいど・がーる ヒロイン17th」: 1 話
  - 芯「ぷれーん・あいどる? ヒロイン19th」: 1 話
  - 芯「貴方の為のヒロイン・ ヒロイン20th」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kagerou6|ヒロイン":
    confirmed: true
```

</details>

### 50. `kagerou6-nidomenoyounajimi` — 二度目の幼なじみ

- 根拠: `filename-stem`, `series-html`, `title-prefix` / 話数: 5 / 作者: kagerou6 (`kagerou6`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__nidome.html` | 二度目の幼なじみ | nidome.html | — | uncatalogued |
| `novel__200206__04234833__nidome.html` | 二度目の幼なじみ | nidome.html | 2000-08-15 | honkan |
| `novel__200206__04234833__nidome2.html` | 二人の記念日 | nidome2.html | 2000-09-19 | honkan |
| `novel__200206__04234833__nidome3.html` | ・・・・絆・・・・（幼なじみ・３） | nidome3.html | 2000-10-01 | honkan |
| `novel__200206__04234833__nidome4.html` | 幼なじみFIN | nidome4.html | 2001-03-02 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「二度目の幼なじみ」: 2 話
  - 芯「二人の記念日」: 1 話
  - 芯「・・・・絆・・・・(幼なじみ・」: 1 話
  - 芯「幼なじみFIN」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kagerou6|二度目の幼なじみ":
    confirmed: true
```

</details>

### 51. `kebo-kiiroiyami` — 黄色い闇

- 根拠: `series-html`, `title-prefix` / 話数: 4 / 作者: KEBO (`kebo`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__20002028__yellow.html` | 黄色い闇 | yellow.html | 1998-09-20 | honkan |
| `novel__yellow.html` | 黄色い闇 | yellow.html | 1998-09-20 | legacy |
| `novel__200112__20002028__rouge.html` | 紅い闇 | rouge.html | 1998-10-05 | honkan |
| `novel__200112__20002028__the_darkness_of_blue.html` | 蒼い闇 | the_darkness_of_blue.html | 1998-12-14 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「黄色い闇」: 2 話
  - 芯「紅い闇」: 1 話
  - 芯「蒼い闇」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kebo|黄色い闇":
    confirmed: true
```

</details>

### 52. `kebo-megamikamenmiimiru` — 女神仮面ミーミル<

- 根拠: `filename-stem`, `series-html`, `series-title-nav`, `title-page`, `title-prefix` / 話数: 35 / 作者: KEBO (`kebo`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__goddess_mask__mimil04.html` | MIMIL4.TXT | mimil04.html | — | uncatalogued |
| `novel__goddess_mask__mimil01.html` | 女神仮面ミーミル＜第一話：女神転生＞ | mimil01.html | 1999-09-16 | honkan |
| `novel__goddess_mask__mimil02.html` | 女神仮面ミーミル＜第二話：黒い薔薇＞ | mimil02.html | 1999-09-23 | honkan |
| `novel__goddess_mask__title.html+19991008` | 女神仮面ミーミル＜第三話：研究室の怪談＞ | title.html | 1999-10-08 | legacy |
| `novel__goddess_mask__mimil03.html+19991008` | 女神仮面ミーミル＜第三話：研究室の怪談＞ | mimil03.html | 1999-10-08 | honkan |
| `novel__goddess_mask__title.html+19991020` | 女神仮面ミーミル＜第四話：うたかたの夢＞ | title.html | 1999-10-20 | legacy |
| `novel__goddess_mask__mimil03.html+19991020` | 女神仮面ミーミル＜第四話：うたかたの夢＞ | mimil03.html | 1999-10-20 | honkan |
| `novel__goddess_mask__title.html+19991119` | 女神仮面ミーミル＜第五話：ロッカールームは危険な香り＞ | title.html | 1999-11-19 | legacy |
| `novel__goddess_mask__mimil05.html` | 女神仮面ミーミル＜第五話：ロッカールームは危険な香り＞ | mimil05.html | 1999-11-19 | honkan |
| `novel__goddess_mask__title.html+19991127` | 女神仮面ミーミル＜第六話：天使の歌声、仮面の裏声＞ | title.html | 1999-11-27 | legacy |
| `novel__goddess_mask__mimil06.html` | 女神仮面ミーミル＜第六話：天使の歌声、仮面の裏声＞ | mimil06.html | 1999-11-27 | honkan |
| `novel__goddess_mask__title.html+19991211` | 女神仮面ミーミル＜第七話：潮風の鎮魂歌＞ | title.html | 1999-12-11 | legacy |
| `novel__goddess_mask__mimil07.html` | 女神仮面ミーミル＜第七話：潮風の鎮魂歌＞ | mimil07.html | 1999-12-11 | honkan |
| `novel__goddess_mask__title.html+20000225` | 女神仮面ミーミル＜第八話：黒い薔薇ＶＳ赤い薔薇＞ | title.html | 2000-02-25 | legacy |
| `novel__goddess_mask__mimil08.html` | 女神仮面ミーミル＜第八話：黒い薔薇ＶＳ赤い薔薇＞ | mimil08.html | 2000-02-25 | honkan |
| `novel__goddess_mask__mimil09.html` | 女神仮面ミーミル＜第九話：決戦は日曜日＞ | mimil09.html | 2000-04-15 | honkan |
| `novel__goddess_mask__mimil10.html` | 女神仮面ミーミル＜第十話：私を愛したヒーロー＞ | mimil10.html | 2000-04-26 | honkan |
| `novel__goddess_mask__mimil11.html` | 女神仮面ミーミル＜第十一話：悪魔のリサイクル＞ | mimil11.html | 2000-05-12 | honkan |
| `novel__goddess_mask__mimil12.html` | 女神仮面ミーミル＜第十二話：六角形の落日＞ | mimil12.html | 2000-05-24 | honkan |
| `novel__goddess_mask__mimil13.html` | 女神仮面ミーミル＜第十三話：女王Ｂ最後の挑戦＞ | mimil13.html | 2000-06-03 | honkan |
| `novel__goddess_mask__mimil_s1.html` | 女神仮面ミーミル＆フレイヤ 〜超次元の女神たち〜 | mimil_s1.html | 2000-08-11 | honkan |
| `novel__goddess_mask__mimil14.html` | 女神仮面ミーミル＜第十四話：天使降臨(前編)＞ | mimil14.html | 2000-10-07 | honkan |
| `novel__goddess_mask__mimil15.html` | 女神仮面ミーミル＜第十五話：天使降臨（後編）＞ | mimil15.html | 2000-10-20 | honkan |
| `novel__goddess_mask__mimil16.html` | 女神仮面ミーミル＜第十六話：漏れ聞こえのミッドナイト＞ | mimil16.html | 2000-10-26 | honkan |
| `novel__goddess_mask__mimil17.html` | 女神仮面ミーミル＜第十七話：憎しみの太陽＞ | mimil17.html | 2000-10-29 | honkan |
| `novel__goddess_mask__mimil18.html` | 女神仮面ミーミル＜第十八話：人魚の涙＞ | mimil18.html | 2000-11-06 | honkan |
| `novel__goddess_mask__mimil19.html` | 女神仮面ミーミル＜第十九話：固い？女にご用心＞ | mimil19.html | 2000-11-17 | honkan |
| `novel__goddess_mask__mimil20.html` | 女神仮面ミーミル＜第二十話：伝家の宝刀＞ | mimil20.html | 2000-11-22 | honkan |
| `novel__goddess_mask__mimil21.html` | 女神仮面ミーミル＜第二十一話：月のステージ＞ | mimil21.html | 2000-11-29 | honkan |
| `novel__goddess_mask__mimil22.html` | 女神仮面ミーミル＜第二十二話：プロジェクトＡ＞ | mimil22.html | 2000-12-04 | honkan |
| `novel__goddess_mask__mimil23.html` | 女神仮面ミーミル＜第二十三話：三姉妹恐怖の正体＞ | mimil23.html | 2000-12-12 | honkan |
| `novel__goddess_mask__mimil24.html` | 女神仮面ミーミル＜第二十四話：闇の鼓動＞ | mimil24.html | 2000-12-19 | honkan |
| `novel__goddess_mask__mimil25.html` | 女神仮面ミーミル＜第二十五話：血の報酬＞ | mimil25.html | 2000-12-24 | honkan |
| `novel__goddess_mask__mimil26.html` | 女神仮面ミーミル＜第二十六話：決着の時＞ | mimil26.html | 2000-12-24 | honkan |
| `novel__goddess_mask__mimil_s2.html` | 太陽仮面アポロン 〜闇を切り裂く正義の光〜 | mimil_s2.html | 2002-02-12 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` / `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「女神仮面ミーミル<」: 33 話
  - 芯「MIMIL4.TXT」: 1 話
  - 芯「太陽仮面アポロン 〜闇を切り裂く正義の光」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kebo|女神仮面ミーミル<":
    confirmed: true
```

</details>

### 53. `kion_rei-kizuna-midochannoyuuutsu` — きずな 〜ミドちゃんの憂鬱

- 根拠: `filename-stem`, `series-html`, `title-prefix` / 話数: 6 / 作者: 樹遠零 (`kion_rei`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200109__21035514__kizuna.html` | 絆 | kizuna.html | 1999-06-03 | honkan |
| `novel__200109__21035514__kizuna2.html` | 絆　〜ライナ〜 | kizuna2.html | 1999-07-11 | honkan |
| `novel__200109__21035514__kizuna3.html` | きずな　〜ミドちゃんの憂鬱〜　第壱話「アイシャ、襲来」 | kizuna3.html | 1999-07-22 | honkan |
| `novel__200109__21035514__kizuna4.html` | 絆　〜全てを見通す者〜 | kizuna4.html | 1999-08-03 | honkan |
| `novel__200109__21035514__kizuna3a.html` | きずな 〜ミドちゃんの憂鬱〜　第弐話「百合の花嫁」 | kizuna3a.html | 1999-09-27 | honkan |
| `novel__200109__21035514__kizuna3b.html` | きずな 〜ミドちゃんの憂鬱〜　第参話「薔薇の庭園」 | kizuna3b.html | 1999-10-01 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「きずな 〜ミドちゃんの憂鬱」: 3 話
  - 芯「絆 〜ライナ」: 2 話
  - 芯「絆」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kion_rei|きずな 〜ミドちゃんの憂鬱":
    confirmed: true
```

</details>

### 54. `kirika-wakabaironokaitou1` — 若葉色の怪盗１

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 20 / 作者: きりか進ノ介 (`kirika`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200605__05101055__wakaba1.htm` | 若葉色の怪盗１ | wakaba1.htm | 2006-05-05 | honkan |
| `novel__200605__05101055__wakaba2.htm` | 若葉色の怪盗２ | wakaba2.htm | 2006-05-14 | honkan |
| `novel__200605__05101055__wakaba3.htm` | 若葉色の怪盗３ | wakaba3.htm | 2006-05-20 | honkan |
| `novel__200605__05101055__wakaba4.htm` | 若葉色の怪盗４ | wakaba4.htm | 2006-05-26 | honkan |
| `novel__200605__05101055__aozora1.htm` | 青空の魔法１ | aozora1.htm | 2006-06-01 | honkan |
| `novel__200605__05101055__aozora2.htm` | 青空の魔法２ | aozora2.htm | 2006-06-07 | honkan |
| `novel__200605__05101055__aozora3.htm` | 青空の魔法３ | aozora3.htm | 2006-06-14 | honkan |
| `novel__200605__05101055__aozora4.htm` | 青空の魔法４ | aozora4.htm | 2006-06-21 | honkan |
| `novel__200605__05101055__fuji001.htm` | 藤色の夏　１ | fuji001.htm | 2006-07-01 | honkan |
| `novel__200605__05101055__fuji002.htm` | 藤色の夏　２ | fuji002.htm | 2006-07-08 | honkan |
| `novel__200605__05101055__fuji003.htm` | 藤色の夏　３ | fuji003.htm | 2006-07-15 | honkan |
| `novel__200605__05101055__fuji004.htm` | 藤色の夏　４ | fuji004.htm | 2006-07-23 | honkan |
| `novel__200605__05101055__shuka001.htm` | 朱夏の恋人　１ | shuka001.htm | 2006-08-26 | honkan |
| `novel__200605__05101055__shuka002.htm` | 朱夏の恋人　２ | shuka002.htm | 2006-09-14 | honkan |
| `novel__200605__05101055__shuka003.htm` | 朱夏の恋人　３ | shuka003.htm | 2006-10-06 | honkan |
| `novel__200605__05101055__shuka004.htm` | 朱夏の恋人　４ | shuka004.htm | 2006-11-03 | honkan |
| `novel__200605__05101055__kin001.htm` | ザ・ゴールデンロード！　１ | kin001.htm | 2007-03-18 | honkan |
| `novel__200605__05101055__kin002.htm` | ザ・ゴールデンロード！　２ | kin002.htm | 2007-07-15 | honkan |
| `novel__200605__05101055__kin003.htm` | ザ・ゴールデンロード！　３ | kin003.htm | 2008-01-09 | honkan |
| `novel__200605__05101055__kin004.htm` | ザ・ゴールデンロード　４ | kin004.htm | 2008-03-12 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「若葉色の怪盗」: 4 話
  - 芯「青空の魔法」: 4 話
  - 芯「藤色の夏」: 4 話
  - 芯「朱夏の恋人」: 4 話
  - 芯「ザ・ゴールデンロード!」: 4 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kirika|若葉色の怪盗１":
    confirmed: true
```

</details>

### 55. `kotobuki_hikaru_ndcss-tshakkenden-shainingu-supirittsu-bureivusu` — TS八犬伝 シャイニング・スピリッツ・ブレイヴス

- 根拠: `filename-stem`, `series-html`, `series-title-nav`, `title-page`, `title-prefix` / 話数: 14 / 作者: ことぶきひかる (`kotobuki_hikaru_ndcss`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__hakken__hakken08.html` | hakken08 | hakken08.html | — | uncatalogued |
| `novel__hakken__hakken01.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第１話 | hakken01.html | 1998-11-28 | honkan |
| `novel__hakken__hakken02.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第２話 | hakken02.html | 1998-12-04 | honkan |
| `novel__hakken__hakken03.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第３話 | hakken03.html | 1999-01-25 | honkan |
| `novel__hakken__hakken04.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第４話 | hakken04.html | 1999-02-20 | honkan |
| `novel__hakken__hakken05.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第５話 | hakken05.html | 1999-03-26 | honkan |
| `novel__hakken__hakken06.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第６話 | hakken06.html | 1999-04-15 | honkan |
| `novel__hakken__title.html+19990504` | 表紙風CG -シャイニング　スピリッツ　ブレイヴス- | title.html | 1999-05-04 | honkan |
| `novel__hakken__hakken07.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第７話 | hakken07.html | 1999-05-31 | honkan |
| `novel__hakken__title.html+19990624` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第８話 | title.html | 1999-06-24 | honkan |
| `novel__hakken__hakken09.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第９話 | hakken09.html | 1999-07-25 | honkan |
| `novel__hakken__hakken10.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第10話 | hakken10.html | 1999-08-24 | honkan |
| `novel__hakken__hakken11.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第11話 | hakken11.html | 1999-09-21 | honkan |
| `novel__hakken__hakken12.html` | TS八犬伝 シャイニング・スピリッツ・ブレイヴス 第12話 | hakken12.html | 1999-10-18 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` / `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「TS八犬伝 シャイニング・スピリッツ・ブレイヴス」: 12 話
  - 芯「hakken」: 1 話
  - 芯「表紙風CG -シャイニング スピリッツ ブレイヴス」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "kotobuki_hikaru_ndcss|TS八犬伝 シャイニング・スピリッツ・ブレイヴス":
    confirmed: true
```

</details>

### 56. `maki_takashi-miichantoyobanaide` — ミィちゃんと呼ばないで

- 根拠: `episode-nav`, `filename-stem`, `series-html`, `title-prefix` / 話数: 9 / 作者: 薪喬 (`maki_takashi`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200203__05130136__fit.html` | fit… | fit.html | 2002-03-05 | honkan |
| `novel__200203__05130136__fit2-1.htm` | fit…II　ミィちゃんと呼ばないで　第一話 | fit2-1.htm | 2002-08-05 | honkan |
| `novel__200203__05130136__fit2-2.htm` | ミィちゃんと呼ばないで　第二話 | fit2-2.htm | 2002-08-08 | honkan |
| `novel__200203__05130136__fit2-3.htm` | ミィちゃんと呼ばないで　第三話 | fit2-3.htm | 2002-08-08 | honkan |
| `novel__200203__05130136__fit2-4.htm` | ミィちゃんと呼ばないで　第四話 | fit2-4.htm | 2002-08-14 | honkan |
| `novel__200203__05130136__fit2-5.htm` | ミィちゃんと呼ばないで　第五話 | fit2-5.htm | 2002-08-19 | honkan |
| `novel__200203__05130136__fit2-6.htm` | ミィちゃんと呼ばないで　第六話 | fit2-6.htm | 2002-08-25 | honkan |
| `novel__200203__05130136__fit2-7.htm` | ミィちゃんと呼ばないで　第七話 | fit2-7.htm | 2002-09-07 | honkan |
| `novel__200203__05130136__fit2-8.htm` | ミィちゃんと呼ばないで　最終話 | fit2-8.htm | 2002-09-16 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` / `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ミィちゃんと呼ばないで」: 7 話
  - 芯「fit...」: 2 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "maki_takashi|ミィちゃんと呼ばないで":
    confirmed: true
```

</details>

### 57. `marie-haatonopendanto` — ハートのペンダント

- 根拠: `filename-stem`, `series-html`, `title-prefix` / 話数: 6 / 作者: Ｍａｒｉｅ (`marie`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__marie01.html` | ピンクのランドセル | marie01.html | 1998-01-03 | legacy |
| `novel__marie02.html` | ハートのペンダント | marie02.html | 1998-01-15 | legacy |
| `novel__heart2.html` | ハートのペンダント２ | heart2.html | 1998-03-03 | legacy |
| `novel__200202__03004409__pink.html` | ピンクのランドセル | pink.html | 2002-04-02 | honkan |
| `novel__200202__03004409__heart01.html` | ハートのペンダント | heart01.html | 2002-08-15 | honkan |
| `novel__200202__03004409__heart02.html` | ハートのペンダント２ | heart02.html | 2002-08-18 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ハートのペンダント」: 4 話
  - 芯「ピンクのランドセル」: 2 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "marie|ハートのペンダント":
    confirmed: true
```

</details>

### 58. `mist-mlchemist-renkinjutsushi` — Mlchemist(錬金術師

- 根拠: `episode-nav`, `filename-stem`, `title-prefix` / 話数: 5 / 作者: Mist (`mist`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200210__11183849__mlchemist.htm` | Mlchemist（錬金術師) | mlchemist.htm | 2002-10-11 | honkan |
| `novel__200210__11183849__mlchemist2.htm` | Mlchemist（錬金術師) 第２話 | mlchemist2.htm | 2002-10-21 | honkan |
| `novel__200210__11183849__mlchemist3.htm` | Mlchemist（錬金術師) 第3話 | mlchemist3.htm | 2002-11-21 | honkan |
| `novel__200210__11183849__mlchemist4.htm` | Mlchemist（錬金術師) 第4話 光サイド | mlchemist4.htm | 2002-12-25 | honkan |
| `novel__200210__11183849__wizard.htm` | Wizard（魔法使い） | wizard.htm | 2003-05-01 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「Mlchemist(錬金術師」: 4 話
  - 芯「Wizard(魔法使い」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mist|Mlchemist(錬金術師":
    confirmed: true
```

</details>

### 59. `mondo-kaetsutekichattafeiku-gaaru` — 帰ってきちゃったフェイク☆ガール

- 根拠: `series-title-nav` / 話数: 2 / 作者: ＭＯＮＤＯ (`mondo`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200505__12003948__fg_05.htm` | 帰ってきちゃったフェイク☆ガール | fg_05.htm | 2011-10-10 | honkan |
| `novel__200505__12003948__fg_06.htm` | ＦＡＫＥ☆ＧＩＲＬふぁいなる | fg_06.htm | 2012-02-20 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「帰ってきちゃったフェイク☆ガール」: 1 話
  - 芯「FAKE☆GIRLふぁいなる」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mondo|帰ってきちゃったフェイク☆ガール":
    confirmed: true
```

</details>

### 60. `mondo-robotrybishoujotsu-shiriizu` — ロボＴＲＹ美少女っ　シリーズ

- 根拠: `filename-stem`, `series-title-nav`, `title-page`, `title-prefix` / 話数: 10 / 作者: ＭＯＮＤＯ (`mondo`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__robot_triathlon__title.html` | ロボＴＲＹ美少女っ　シリーズ | title.html | 2000-02-28 | honkan |
| `novel__robot_triathlon__title.htm` | 『ロボＴＲＹ美少女っ』シリーズ・新タイトルページ | title.htm | 2000-11-06 | honkan |
| `novel__robot_triathlon__robotryex-s.htm` | ロボＴＲＹ・ＥＸ−Ｓ『さおりん歳時記』 | robotryex-s.htm | 2001-01-03 | honkan |
| `novel__robot_triathlon__robotryex-s02.htm` | ロボＴＲＹ・ＥＸ−Ｓ『さおりん歳時記……の続き（笑）』 | robotryex-s02.htm | 2002-07-20 | honkan |
| `novel__robot_triathlon__robotryex-s03.htm` | ロボＴＲＹ・ＥＸ−Ｓ『さおりんとりびゅーと』 | robotryex-s03.htm | 2003-01-04 | honkan |
| `novel__robot_triathlon__robotry_ex4a.htm` | ロボＴＲＹ・ＥＸ−４『ＨＥＲＯ美少女変身騒動っ（前編）』 | robotry_ex4a.htm | 2003-01-22 | honkan |
| `novel__robot_triathlon__robotry_ex4b.htm` | ロボＴＲＹ・ＥＸ−４『ＨＥＲＯ美少女変身騒動っ（後編）』 | robotry_ex4b.htm | 2004-08-06 | honkan |
| `novel__robot_triathlon__robotryex-s04.htm` | 私たちをスキーに連れてって♪ | robotryex-s04.htm | 2005-04-15 | honkan |
| `novel__robot_triathlon__robotry_ex4c.htm` | ＨＥＲＯ美少女変身騒動（完結編） | robotry_ex4c.htm | 2006-09-28 | honkan |
| `novel__robot_triathlon__robotryex-s05.htm` | さおりんとりびゅーと０３『運命の再戦？』 | robotryex-s05.htm | 2008-04-28 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ロボTRY・EX−4『HERO美少女変身騒動っ」: 7 話
  - 芯「私たちをスキーに連れてって♪」: 1 話
  - 芯「HERO美少女変身騒動」: 1 話
  - 芯「さおりんとりびゅーと03『運命の再戦?」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "mondo|ロボＴＲＹ美少女っ　シリーズ":
    confirmed: true
```

</details>

### 61. `ohharano-yuukiarutatakai` — 勇気ある戦い

- 根拠: `series-title-nav` / 話数: 3 / 作者: 大原野山城守武里 (`ohharano`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200611__21212001__brave.htm` | 勇気ある戦い | brave.htm | 2010-02-21 | honkan |
| `novel__200611__21212001__manoyama.htm` | 魔の山へ飛べ | manoyama.htm | 2010-07-18 | honkan |
| `novel__200611__21212001__lake.htm` | 湖のひみつ | lake.htm | 2011-07-23 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「勇気ある戦い」: 1 話
  - 芯「魔の山へ飛べ」: 1 話
  - 芯「湖のひみつ」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "ohharano|勇気ある戦い":
    confirmed: true
```

</details>

### 62. `omochibako-mahoushoujoshugaa-sandee` — 魔法少女シュガー☆サンデー

- 根拠: `filename-stem`, `series-title-nav`, `title-page`, `title-prefix` / 話数: 10 / 作者: おもちばこ (`omochibako`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200802__15232641__sundaes.html` | シュガー☆サンデー | sundaes.html | 2008-02-01 | uncatalogued |
| `novel__200802__15232641__sundae1.html` | 魔法少女シュガー☆サンデー 第１話 | sundae1.html | 2008-02-15 | honkan |
| `novel__200802__15232641__sundae2.html` | 魔法少女シュガー☆サンデー　第２話 | sundae2.html | 2008-03-02 | honkan |
| `novel__200802__15232641__sundae3.html` | 魔法少女シュガー☆サンデー第３話 | sundae3.html | 2008-03-23 | honkan |
| `novel__200802__15232641__sundae4.html` | 魔法少女シュガー☆サンデー第４話 | sundae4.html | 2008-04-16 | honkan |
| `novel__200802__15232641__sundae5.html` | 魔法少女シュガー☆サンデー第５話 | sundae5.html | 2008-07-04 | honkan |
| `novel__200802__15232641__sundae6.html` | 魔法少女シュガー☆サンデー第６話 | sundae6.html | 2008-10-26 | honkan |
| `novel__200802__15232641__sundae7.html` | 魔法少女シュガー☆サンデー第７話 | sundae7.html | 2009-05-26 | honkan |
| `novel__200802__15232641__sundae8.html` | 魔法少女シュガー☆サンデー第８話 | sundae8.html | 2009-08-17 | honkan |
| `novel__200802__15232641__sundae9.html` | 魔法少女シュガー☆サンデー第９話 | sundae9.html | 2009-09-27 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「魔法少女シュガー☆サンデー」: 9 話
  - 芯「シュガー☆サンデー」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "omochibako|魔法少女シュガー☆サンデー":
    confirmed: true
```

</details>

### 63. `popof-uchuujintoshounen` — 宇宙人と少年

- 根拠: `episode-nav`, `filename-stem` / 話数: 4 / 作者: ポポフ (`popof`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200506__21202425__poan01.htm` | 宇宙人と少年 | poan01.htm | 2005-06-21 | honkan |
| `novel__200506__21202425__poan02.htm` | 危険人物　宇宙人と少年第２話 | poan02.htm | 2005-07-26 | honkan |
| `novel__200506__21202425__poan03.htm` | 三人目　宇宙人と少年第３話 | poan03.htm | 2005-09-26 | honkan |
| `novel__200506__21202425__poan04.htm` | 歯医者さん　宇宙人と少年第４話 | poan04.htm | 2005-11-16 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「宇宙人と少年」: 1 話
  - 芯「危険人物 宇宙人と少年」: 1 話
  - 芯「三人目 宇宙人と少年」: 1 話
  - 芯「歯医者さん 宇宙人と少年」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "popof|宇宙人と少年":
    confirmed: true
```

</details>

### 64. `ryuhju-let-s-oobaahiito` — Let's オーバーヒート♪

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 9 / 作者: 龍酒 (`ryuhju`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__18230553__over_heat.html` | Let's オーバーヒート♪ | over_heat.html | 2001-12-01 | uncatalogued |
| `novel__200112__18230553__over_heat1.html` | Let's　オーバーヒート♪ | over_heat1.html | 2001-12-18 | honkan |
| `novel__200112__18230553__over_heat2.html` | 続・Let's　オーバーヒート♪ | over_heat2.html | 2002-01-31 | honkan |
| `novel__200112__18230553__over_heat3.html` | 続々・Let's　オーバーヒート♪ | over_heat3.html | 2002-03-20 | honkan |
| `novel__200112__18230553__over_heat4.html` | 続々々・Let's　オーバーヒート♪ | over_heat4.html | 2002-06-18 | honkan |
| `novel__200112__18230553__over_heat5.html` | 続々々々・Let's　オーバーヒート♪ | over_heat5.html | 2002-08-25 | honkan |
| `novel__200112__18230553__over_heat6.html` | 続々々々々・Let's　オーバーヒート♪ | over_heat6.html | 2002-09-10 | honkan |
| `novel__200112__18230553__over_heat7.html` | 続々々々々々・Let's　オーバーヒート♪ | over_heat7.html | 2003-01-05 | honkan |
| `novel__200112__18230553__over_heat8.html` | 続々々々々々々・Let's　オーバーヒート♪ | over_heat8.html | 2003-04-03 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「続々・Let's オーバーヒート♪」: 6 話
  - 芯「Let's オーバーヒート♪」: 2 話
  - 芯「続・Let's オーバーヒート♪」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "ryuhju|Let's オーバーヒート♪":
    confirmed: true
```

</details>

### 65. `shining_heaven-hanayochanshiriizu` — 華代ちゃんシリーズ

- 根拠: `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 10 / 作者: 天爛 (`shining_heaven`) / 複数ディレクトリ

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__kayo_chan__extra__kayo_ex42.htm` | ハンター・シリーズ「三身一対〜30番目の多重人格者〜 | kayo_ex42.htm | 2006-05-27 | honkan |
| `novel__kayo_chan__extra__kayo_ex44.htm` | 華代ちゃん・シリーズ番外編＆ハンター・シリーズ『未来への手紙』 | kayo_ex44.htm | 2006-06-04 | honkan |
| `novel__kayo_chan__extra__kayo_ex46.html` | ハンター・シリーズ『ハンター捕物帳〜３０号の場合〜』 | kayo_ex46.html | 2006-06-21 | honkan |
| `novel__200607__02124625__nvl__kayo_ex1.html` | 華代ちゃんシリーズ番外編『小夜神楽』・試作 | kayo_ex1.html | 2006-07-01 | uncatalogued |
| `novel__200607__02124625__nvl__kayo_ex2.html` | 華代ちゃんシリーズ番外編『あいうえお』 | kayo_ex2.html | 2006-07-01 | uncatalogued |
| `novel__200607__02124625__nvl__kayo_ex3_2.html` | 華代ちゃんシリーズ番外編『理想の麗人』 | kayo_ex3_2.html | 2006-07-01 | uncatalogued |
| `novel__200607__02124625__nvl__kayo_ex3.html` | 華代ちゃんシリーズ番外編『女装の麗人』 | kayo_ex3.html | 2006-07-01 | uncatalogued |
| `novel__kayo_chan__extra__kayo_ex57.htm` | 華代ちゃんシリーズ番外編「『女装の麗人』改め『普通の麗人』」 | kayo_ex57.htm | 2008-02-03 | honkan |
| `novel__kayo_chan__extra__kayo_ex58.html` | ハンターシリーズ「いつか出会う未来」 | kayo_ex58.html | 2008-02-26 | honkan |
| `novel__kayo_chan__extra__kayo_ex64.htm` | ハンターシリーズ「いまだ来ぬ過去」 | kayo_ex64.htm | 2009-02-04 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「華代ちゃんシリーズ」: 6 話
  - 芯「ハンター・シリーズ「三身一対〜30番目の多重人格者」: 4 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "shining_heaven|華代ちゃんシリーズ":
    confirmed: true
```

</details>

### 66. `tenki-zweihandervts-presented-by-tenki-hanamura` — ZweihanderVTS (Presented by Tenki Hanamura)

- 根拠: `filename-stem`, `series-title-nav`, `title-page` / 話数: 4 / 作者: 華村天稀 (`tenki`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200512__22203201__zhvts_top.html` | ZweihanderVTS (Presented by Tenki Hanamura) | zhvts_top.html | 2005-12-01 | uncatalogued |
| `novel__200512__22203201__zhvts_c00.html` | 発端と変革 - ZweihanderVTS | zhvts_c00.html | 2005-12-28 | honkan |
| `novel__200512__22203201__zhvts_c01.html` | 邂逅と激怒 - ZweihanderVTS | zhvts_c01.html | 2006-01-08 | honkan |
| `novel__200512__22203201__zhvts_c02.html` | 魔神と女神 - ZweihanderVTS | zhvts_c02.html | 2006-01-27 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-title-nav` / `title-page` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ZweihanderVTS (Presented by Tenki Hanamura」: 1 話
  - 芯「発端と変革 - ZweihanderVTS」: 1 話
  - 芯「邂逅と激怒 - ZweihanderVTS」: 1 話
  - 芯「魔神と女神 - ZweihanderVTS」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "tenki|ZweihanderVTS (Presented by Tenki Hanamura)":
    confirmed: true
```

</details>

### 67. `toisan-gurandof` — グランドＦ

- 根拠: `episode-nav`, `filename-stem` / 話数: 2 / 作者: ＴＯＩＳＡＮ (`toisan`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200209__13173229__grand_f.htm` | グランドＦ | grand_f.htm | 2002-09-13 | honkan |
| `novel__200209__13173229__grand_f2.htm` | 光と闇の間 —グランドＦ 第二章— | grand_f2.htm | 2002-09-19 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「グランドF」: 1 話
  - 芯「光と闇の間 —グランドF」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "toisan|グランドＦ":
    confirmed: true
```

</details>

### 68. `yaji-koncherutowomouichido` — コンチェルトをもう一度

- 根拠: `filename-stem`, `series-html` / 話数: 7 / 作者: 矢治浩平 (`yaji`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__concerto__concerto_1.html` | コンチェルトをもう一度 | concerto_1.html | 1998-09-20 | honkan |
| `novel__concerto__concerto_2.html` | 続・コンチェルトをもう一度 | concerto_2.html | 1998-09-20 | honkan |
| `novel__concerto__concerto_3.html` | 通り雨（コンチェルトをもう一度・シリーズ） | concerto_3.html | 1998-09-23 | honkan |
| `novel__concerto__concerto_4.html` | 一年目のクリスマス（コンチェルトシリーズ） | concerto_4.html | 1998-11-16 | honkan |
| `novel__concerto__concerto_5.html` | チョコレートに想いを込めて（コンチェルト・シリーズ） | concerto_5.html | 1999-02-11 | honkan |
| `novel__concerto__white_day.html` | 一番素敵な贈り物 | white_day.html | 1999-03-13 | honkan |
| `novel__concerto__pool.html` | ある暑い日 | pool.html | 1999-08-03 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `series-html` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「コンチェルトをもう一度」: 1 話
  - 芯「続・コンチェルトをもう一度」: 1 話
  - 芯「通り雨(コンチェルトをもう一度・シリーズ」: 1 話
  - 芯「一年目のクリスマス(コンチェルトシリーズ」: 1 話
  - 芯「チョコレートに想いを込めて(コンチェルト・シリーズ」: 1 話
  - 芯「一番素敵な贈り物」: 1 話
  - 芯「ある暑い日」: 1 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yaji|コンチェルトをもう一度":
    confirmed: true
```

</details>

### 69. `yamaguti_oomi-dokuritsunanpouhikoutai-shirayuributai` — 独立南方飛行隊・白百合部隊

- 根拠: `episode-nav`, `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 18 / 作者: 山口多聞 (`yamaguti_oomi`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200407__31143808__lily01.htm` | 独立南方飛行隊・白百合部隊 | lily01.htm | 2004-09-11 | honkan |
| `novel__200407__31143808__lily02.htm` | 独立南方飛行隊・白百合部隊　第二話　初出撃 | lily02.htm | 2004-09-11 | honkan |
| `novel__200407__31143808__lily03.htm` | 南方独立飛行隊・白百合部隊　第三話 | lily03.htm | 2004-09-25 | honkan |
| `novel__200407__31143808__lily04.htm` | 南方独立飛行隊・白百合部隊　第四話 | lily04.htm | 2004-10-01 | honkan |
| `novel__200407__31143808__lily05.htm` | 南方独立飛行隊・白百合部隊　第五話 | lily05.htm | 2004-10-11 | honkan |
| `novel__200407__31143808__lily_ex01.htm` | 本土独立防空部隊・白百合部隊　外伝「廣島」前編 | lily_ex01.htm | 2004-10-18 | honkan |
| `novel__200407__31143808__lily_ex02.htm` | 本土独立防空部隊・白百合部隊　外伝「廣島」中編 | lily_ex02.htm | 2004-10-23 | honkan |
| `novel__200407__31143808__lily_ex03.htm` | 本土独立防空部隊・白百合部隊　外伝「廣島」後編 | lily_ex03.htm | 2004-10-27 | honkan |
| `novel__200407__31143808__lily06.htm` | 南方独立飛行部隊・白百合部隊　第６話「猛攻」 | lily06.htm | 2004-11-04 | honkan |
| `novel__200407__31143808__lily07.htm` | 南方独立飛行部隊・白百合部隊　第７話「強襲」 | lily07.htm | 2004-11-18 | honkan |
| `novel__200407__31143808__lily_ex04.htm` | 白百合部隊外伝「誕生の時」 | lily_ex04.htm | 2004-12-13 | honkan |
| `novel__200407__31143808__lily_ex05.htm` | 白百合部隊外伝　白梅部隊の戦闘　前編 | lily_ex05.htm | 2005-01-05 | honkan |
| `novel__200407__31143808__lily_ex06.htm` | 白百合部隊外伝　白梅部隊の戦闘　後編 | lily_ex06.htm | 2005-01-18 | honkan |
| `novel__200407__31143808__lily_h01.htm` | 本土独立防空部隊白百合部隊　第一話　「帰還」 | lily_h01.htm | 2005-02-12 | honkan |
| `novel__200407__31143808__lily_h02.htm` | 本土独立防空部隊白百合部隊　第２話　「束の間の」 | lily_h02.htm | 2005-03-30 | honkan |
| `novel__200407__31143808__lily_h03.htm` | 本土独立防空部隊白百合部隊　第３話　「鉄鯱狩り」 | lily_h03.htm | 2005-04-30 | honkan |
| `novel__200407__31143808__lily_h04.htm` | 本土独立防空部隊白百合部隊　第４話　「本土初防空戦」 | lily_h04.htm | 2005-08-01 | honkan |
| `novel__200407__31143808__lily_h05.htm` | 本土独立防空部隊白百合部隊　第５話　「戦闘開始」 | lily_h05.htm | 2005-11-08 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` / `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「本土独立防空部隊白百合部隊」: 8 話
  - 芯「南方独立飛行隊・白百合部隊」: 5 話
  - 芯「白百合部隊」: 3 話
  - 芯「独立南方飛行隊・白百合部隊」: 2 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yamaguti_oomi|独立南方飛行隊・白百合部隊":
    confirmed: true
```

</details>

### 70. `yamu-misutoresutokimyounanakamatachi` — ミストレスと奇妙な仲間達

- 根拠: `episode-nav`, `filename-stem`, `series-title-nav`, `title-prefix` / 話数: 40 / 作者: 夜夢 (`yamu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__200112__18123055__mistress.html` | ミストレスと奇妙な仲間達　その１ | mistress.html | 2001-12-18 | honkan |
| `novel__200112__18123055__mistress2.html` | ミストレスと奇妙な仲間達　その２ | mistress2.html | 2002-01-15 | honkan |
| `novel__200112__18123055__mistress3.html` | ミストレスと奇妙な仲間達 その３ | mistress3.html | 2002-01-21 | honkan |
| `novel__200112__18123055__mistress4.html` | ミストレスと奇妙な仲間達〜その４ | mistress4.html | 2002-02-16 | honkan |
| `novel__200112__18123055__mistress5.html` | ミストレスと奇妙な仲間達〜その５ | mistress5.html | 2002-02-16 | honkan |
| `novel__200112__18123055__mistress6.html` | ミストレスと奇妙な仲間達〜その６ | mistress6.html | 2002-04-04 | honkan |
| `novel__200112__18123055__mistress7.htm` | ミストレスと奇妙な仲間達〜その７ | mistress7.htm | 2002-05-05 | honkan |
| `novel__200112__18123055__mistress8.htm` | ミストレスと奇妙な仲間達〜その８ | mistress8.htm | 2002-05-05 | honkan |
| `novel__200112__18123055__e-ena01.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第一話 | e-ena01.htm | 2002-05-15 | honkan |
| `novel__200112__18123055__e-ena02.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第二話 | e-ena02.htm | 2002-06-07 | honkan |
| `novel__200112__18123055__e-ena03.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第三話 | e-ena03.htm | 2002-06-23 | honkan |
| `novel__200112__18123055__mistress9.htm` | ミストレスと奇妙な仲間達〜その９ | mistress9.htm | 2002-07-12 | honkan |
| `novel__200112__18123055__mistress10.htm` | ミストレスと奇妙な仲間達〜その10 | mistress10.htm | 2002-07-29 | honkan |
| `novel__200112__18123055__e-ena04.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第四話 | e-ena04.htm | 2002-08-11 | honkan |
| `novel__200112__18123055__mistress11.htm` | ミストレスと奇妙な仲間達〜その11 | mistress11.htm | 2002-09-10 | honkan |
| `novel__200112__18123055__mistress11-2nd.htm` | ミストレスと奇妙な仲間達〜その11（改訂版） | mistress11-2nd.htm | 2002-09-28 | honkan |
| `novel__200112__18123055__e-ena05.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第五話 | e-ena05.htm | 2002-09-29 | honkan |
| `novel__200112__18123055__mistress12.htm` | ミストレスと奇妙な仲間達〜その12 | mistress12.htm | 2002-10-27 | honkan |
| `novel__200112__18123055__mistress13.htm` | ミストレスと奇妙な仲間達〜その13 | mistress13.htm | 2002-10-27 | honkan |
| `novel__200112__18123055__e-ena06.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第六話 | e-ena06.htm | 2002-11-10 | honkan |
| `novel__200112__18123055__mistress14.htm` | ミストレスと奇妙な仲間達〜その14 | mistress14.htm | 2002-11-20 | honkan |
| `novel__200112__18123055__mistress15.htm` | ミストレスと奇妙な仲間達〜その15 | mistress15.htm | 2002-12-18 | honkan |
| `novel__200112__18123055__mistress16.html` | ミストレスと奇妙な仲間達〜その１６ | mistress16.html | 2003-01-18 | honkan |
| `novel__200112__18123055__mistress16-b.htm` | ミストレスと奇妙な仲間達〜その16の裏 | mistress16-b.htm | 2003-04-28 | honkan |
| `novel__200112__18123055__mistress17.htm` | ミストレスと奇妙な仲間達〜その17 | mistress17.htm | 2003-04-28 | honkan |
| `novel__200112__18123055__e-ena07.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第七話 | e-ena07.htm | 2003-05-06 | honkan |
| `novel__200112__18123055__mistress18.htm` | ミストレスと奇妙な仲間達〜その18 | mistress18.htm | 2003-06-07 | honkan |
| `novel__200112__18123055__mistress19.html` | ミストレスと奇妙な仲間達〜その19 | mistress19.html | 2003-07-11 | honkan |
| `novel__200112__18123055__e-ena08.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第八話 | e-ena08.htm | 2003-08-18 | honkan |
| `novel__200112__18123055__mistress20.html` | ミストレスと奇妙な仲間達〜その20 | mistress20.html | 2003-09-20 | honkan |
| `novel__200112__18123055__e-ena09.html` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第九話 | e-ena09.html | 2003-11-15 | honkan |
| `novel__200112__18123055__e-ena10.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第十話 | e-ena10.htm | 2003-12-08 | honkan |
| `novel__200112__18123055__e-ena11.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第十一話 | e-ena11.htm | 2003-12-21 | honkan |
| `novel__200112__18123055__e-ena12.htm` | 電光の魔法少女エレクトリック・イーナ　十二封妖編第十二話 | e-ena12.htm | 2004-01-01 | honkan |
| `novel__200112__18123055__kenzya01.html` | 狂才!?沼池教授の生活　賢者の石（？）争奪編第一話 | kenzya01.html | 2004-06-15 | honkan |
| `novel__200112__18123055__kenzya02.html` | 狂才!?沼池教授の生活 賢者の石（？）争奪編　第二話：流れ始める事態 | kenzya02.html | 2005-03-05 | honkan |
| `novel__200112__18123055__kenzya03.html` | 狂才!?沼池教授の生活 賢者の石（？）争奪編　第三話：萌え出ずる者達 | kenzya03.html | 2005-06-05 | honkan |
| `novel__200112__18123055__mistress21.htm` | ミストレスと奇妙な仲間達〜その21 | mistress21.htm | 2009-03-20 | honkan |
| `novel__200112__18123055__mistress22.html` | ミストレスと奇妙な仲間達〜その22 | mistress22.html | 2009-11-14 | honkan |
| `novel__200112__18123055__mistress_ng.html` | ミストレスと奇妙な仲間達〜NG | mistress_ng.html | 2010-12-24 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` / `series-title-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「ミストレスと奇妙な仲間達」: 25 話
  - 芯「電光の魔法少女エレクトリック・イーナ 十二封妖編」: 12 話
  - 芯「狂才!?沼池教授の生活 賢者の石(?)争奪編」: 3 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yamu|ミストレスと奇妙な仲間達":
    confirmed: true
```

</details>

### 71. `yamu-mouhitorino-nijinohitomi-nomonogatari` — もう一人の“虹の瞳”の物語

- 根拠: `episode-nav`, `filename-stem`, `title-prefix` / 話数: 5 / 作者: 夜夢 (`yamu`)

| episode_id | 題名 | ファイル名 | 掲載日 | corpus |
|---|---|---|---|---|
| `novel__rainbow__niji01.html` | 虹の瞳と呼ばれて | niji01.html | 1999-08-30 | honkan |
| `novel__rainbow__niji02.html` | 虹の瞳を得たのは | niji02.html | 2000-02-25 | honkan |
| `novel__rainbow__niji2_01.htm` | もう一人の“虹の瞳”の物語　第一話 | niji2_01.htm | 2001-08-16 | honkan |
| `novel__rainbow__niji2_02.htm` | もう一人の“虹の瞳”の物語　第二話彼の方との出会い | niji2_02.htm | 2001-09-16 | honkan |
| `novel__rainbow__niji2_03.htm` | もう一人の“虹の瞳”の物語　第三話“もう一つの・・・” | niji2_03.htm | 2001-10-26 | honkan |

**裁定** (○ / 分割 / 切り離し / 併合。分割なら話の振り分け):

- 一次案: **○** (強い根拠 `episode-nav` で繋がっている。芯の違いは参考として下に並べる)
  - 芯「もう一人の“虹の瞳”の物語」: 3 話
  - 芯「虹の瞳と呼ばれて」: 2 話

<details><summary>一次案を work_overrides.yml に写したときの形</summary>

```yaml
  "yamu|もう一人の“虹の瞳”の物語":
    confirmed: true
```

</details>

## slug が変わった作品 (7d4778f4 との比較)

題名の芯の追加規則 (specs/S.148.md) などで work_slug が変わった作品。話の所属で突き合わせた。本番の URL の付け替え (T.24.6) は本番の `export-pathmap` の差分で作るので、これはその下調べ。

- works: 1451 → 1440 / slug が変わった作品: **28** (うち他の作品と束ね直されたもの 16)

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
