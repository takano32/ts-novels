#!/usr/bin/env bash
# 配備の手順 (specs/S242.md の「配備の手順」) を一式で流す (T45.6.1・T45.6.2。高品位なモデルとエフォートの所管 — 本番を触るコード)。
# 2026-11-05 の配備 (T45.3.3) で scratchpad から流したスクリプトをリポジトリに入れたもの。
#
# 既定は DRY-RUN。本番に書くのは --apply のときだけ。dry-run でも、読むだけの段 (見え方・md5・取り下げの印) は
# 本当に流し、書く段 (復旧点・メンテナンス表示・rsync・削除) は何をするかを表示する。
#   scripts/wp/deploy_run.sh                 # dry-run
#   scripts/wp/deploy_run.sh --apply         # 実配備
#   scripts/wp/deploy_run.sh --out DIR       # 見え方とログの置き場 (既定 .deploy/<日時>。git 管理外)
#
# 段 (本番に書く段に *):
#    1  手元の確認: HEAD が origin/master の最新で、mu-plugins/・themes/ に未コミットの変更が無い
#    2  配備前の見え方を取る (scripts/wp/deploy_snapshot.sh take)
#    3* 復旧点 wp db export ~/dumps/pre-deploy-<日時>.sql (S67)
#    4* deploy.sh --skip-php: 構文検査・サーバ側 repo・生成物 (bodies/・payloads/)・robots.txt・画像
#    5* メンテナンス表示を出す (public_html/.maintenance。読者には 503)。WordPress は 10 分より古い表示を無視するので、
#       時間のかかる生成物の rsync (4) の後、PHP を置く直前に出す
#    6* deploy.sh --php-only: 構文検査・mu-plugin・テーマ (この間だけ新旧の mu-plugin とテーマが混ざる。S242)
#    7* 古い includes の 7 本 (S242 の「配備のときに本番から消すファイル」): 残っていて md5 が合うものだけ消す
#    8  新しい mu-plugin とテーマが読めることの確認 (T45.6.2): 手元の HEAD から集めたクラス (includes/ の 3 表) と、テーマが
#       呼ぶ関数 (scripts/wp/tests/files.php の一覧と同じ 20 本) と ts_bunko_index_<種類> が本番で定義されているかを
#       wp eval-file で見る。WP-CLI は表示の間も動く (Runner が enable_maintenance_mode を false にする)。
#       欠けがあれば実配備は表示を残して止まる
#    9* メンテナンス表示を消す
#   10  配備後の見え方を取って前と比べる (deploy_snapshot.sh diff)
#   11  取り下げの印の確認 (wp ts apply-takedown --dry-run と private・draft の数。DB は変えない。S67)
#
# 途中で止まったときは、メンテナンス表示は消さずに残す (本番が途中の状態かもしれないため。10 分で自然に切れる)。
# 消し方は止まったときの表示に出る。
# 安全則 (S67): rsync に --delete は使わない (deploy.sh の中)。rm は 7 本の名前の表に限る。DB は復旧点を取るだけで変えない。
set -euo pipefail

LOCAL_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RSH="$LOCAL_ROOT/scripts/wp/novels-ssh"
SNAP="$LOCAL_ROOT/scripts/wp/deploy_snapshot.sh"
R='cd ~/novels.xwp.jp/public_html'
INC='~/novels.xwp.jp/public_html/wp-content/mu-plugins/ts-library/includes'
STAMP=$(date +%Y%m%d-%H%M)

# 本番に残り得る古い includes と、分割前の版の md5 (S242 の「配備のときに本番から消すファイル」の実測値)。
# 消すのはこの表の名前で、md5 が同じものだけ
OLD_INCLUDES='
commands.php          83e6639e94aa935d00bd655cfcc1886e
indexes.php           3d97780ddc6b22bb9c092c185d35d0d3
render.php            f9362429b75721d57ed8911d956fe67c
roles.php             c76e6c6a396f81e17ae46ceb874c92d1
takedown-admin.php    e5c5b4aa3caf33461b63a808d1e37c3d
takedown-request.php  af2fd7636e3ffc06bf9e0fa89555d4ef
visibility.php        39caa15ffbc2d5e3b9bff8734c74661a
'

APPLY=0
OUT=''
while (( $# )); do
  case "$1" in
    --apply) APPLY=1 ;;
    --out)   OUT="${2:?--out DIR}"; shift ;;
    *) sed -n '2,30p' "$0"; exit 2 ;;
  esac
  shift
done
OUT="${OUT:-$LOCAL_ROOT/.deploy/$STAMP}"
mkdir -p "$OUT"
exec > >(tee -a "$OUT/deploy.log") 2>&1

APPLY_FLAG=''
(( APPLY )) && APPLY_FLAG='--apply '
MODE='dry-run。本番には書かない'
(( APPLY )) && MODE='実配備'
echo "== 配備の手順 ($MODE) $(date '+%Y-%m-%d %H:%M')。記録: $OUT"

