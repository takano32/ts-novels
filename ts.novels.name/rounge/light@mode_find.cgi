<?xml version="1.0" encoding="Shift_JIS"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
<meta http-equiv="content-type" content="text/html; charset=shift_jis" />
<meta http-equiv="content-style-type" content="text/css" />
<link href="style.css" rel="stylesheet" type="text/css" />
<title>ワード検索</title>
</head>
<body>

[<a href="./light.cgi">戻る</a>]
<div class="obi">ワード検索</div>

<ul>
<li>検索したい<b>キーワード</b>を入力し「検索」ボタンを押してください。</li>
<li>キーワードはスペースで区切って複数指定することができます。
<form action="./light.cgi" method="get">
<input type="hidden" name="mode" value="find" />
キーワード <input type="text" name="word" size="36" value="" />
条件
<select name="cond">
<option value="1">AND</option>
<option value="0">OR</option>

</select>
<input type="submit" value=" 検索 " />
</form></li>
</ul>

<div class="ta-c">



</div>
<p style="margin-top:2em;text-align:center;font-family:Verdana,Helvetica,Arial;font-size:10px;">
- <a href="http://www.kent-web.com/" target="_top">LightBoard</a> -
</p>
</body>
</html>

