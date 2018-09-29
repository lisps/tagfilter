<?php
/**
 * DokuWiki Plugin tagfilter (Action Component)
 *
 * inserts a button into the toolbar
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'action.php');

class action_plugin_tagfilter extends DokuWiki_Action_Plugin {
	/**
	 * Register the eventhandlers
	 */
	function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
		$controller->register_hook('DOKUWIKI_STARTED', 'AFTER',  $this, '_addparams');
		$controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE',  $this, '_ajax_call');
	}

	function _addparams(&$event, $param) {
		global $JSINFO;
		global $INPUT;
	    // filter for ft* in GET
	    $f = create_function('$value', 'return strpos($value,"tf") === 0;');
	    $get_tagfilter = array_filter(array_keys($_GET),$f);

		//filter for ft<key>_<label> and add it to JSINFO to select it via JavaScript
		foreach($get_tagfilter as $param){
			$ret = preg_match('/^tf(\d+)\_([\w\:äöü\-\/#]+)/',$param,$matches);

			if($ret && is_numeric($matches[1]) && $matches[2])
				$JSINFO['tagfilter'][] = array(
					'key' => $matches[1],
					'label'=>$matches[2],
					'values' =>$INPUT->str($param)?array($INPUT->str($param)):$INPUT->arr($param)
				);
		}

	}

	/**
	 * Inserts the toolbar button
	 */
	function insert_button(&$event, $param) {
		$event->data[] = array(
			'type'   => 'format',
			'title' => 'Tagfilter plugin',
			'icon'   => '../../plugins/tagfilter/tagfilter.png',
			'sample' => '<namespace>? <Label>=<TagAusdruck>=<Tags Selected>|... &<pagelistoptions (&multi&nouser&chosen)>',
			'open' => '{{tagfilter>',
			'close'=>'}}',
			'insert'=>'',
		);
	}
	
	/**
	 * ajax Request Handler
	 */
	function _ajax_call(&$event, $param) {
		if ($event->data !== 'plugin_tagfilter') {
			return;
		}
		//no other ajax call handlers needed
		$event->stopPropagation();
		$event->preventDefault();
		
		global $INPUT;
		global $lang;

		//Variables
		$tagfilter_id = $INPUT->int('id');
		$form = $this->dejson_param('form');
		$ns = $this->dejson_param('ns');
		$flags = (array)$this->dejson_param('flags');
		//print_r($flags);
		$tfFlags = (array)$flags[1];
		$flags = $flags[0];
		//print_r($tfFlags);
		$pagesearch = $this->dejson_param('pagesearch');
		
		//load tagfilter plugin
		$Htagfilter = $this->loadHelper('tagfilter', false);
		
		//load tag plugin
		if (is_null($Htag = $this->loadHelper('tag', false))) {
			$this->send_response(sprintf($Htagfilter->getLang('missing_plugin'),'tag'));
			return;
		}
		//load tag plugin
		if (is_null($Hpagelist = $this->loadHelper('pagelist', false))) {
			$this->send_response(sprintf($Htagfilter->getLang('missing_plugin'),'pagelist'));
			return;
		}
		
		$form = array_filter($form);//remove empty entries
		
		$tag_list_r = array();
		
		foreach($form as $item){ //rearrange entries for tag plugin lookup
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
		

		
		//get the first item
		if(!is_array($pages_intersect = array_shift($page_names))) {
			$pages_intersect = array();
		}
		
		//find the intersections
		foreach($page_names as $names){
			$pages_intersect = array_intersect($pages_intersect,$names);
		}
		
		//intersections with the pagesearch
		if(is_array($pagesearch) && !empty($pagesearch)){
			$pages_intersect = array_intersect($pages_intersect,$pagesearch);
		}
		
		//no matching pages
		if(count($pages_intersect)===0){ 
			$this->send_response('<i>'.$lang['nothingfound'].'</i>');
			return;
		}

		$pages_intersect = array_filter($pages_intersect,array($this,_filter_hide_template));
		
		$pages = array();
		foreach($pages_intersect as $page){
			$title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
			$pages[$title?$title:$page] = array(
					'title' => $title?$title:$page,
					'id' => $page
			);
		}
		if(in_array('rsort',$flags)) {
			krsort($pages,SORT_STRING|SORT_FLAG_CASE);
		} else {
			ksort($pages,SORT_STRING|SORT_FLAG_CASE);
		}
		
		$pagetopics = array();
		//print_r($tfFlags['tagcolumn']);
		if(!isset($tfFlags['tagcolumn']))$tfFlags['tagcolumn'] = array();
		foreach($tfFlags['tagcolumn'] as $tagcolumn) {
			$Hpagelist->addColumn('tagfilter',hsc($tagcolumn));
		}
		$Hpagelist->setFlags($flags);
		$Hpagelist->startList();
		
		foreach ($pages as $page) {
			$Hpagelist->addPage($page);
		}
		$text = $Hpagelist->finishList();
		
		$this->send_response($text);
	}
	
	private function dejson_param($name) {
		global $INPUT;
		
		$param = null;
		if($INPUT->has($name)) {
			$param = $INPUT->param($name);
			$param = json_decode($param);
		}
		return $param;
	}
	
	private function send_response($text) {
		global $INPUT;
		$tagfilter_id = $INPUT->int('id');
		echo json_encode(array('id'=>$tagfilter_id,'text'=>$text));
	}
	
	/**
	 * filter function 
	 * show only pages different to _template and the have to exist
	 */
	private function _filter_hide_template($val) {
		return (strpos($val,"_template")===false) && (@file_exists(wikiFN($val))) ? true : false;
	}


}





