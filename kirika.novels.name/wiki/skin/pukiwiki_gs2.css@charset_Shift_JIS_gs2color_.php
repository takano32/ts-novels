@charset "Shift_JIS";
@import "./pagetree.css";
@import "./treemenu.css";

pre, dl, ol, p, blockquote {
	line-height:175%;
}

blockquote { margin-left:32px; }

body {
	color:#060606;
	background-color:#D6E1EC;
	margin:0%;
	padding:1%;
	font-size:x-small;
	letter-spacing:1px;
	font-family:Verdana, Sans-Serif;
}

td, th {
	color:#060606;
	background-color:#D6E1EC;
	font-size:x-small;
	letter-spacing:1px;
	font-family:Verdana, Sans-Serif;
	
}

body {
	scrollbar-face-color:#D6E1EC;
	scrollbar-track-color:#D6E1EC;
	scrollbar-3dlight-color:#0064C8;
	scrollbar-base-color:#0064C8;
	scrollbar-darkshadow-color:#0064C8;
	scrollbar-highlight-color:#0064C8;
	scrollbar-shadow-color:#0064C8;
	scrollbar-arrow-color:#0064C8;
}

div#container {
	width:100%;
	position:relative;
}

div#leftbox2 {
	position:absolute;
	width:18%;
	margin:0px;
	padding:0px;
}

div#centerbox_noright2 {
	position:absolute;
	width:80%;
	left:19%;
	top:0;
	margin:0px;
	padding:0px;
}



div#rightbox {
	width:18%;
	float:right;
	margin:0px;
	padding:0px;
}
	div.adsky {
		text-align:center;
	}

div#centerbox {
	width:63%;
	float:left;
	margin:0px;
	padding:0px;

}

div#centerbox_noside {
	float:left;
	width:100%;
	margin:0px;
	padding:0px;
}

div#centerbox_noright {
	float:left;
	width:81%;
	margin:0px;
	padding:0px;
}

div#topbox {
	width:100%;
	padding:5px;
}

div#header {
	padding:5px;
	margin:0px 0px 10px 0px;
	background-color: #ADC3D9;
	border: 2px solid #769BC0;
}

	h1.title {
		font-size: 200%;
		font-family: 'Trebuchet MS';
		font-weight: bold;
		letter-spacing: 3px;
		color:#00000F;
		background-color: #7EA1C2;
		border-style: solid;
		border-color: #2B3643;
		border-width: 2px 4px 4px 2px;
		padding: 3px;
		margin: 5px;
	}

	form#head_search
	{
		font-size: 80%;
		padding:2px 8px;
		margin:0px;
		float:right;
	}

	div#navigator {
		font-size: 80%;
		padding:2px;
		margin:0px;
	}

	div.pageinfo
	{
		font-size: 80%;
		padding:2px;
		margin:0px;
		text-align:right;
	}

div#contents {
	padding:12px;
	background-color:#F5FAFF;
	border:3px solid #0064C8;
}

	.footbox
	{
		clear:both;
		padding:3px;
		margin:6px 1px 1px 1px;
		border:dotted 1px #769BC0;
		background-color: #ADC3D9;
		font-size:xx-small;
		line-height:180%;
	}

	div#note {
	}

	div#attach {
			}
	
	div#related {
			}

div#toolbar {
	padding:0px;
	margin-bottom:10px;
	text-align:right;
}

div#footer {
	clear: both;
	font-size:xx-small;
	padding:0px;
	margin:16px 0px 0px 0px;
}

div#qrcode {
	float:left;
	margin:0px 10px 0px 10px;
}


div#leftbox {
	width:18%;
	float:left;
	margin:0px;
	padding:0px;
}

	div.menubar {
		margin: 0px 8px;
		padding: 3px;
		word-break:break-all;
		overflow:hidden;
		letter-spacing: 0.5px;
	}

	div.menubar ul li {
		line-height:160%;
		font-size: x-small;
	}
	
	div.menubar h1 ,
	div.menubar h2 ,
	div.menubar h3 ,
	div.menubar h4 ,
	div.menubar h5 {
		font-size: small;
		border: 2px solid #769BC0;
		background-color: #ADC3D9;
		background-image: none;
		margin-top:10px;
	}
	
	div.menubar .anchor_super,
	div.menubar .jumpmenu {
		display:none;
	}
	
	div.menubar td {
		padding:0px;
	}


