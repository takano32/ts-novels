/**
 * modefiied by sky.
 *
 */
var treemenustatus = new Array();
var treepageliststatus = new Array();

var status_str = _treemenu_getCookie(treemenu_cookie_name);
if( status_str != '' ) {
	var a = status_str.split(':');
	for( var i = 0; i< a.length-1; i++ ){
		ar = a[i].split('@');
		treemenustatus[ar[0]] = ar[1];
	}
}
var status_str = _treemenu_getCookie(treepagelist_cookie_name);
if( status_str != '' ) {
	var a = status_str.split(':');
	for( var i = 0; i< a.length-1; i++ ){
		ar = a[i].split('@');
		treepageliststatus[ar[0]] = ar[1];
	}
}

function treemenu(id)
{
	treemenu_fold(id);
	_treemenu_setStatus(treemenustatus ,id);

	var statusVal = '';
	for(key in treemenustatus){
		statusVal += key+'@'+treemenustatus[key]+':';
	}
	_treemenu_setCookie(treemenu_cookie_name, statusVal);
}
function treepagelist(id)
{
	treemenu_fold(id);
	_treemenu_setStatus(treepageliststatus ,id);

	var statusVal = '';
	for(key in treepageliststatus){
		statusVal += key+'@'+treepageliststatus[key]+':';
	}
	_treemenu_setCookie(treepagelist_cookie_name, statusVal);
}
function treemenu_fold(id)
{
	var linkelement = document.getElementById(id+"_a");
	var bodyelement = document.getElementById(id+"_body");
	var headelement = document.getElementById(id);
	
	if (bodyelement.style.display == "none") {
		bodyelement.style.display = "";
		if (headelement) {
			headelement.style.color = openedcolor;
			headelement.style.background = openedbgcolor;
		}
		if (linkelement)
			linkelement.innerHTML = openimage;
	} else {
		bodyelement.style.display = "none";
		if (headelement) {
			headelement.style.color = closedcolor;
			headelement.style.background = closedbgcolor;
		}	
		if (linkelement)
			linkelement.innerHTML = closeimage;
	}
}
function treemenu_show(id)
{
	var linkelement = document.getElementById(id+"_a");
	var bodyelement = document.getElementById(id+"_body");
	var headelement = document.getElementById(id);
	
	bodyelement.style.display = "";
	if (headelement) {
		headelement.style.color = openedcolor;
		headelement.style.background = openedbgcolor;
	}
	if (linkelement)
		linkelement.innerHTML = openimage;
}

function treemenu_hide(id)
{
	var linkelement = document.getElementById(id+"_a");
	var bodyelement = document.getElementById(id+"_body");
	var headelement = document.getElementById(id);
	
	bodyelement.style.display = "none";
	if (headelement) {
		headelement.style.color = closedcolor;
		headelement.style.background = closedbgcolor;
	}	
	if (linkelement)
		linkelement.innerHTML = closeimage;
}

function _treemenu_setStatus(status, id){
	if(status[id] != 1){
		status[id] = 1;
	}else{
		status[id] = 0;
	}
}

function _treemenu_setCookie(name, val){
	eDay = new Date();
	eDay.setTime(eDay.getTime() + (30 * 1000 * 60 * 60 * 24));
	eDay = eDay.toGMTString();
	document.cookie = name+'='+ val +';expires='+eDay+';path='+path+';';
}

function _treemenu_getCookie(name) {
    var i, index, array;
    array = document.cookie.split(';');
	// nameと同じ要素を検索する
    for(i = 0; i < array.length; i++) {
        index = array[i].indexOf('=');
        if(array[i].substring(0, index) == name || 
		   array[i].substring(0, index) == ' ' + name)
            return array[i].substring(index + 1);
    }
    return '';
}

function hide_menubar(cookie) 
{
	var menubar  = document.getElementById("menubar");
	var contents = document.getElementById("body");
	var contents_td = document.getElementById("body_td");
	var menubar_top = document.getElementById("menubar_topmenu_title");
	var anchor = document.getElementById("menubar_topmenu_anchor");

	if (menubar.style.display == "none") {
		menubar.style.display = "";
		//if (contents) contents.style.width = "74%";
		//if (contents_td) contents_td.style.width = "100%";
		if (anchor) {
			anchor.innerHTML = openimage;
			anchor.title = "close";
		}
		if (menubar_top) menubar_top.innerHTML = menubar_title;
		_treemenu_setCookie(treemenu_bar_cookie_name, 0);
	} else {
		menubar.style.display = "none";
		if (contents) contents.style.width = "100%";
		if (contents_td) contents_td.style.width = "100%";
		if (anchor) {
			anchor.innerHTML = closeimage;
			anchor.title = "open";
		}
		// commentout on css layout
		if (menubar_top) menubar_top.innerHTML = '';
		_treemenu_setCookie(treemenu_bar_cookie_name, 1);
	}
}

/**
 *  Change Background color
 */
function changeBgcolor(id,state) {
	if (state == 'in') {
		document.getElementById(id).style.background = hover_incolor;		
	} else {
		document.getElementById(id).style.background = hover_outcolor;		
	}
}