N=0
step() { N=$((N + 1)); echo; echo "== [$N] $*"; }
WARN=0
warn() { WARN=$((WARN + 1)); echo "!! $*"; }
# 本番に書く操作。dry-run では何をするかを表示するだけ
remote_write() {
  if (( APPLY )); then "$RSH" "$1"; else echo "(dry-run) novels-ssh: $1"; fi
}
# 手元の HEAD から「あるべき名前」を集め、本番の WordPress の中で class_exists / function_exists を見る PHP を作る (T45.6.2)。
# クラスは includes/ の class 宣言 (TS_Library_Files の 3 表の全ファイル。wp eval-file は WP_CLI なので CLI の表も読まれる)。
# 関数はテーマが呼ぶ ts_ の関数 (scripts/wp/tests/files.php の一覧と同じ集め方) と、ts-index.php が TS_Index_Text::KINDS の名前で
# 動的に呼ぶ ts_bunko_index_<種類> (本番の定数から作る)。名前は英数字と _ だけなので sed の置換で埋める
definitions_php() {
  local classes functions
  classes=$(grep -rhoE '^[[:space:]]*(final |abstract )?class [A-Za-z_0-9]+' "$LOCAL_ROOT/mu-plugins/ts-library/includes" \
            | awk '{print $NF}' | sort -u | tr '\n' ' ')
  functions=$(grep -hoE '\bts_[a-z_0-9]+\(' "$LOCAL_ROOT"/themes/ts-bunko/*.php | tr -d '(' | sort -u | tr '\n' ' ')
  sed -e "s/__CLASSES__/${classes% }/" -e "s/__FUNCTIONS__/${functions% }/" <<'EOF'
<?php
// scripts/wp/deploy_run.sh が作って wp eval-file - に流す (読むだけ)
$classes = explode(' ', '__CLASSES__');
$functions = explode(' ', '__FUNCTIONS__');
$kinds = class_exists('TS_Index_Text') ? TS_Index_Text::KINDS : [];
$missing = [];
foreach ($classes as $c) if (!class_exists($c)) $missing[] = "クラス $c";
foreach ($functions as $f) if (!function_exists($f)) $missing[] = "関数 $f";
if (!$kinds) $missing[] = '定数 TS_Index_Text::KINDS';
foreach ($kinds as $k) if (!function_exists("ts_bunko_index_$k")) $missing[] = "関数 ts_bunko_index_$k";
printf("定義の確認: クラス %d・テーマが呼ぶ関数 %d・索引の関数 %d (テーマ %s)、欠け %d\n",
       count($classes), count($functions), count($kinds), get_stylesheet(), count($missing));
foreach ($missing as $m) echo "!! 無い: $m\n";
exit($missing ? 1 : 0);
EOF
}

MAINT_ON=0
MAINT_T0=0
DONE=0
on_exit() {
  local rc=$?
  if (( rc != 0 && DONE == 0 )); then
    echo
    echo "!! 途中で止まった (終了コード $rc)。記録: $OUT/deploy.log"
    if (( MAINT_ON )); then
      echo "!! メンテナンス表示は消していない (本番が途中の状態かもしれないため。出してから 10 分で WordPress が無視する)。"
      echo "!! 直して流し直すか、本番を確かめてから手で消す: scripts/wp/novels-ssh '$R && rm -f .maintenance'"
    fi
  fi
}
trap on_exit EXIT

step "手元の確認 (origin/master の最新か、mu-plugins/・themes/ に未コミットの変更が無いか)"
git -C "$LOCAL_ROOT" fetch -q origin master
HEAD_LOCAL=$(git -C "$LOCAL_ROOT" rev-parse HEAD)
if [ "$HEAD_LOCAL" != "$(git -C "$LOCAL_ROOT" rev-parse origin/master)" ]; then
  echo "!! 手元 HEAD ($(git -C "$LOCAL_ROOT" rev-parse --short HEAD)) が origin/master ($(git -C "$LOCAL_ROOT" rev-parse --short origin/master)) と違う。git pull --ff-only してから"
  exit 1
fi
if [ -n "$(git -C "$LOCAL_ROOT" status --porcelain -- mu-plugins themes)" ]; then
  git -C "$LOCAL_ROOT" status --short -- mu-plugins themes
  echo "!! mu-plugins/ か themes/ に未コミットの変更がある。rsync は作業木を送るので、コミットして push するか戻してから"
  exit 1
fi
echo "-- HEAD $HEAD_LOCAL = origin/master、mu-plugins/ と themes/ はコミットどおり"

step "配備前の見え方を取る (読むだけ): $OUT/pre"
rm -rf "$OUT/pre" "$OUT/post"
"$SNAP" take "$OUT/pre"

DUMP="~/dumps/pre-deploy-$STAMP.sql"
step "復旧点 $DUMP (S67。ダンプは私有ストレージへ退避し、リポジトリに入れない)"
remote_write "mkdir -p ~/dumps && $R && wp db export $DUMP && ls -la $DUMP"

step "配備 (前半。PHP 以外): scripts/wp/deploy.sh $APPLY_FLAG--skip-php"
"$LOCAL_ROOT/scripts/wp/deploy.sh" $APPLY_FLAG --skip-php

step "メンテナンス表示を出す (public_html/.maintenance。読者には 503。WP-CLI は表示の間も動く)"
"$RSH" "$R && if [ -e .maintenance ]; then echo 'いまの本番: .maintenance あり'; cat .maintenance; echo; else echo 'いまの本番: .maintenance 無し'; fi"
# WordPress 本体 (WP_Upgrader::maintenance_mode) と同じく時刻を数で書く。time() と書くと読むたびに今になり、10 分で切れない。
# 書く前に印を立てる (書けたか分からずに止まったときも、止まったときの表示に消し方が出るように)
if (( APPLY )); then MAINT_ON=1; MAINT_T0=$(date +%s); fi
remote_write "$R && printf '<?php \$upgrading = %s; ?>\n' \"\$(date +%s)\" > .maintenance && cat .maintenance"

step "配備 (後半。mu-plugin とテーマ): scripts/wp/deploy.sh $APPLY_FLAG--php-only"
"$LOCAL_ROOT/scripts/wp/deploy.sh" $APPLY_FLAG --php-only

step "古い includes の 7 本: 残っていて md5 が分割前の版と同じものだけ消す (2026-11-05 に消し済みなので、いまは全部「無い」が正しい)"
# 1 回の接続で md5sum を取る。無いファイルは出ない (2>/dev/null)。includes/ 自体が無いのは配備の失敗なので止まる
GOT=$("$RSH" "cd $INC || exit 1; md5sum $(awk 'NF {print $1}' <<< "$OLD_INCLUDES" | tr '\n' ' ') 2>/dev/null; true")
TO_RM=()
while read -r f want; do
  [ -n "$f" ] || continue
  have=$(awk -v f="$f" '$2 == f {print $1}' <<< "$GOT")
  if [ -z "$have" ]; then
    echo "-- 無い: $f"
  elif [ "$have" = "$want" ]; then
    echo "-- 消す (md5 が分割前の版と同じ): $f"
    TO_RM+=("$INC/$f")
  else
    warn "消さない (md5 が分割前の版と違う $have。手で見る): $f"
  fi
done <<< "$OLD_INCLUDES"
if (( ${#TO_RM[@]} )); then
  remote_write "rm -- ${TO_RM[*]} && echo '消した: ${#TO_RM[@]} 本'"
else
  echo "-- 消すものは無い"
fi

step "新しい mu-plugin とテーマが読めることの確認 (wp eval-file。読むだけ。WP-CLI は表示の間も動く)"
if definitions_php | "$RSH" "$R && wp eval-file -"; then
  echo "-- 欠け 0"
elif (( APPLY )); then
  echo "!! 定義が欠けている (上の !! の行)。新しい PHP を置いた後なので、メンテナンス表示を残して止まる"
  exit 1
else
  warn "定義が欠けている。dry-run は配備前の本番を見るので、手元で足した名前が無いのは正しい (実配備では表示を残して止まる)"
fi

step "メンテナンス表示を消す"
remote_write "$R && rm -f .maintenance && if [ -e .maintenance ]; then echo '!! .maintenance がまだある'; exit 1; else echo 'メンテナンス表示なし'; fi"
if (( APPLY )); then
  MAINT_ON=0
  echo "-- 表示を出していた時間: $(( $(date +%s) - MAINT_T0 )) 秒 (600 秒を超えると WordPress が無視する)"
fi

step "配備後の見え方を取って前と比べる (読むだけ): $OUT/post"
"$SNAP" take "$OUT/post"
if "$SNAP" diff "$OUT/pre" "$OUT/post"; then
  echo "-- 前後で同じ"
else
  warn "前後で違いがある (上の diff)。意図した変更かを見る。本文の違いは $OUT/pre/body と $OUT/post/body を比べる"
fi

step "取り下げの印の確認 (読むだけ。DB は変えない)"
"$RSH" "$R && wp ts apply-takedown --dry-run 2>&1 | tail -3"
"$RSH" "$R && echo private=\$(wp post list --post_type=ts_work --post_status=private --format=count) draft=\$(wp post list --post_type=ts_work --post_status=draft --format=count)"
echo "-- 「private 化 0 / 復帰 0」でなければ denylist の変化が DB に未反映。S69 の手順で wp ts apply-takedown を別に流す。draft は 0、private は取り下げの件数 (S67)"

echo
if (( APPLY )); then
  echo "== 配備が終わった ($(date '+%H:%M'))。次: nginx のキャッシュのクリアを頼む (T38.2)。記録: $OUT"
else
  echo "== DRY-RUN でした。本番には書いていない。実配備は: scripts/wp/deploy_run.sh --apply"
fi
DONE=1
if (( WARN )); then
  echo "!! 注意が $WARN 件 (上の !! の行)"
  exit 1
fi