a:link {
	color:#215dc6;
	text-decoration:none;
}

a:active {
	color:#c6215d;
	text-decoration:none;
}

a:visited {
	color:#06358A;
	text-decoration:none;
}

a:hover {
	color:#268671;
	text-decoration:underline;
}

h1, h2 {
	font-size:150%;
	color:#00000F;
	background-color:#ADC3D9;
	padding:3px;
	border-style:solid;
	border-color:#384658;
	border-width:3px 3px 6px 20px;
	margin:0px 0px 5px 0px;
}
h3 {
	font-size:140%;
	color:#00000F;
	background-color:#ADC3D9;
	padding:3px;
	border-style: solid;
	border-color:#46586E;
	border-width: 1px 1px 5px 12px;
	margin:0px 0px 5px 0px;
}
h4 {
	font-size:130%;
	color:#00000F;
	background-color:#ADC3D9;
	padding:3px;
	border-style: solid;
	border-color:#50667F;
	border-width: 0px 6px 1px 7px;
	margin:0px 0px 5px 0px;
}
h5 {
	font-size:120%;
	color:#00000F;
	background-color:#ADC3D9;
	padding:3px;
	border-style: solid;
	border-color:#60768F;
	border-width: 0px 0px 1px 6px;
	margin:0px 0px 5px 0px;
}

h6 {
	font-size:110%;
	color:#00000F;
	background-color:#ADC3D9;
	padding:3px;
	border-style: solid;
	border-color:#70869F;
	border-width: 0px 5px 1px 0px;
	margin:0px 0px 5px 0px;
}


dt {
	font-weight:bold;
	margin-top:1em;
	margin-left:1em;
}

pre {
	border:#888899 1px solid;
	padding:.5em;
	margin-left:1em;
	margin-right:2em;
	font-size: x-small;
	white-space:pre;
	word-break:break-all;
	letter-spacing:0px;

	color:#060606;
	background-color:#EEEEEE;
}

img {
	border:none;
	vertical-align:middle;
}

ul {
	margin:0px 0px 0px 6px;
	padding:0px 0px 0px 10px;
	line-height:160%;
}

li {
	margin: 3px 0px;
}

em { font-style:italic; }

strong { font-weight:bold; }

input, textarea {
	color:#060606;
	background-color:#D6E1EC;
	border-style: solid;
	border-color: #769BC0;
	border-width: 1px;
	font-size:12px;
}

input.radio {
	background-color:transparent;
	border-width: 0;
}

thead td.style_td,
tfoot td.style_td {
	color:inherit;
	background-color:#D6E1EC;
}
thead th.style_th,
tfoot th.style_th {
	color:inherit;
	background-color:#ADC3D9;
}
.style_table {
	padding:0px;
	border:0px;
	margin:auto;
	text-align:left;
	color:inherit;
	background-color:#769BC0;
}
.style_th, th {
	padding:5px;
	margin:1px;
	text-align:center;
	color:inherit;
	background-color:#D6E1EC;
	vertical-align:bottom;
}
.style_td, td {
	padding:5px;
	margin:1px;
	color:inherit;
	background-color:#F5FAFF;
	vertical-align:middle;
}

ul.list1 { list-style-type:disc; }
ul.list2 { list-style-type:circle; }
ul.list3 { list-style-type:square; }
ol.list1 { list-style-type:decimal; }
ol.list2 { list-style-type:lower-roman; }
ol.list3 { list-style-type:lower-alpha; }

div.ie5 { text-align:center; }

span.noexists {
	color:#000000;
	background-color:#FFFACC;
}

.small { font-size:80%; }

.super_index {
	color:#DD3333;
	background-color:inherit;
	font-weight:bold;
	font-size:60%;
	vertical-align:super;
}

a.note_super {
	color:#DD3333;
	background-color:inherit;
	font-weight:bold;
	font-size:xx-small;
}

div.jumpmenu {
	font-size:60%;
	text-align:right;
}

hr.full_hr {
	border-style:ridge;
	border-color:#888899;
	border-width:1px 0px;
}

