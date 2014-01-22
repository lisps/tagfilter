<?php
/**
 * DokuWiki Plugin tagfilter (Ajax Component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
require_once(DOKU_INC.'inc/init.php');

global $lang;

//Variables
$requested_id = intval($_POST["id"]);
$form = json_decode($_POST["form"]);
$ns = json_decode($_POST["ns"]);
$flags = json_decode($_POST["flags"]);
$pagesearch = json_decode($_POST['pagesearch']);


//load tagfilter plugin
$Htagfilter = plugin_load('helper', 'tagfilter');

//load pagelist plugin
if (!$pagelist = plugin_load('helper', 'pagelist')) {
	send_response(sprintf($Htagfilter->getLang('missing_plugin'),'pagelist'));
}

//load tag plugin
if (!$Htag = plugin_load('helper', 'tag')) {
	send_response(sprintf($Htagfilter->getLang('missing_plugin'),'tag'));
}

$form = array_filter($form);//leere Einträge ausfiltern

$tag_list_r = array();

foreach($form as $item){ //die Einträge aus einer Dropdown list zusammenfügen
	$tag_list_r[]=  implode(' ',array_filter($item));
}

$tag_list_r = array_filter($tag_list_r);

//lookup the pages from the taglist
//partially copied from tag->helper with less checks and no meta lookups
$page_names = array();
foreach($tag_list_r as $key=>$tag_list){
	$tags_parsed = $Htag->_parseTagList($tag_list, true);
	$pages_lookup = $Htag->_tagIndexLookup($tags_parsed);
	foreach($pages_lookup as  $page_lookup){
		// filter by namespace, root namespace is identified with a dot // root namespace is specified, discard all pages who lay outside the root namespace
		if(($ns == '.' && (getNS($page_lookup) == false)) || ($ns && (strpos(':'.getNS($page_lookup).':', ':'.$ns.':') === 0)) || $ns === '') {
			$perm = auth_quickaclcheck($page_lookup);
            if (!($perm < AUTH_READ))
				$page_names[$key][] = $page_lookup;
		}
	}
}


if(!$pages_intersect = array_shift($page_names)){
	$pages_intersect = array();
}
foreach($page_names as $names){
	$pages_intersect = array_intersect($pages_intersect,$names);
}

if(!empty($pagesearch)){
	$pages_intersect = array_intersect($pages_intersect,$pagesearch);
}
if(count($pages_intersect)==0){ //wenn pages_intersect keine Werte enthält gibt es keine gefundenen Seiten
	send_response('<i>'.$lang['nothingfound'].'</i>');
}

//Template nicht anzeigen
$f = create_function('$val', 'return strpos($val,"_template")===false?true:false;');
$pages_intersect = array_filter($pages_intersect,$f);

$pages = array();
foreach($pages_intersect as $page){
	$title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
	$pages[$title?$title:$page] = array(
		'title' => $title?$title:$page,
		'id' => $page
		);
}
if(in_array('rsort',$flags)) {
	krsort($pages);
} else {
	ksort($pages);
}
/*
$pages = array();
foreach($pages_intersect as $page)
$pages[]= array('id'=>$page);

*/
// let Pagelist Plugin do the work for us


$pagetopics = array();
$pagelist->setFlags($flags);
$pagelist->startList();

foreach ($pages as $page) {
	$pagelist->addPage($page);
}
$text = $pagelist->finishList();

send_response($text);

function send_response($text) {
    global $requested_id;
    echo json_encode(array('id'=>$requested_id,'text'=>$text));
    exit;
}
