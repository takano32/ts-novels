<html>
<head>
<STYLE TYPE="text/css">
<!--
/*枠の定義*/
TABLE#id1 {border-style: double; border-color: #999999; background-color: #D7FFEB}
/*一覧の表題の定義*/
TR#tr2 {background-color: #4AFFD7}
/*一覧の記事欄の定義*/
TR#tr3 {background-color: #E6FFE6}
TH#th1 {font-size:12pt}
TD#td1 {font-size:9pt}
/*FORM INPUT BOXの定義*/
INPUT {border-color:#999999;border-width:midium;background-color: #FFE6FF;
border-style: solid}
/*FORM SELECT BOXの定義*/
SELECT {border-width:midium;background-color: #FFE6FF;
border-style: solid}
/*FORM SUBMITボタンの定義*/
.d1 {background-color: #0000a0;border-style: double; border-color:#555555;
 color: #ffff00; font-weight: bolder; cursor:hand}
/*FORM SUBMITボタンの定義*/
.d2 {background-color: #0000a0;border-style: double; border-color:#555555;
 color: #ff00ff;font-weight: bolder; cursor:hand}
/*FORM RADIO CHECK BOXの定義*/
.d3 {border-style: double;background-color: #ffff80;border-color:#999999}
/*投稿FORMの一部の定義*/
.d4 {background-color: #999999; font-size:13pt}
/*作品タイトル表示の定義(タイトルに背景色を入れない場合、background-color:#E6FFE6を削除)*/
.d5 {font-size:30pt;font-weight:bold;background-color:#E6FFE6}
/*作品内容の表示定義*/
.d6 {font-size:13pt}
/*FORM TEXTAREAの定義*/
TEXTAREA {border-color:#999999;background-color: #FFE6FF;border-style:
 solid;border-width:midium;background-image:URL(./textbg.gif)}
/*リンク色の設定*/
A:link{ color:blue }
A:visited{ color:gray }
A:active{ color:green }
A{TEXT-DECORATION:NONE};
.on { background-color: #ffff00;color: white;font-style: Italic; }
.off {color: #555555;font-style: normal }
-->
</STYLE>
<title>少年少女文庫　−　第二掲示板・ストーリー道場(仮)</title>
</head>
<body background="" bgcolor=#ffffff text=navy>
<center>
<font style="font-size:30pt;font-weight:bold">
少年少女文庫　−　第二掲示板・ストーリー道場(仮)
</font>
</center>
<br>
<center>
<table cellspacing=3 width=100% bgcolor=#D7FFEB>
<tr>
<td colspan=7>
<table align=center id=id1>
<tr id=tr3>
<th>
最新作品集
</th>
</tr>
</table>
</td>
</tr>
<tr>
<th colspan=7>
<font style="font-size:13pt">
投稿LIST [ 現在 15 作品公開中 ]
</font>
</th>
</tr>
<form method=post action=./index.cgi>
<input type=hidden name="log" value="">
<input type=hidden name=action value="html4">
<tr><th colspan=7>
<center>
<table width=90%><tr><th>
作者を限定して抽出<br>
<select name=sakusha>
<option value=0>作者を選択
<option value="kyouske">kyouske(15)
</select>
<input type=submit value=リスト表示 class="d1">
</th>
</form>
<form method=post action=./index.cgi>
<input type=hidden name="log" value="">
<input type=hidden name=action value="html5">
<th>
語句を指定して抽出<br><input type=text name=serch size=16>
<input type=submit value=検索 class="d1">
</th>
</form>
<form method=post action=./index.cgi><th colspan=4>
<input type=hidden name=log value=>
条件を指定して整頓<br><select name=sort>
<option value=0>
<option value=saku1>
<option value=saku2>
<option value=visit>
<option value=leng>
<option value=rescnt>
<option value=points>
</select> <input type=submit class=d1 value=並び替え>
</th></form></tr></table>
<tr ID="tr2">
<th width=40%>Title</th>
<th>Name</th>
<th>投稿日</th>
<th>閲覧数</th>
<th>Byte</th>
<th>Res</th>
<th>Point</th>
</tr>
</tr></table>
</center><p>
<table border=5 cellspacing=5 align=center><tr>
<form method="post" action="./index.cgi">
<input type=hidden name=action value="home">
<td>
<input type=submit value=" HOME " class="d1">
</td>
</form>
<form method="post" action="./index.cgi">
<input type=hidden name=action value="form">
<td>
<input type=submit value=" 新規投稿 " class="d1">
</td>
</form>
</tr></table><br>
<hr>
</center>
<form method="post" action="./index.cgi">
<input type=hidden name="action" value="sentaku">
<input type=hidden name="log" value="">
PASSWORD <input type=password name="pwd" size=10>
<input type=submit value="管理者用" class="d1"></form>
<div align=right><address>
<font size=3>
<a href="http://www2s.biglobe.ne.jp/~yasuu/antho_index.html" target="_top">
Anthologys v2.5e  Script by YASUU!!
</a>
</font></address>
<font size=2>
<b><a href="http://zeroad.biz/anthology/">
Ver.Bright arrange by ZERO
</a></b>
</font>
</div>
</body></html>