span.size1 {
	font-size:xx-small;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size2 {
	font-size:x-small;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size3 {
	font-size:small;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size4 {
	font-size:medium;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size5 {
	font-size:large;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size6 {
	font-size:x-large;
	line-height:130%;
	text-indent:0px;
	display:inline;
}
span.size7 {
	font-size:xx-large;
	line-height:130%;
	text-indent:0px;
	display:inline;
}

/* html.php/catbody() */
strong.word0 {
	background-color:#FFFF66;
	color:black;
}
strong.word1 {
	background-color:#A0FFFF;
	color:black;
}
strong.word2 {
	background-color:#99FF99;
	color:black;
}
strong.word3 {
	background-color:#FF9999;
	color:black;
}
strong.word4 {
	background-color:#FF66FF;
	color:black;
}
strong.word5 {
	background-color:#880000;
	color:white;
}
strong.word6 {
	background-color:#00AA00;
	color:white;
}
strong.word7 {
	background-color:#886800;
	color:white;
}
strong.word8 {
	background-color:#004699;
	color:white;
}
strong.word9 {
	background-color:#990099;
	color:white;
}

/* html.php/edit_form() */
.edit_form { clear:both; }

/* pukiwiki.skin.php */
div#preview {
	color:inherit;
	background-color:#F5FAFF;
}

/* aname.inc.php */
.anchor {}
.anchor_super {
	font-size:xx-small;
	vertical-align:super;
}

/* br.inc.php */
br.spacer {}

/* calendar*.inc.php */
.style_calendar {
	padding:0px;
	border:0px;
	margin:3px;
	color:inherit;
	background-color:#769BC0;
	text-align:center;
}

.style_calendar td {
	padding:4px;
	margin:1px;
	text-align:center;
	color:inherit;
}

.style_td_today {
	background-color:#CCFFDD;
}
.style_td_sat {
	background-color:#DDE5FF;
}
.style_td_sun {
	background-color:#FFEEEE;
}
.style_td_caltop,
.style_td_week {
	background-color:#D6E1EC;
	font-weight:bold;
}

/* calendar_viewer.inc.php */
div.calendar_viewer {
	color:inherit;
	background-color:inherit;
	margin-top:20px;
	margin-bottom:10px;
	padding-bottom:10px;
}
span.calendar_viewer_left {
	color:inherit;
	background-color:inherit;
	float:left;
}
span.calendar_viewer_right {
	color:inherit;
	background-color:inherit;
	float:right;
}

/* clear.inc.php */
.clear {
	margin:0px;
	clear:both;
}

/* counter.inc.php */
div.counter { font-size:70%; }

/* diff.inc.php */
span.diff_added {
	color:blue;
	background-color:inherit;
}

span.diff_removed {
	color:red;
	background-color:inherit;
}

/* hr.inc.php */
hr.short_line {
	text-align:center;
	width:80%;
	border-style:solid;
	border-color:#AAAAAA;
	border-width:1px 0px;
}

/* include.inc.php */
h5.side_label { text-align:center; }

/* navi.inc.php */
ul.navi {
	font-size:xx-small;
	margin:0px;
	padding:0px;
	text-align:center;
}
li.navi_none {
	font-size:xx-small;
	display:inline;
	float:none;
}
li.navi_left {
	font-size:xx-small;
	display:inline;
	float:left;
	text-align:left;
}
li.navi_right {
	font-size:xx-small;
	display:inline;
	float:right;
	text-align:right;
}

/* new.inc.php */
span.comment_date { font-size:xx-small; }
span.new1 {
	color:red;
	background-color:transparent;
	font-size:xx-small;
}
span.new5 {
	color:green;
	background-color:transparent;
	font-size:xx-small;
}

/* popular.inc.php */
span.counter { font-size:70%; }
ul.popular_list {
}

/* recent.inc.php,showrss.inc.php */
ul.recent_list {
}

/* ref.inc.php */
div.img_margin {
	margin-left:32px;
	margin-right:32px;
}

/* vote.inc.php */
td.vote_label {
	color:#000000;
	background-color:#FFCCCC;
}
td.vote_td1 {
	color:#000000;
	background-color:#DDE5FF;
}
td.vote_td2 {
	color:#000000;
	background-color:#EEF5FF;
}

