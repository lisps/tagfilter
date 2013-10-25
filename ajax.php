<?php
/**
 * DokuWiki Plugin tagfilter (Ajax Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
require_once(DOKU_INC.'inc/init.php');

#Variables  
$idcount = intval($_POST["id"]);
$form = json_decode($_POST["form"]);
$ns = json_decode($_POST["ns"]);
$flags = json_decode($_POST["flags"]);
$pagesearch = json_decode($_POST['pagesearch']);


$form = array_filter($form);//leere Einträge ausfiltern

$tags = array();

foreach($form as $item){ //die Einträge aus einer Dropdown list zusammenfügen
	$tags[]=  implode(' ',array_filter($item));
}

$tags = array_filter($tags);
//Tag plugin laden
$Htag =& plugin_load('helper', 'tag');
if(!$Htag){
	echo json_encode(array('id'=>$idcount,'text'=>'<i>Das Tag Plugin wird benötigt</i>'));
	return true; // nothing to display
}

//Pages für die einzelnen Dropdown Listen finden
$pages = array();
foreach($tags as $tag){ 
	$pages[] = $Htag->getTopic($ns,'',$tag);
}

//jetzt muss die Schnittmenge der Pages gefunden werden.
//Dazu wird der ID Eintrag(sprich Seitenname) herausgezogen und mit array_intersect die Schnittmenge gebildet
$page_names = array();
foreach($pages as $key=>$page){
	$page_names[$key]=array();
	foreach($page as $index=>$item){
		$page_names[$key][] = $item['id'];
	}
}
$pages_intersect = array();
$pages_intersect = array_shift($page_names);
foreach($page_names as $names){
	$pages_intersect = array_intersect($pages_intersect,$names);
}
if(!empty($pagesearch)){
	$pages_intersect = array_intersect($pages_intersect,$pagesearch);
}
if(count($pages_intersect)==0){ //wenn pages_intersect keine Werte enthält gibt es keine gefundenen Seiten
	echo json_encode(array('id'=>$idcount,'text'=>'<i>Nichts</i>','topics'=>array()));
	return true; // nothing to display
}

//nun haben wir die Schnittmenge der Pages und müssen nur noch aus den bereits gefundenen Seiten diese rausfiltern
$pages_pagelist = array();
foreach($pages as $page){
	foreach($page as $index=>$item){
		if( in_array($item['id'],$pages_intersect) ){
			$pages_intersect = array_diff($pages_intersect,array($item['id']));
			$pages_pagelist[]=$item;
		}
	}
}

$pages = $pages_pagelist;


// let Pagelist Plugin do the work for us
if (plugin_isdisabled('pagelist')
		|| (!$pagelist = plugin_load('helper', 'pagelist'))) {
	msg($this->getLang('missing_pagelistplugin'), -1);
	return false;
}
$pagetopics = array();
$pagelist->setFlags($flags);
$pagelist->startList();

foreach ($pages as $page) {
	$pagelist->addPage($page);
	$pagetopics[$page['id']]=$page['title'];
}
$text = $pagelist->finishList();      

echo json_encode(array('id'=>$idcount,'text'=>$text,'topics'=>$pagetopics));

