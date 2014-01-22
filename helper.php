<?php
/**
 * DokuWiki Plugin tagfilter (Helper Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

require_once(DOKU_INC.'inc/indexer.php');

class helper_plugin_tagfilter extends DokuWiki_Plugin {
	
	protected $Htag;
	/**
	* Constructor gets default preferences and language strings
	*/
	function helper_plugin_tagfilter() {
		if (plugin_isdisabled('tag') || (!$this->Htag = plugin_load('helper', 'tag'))) {
			msg('tag plugin is missing', -1);
			return false;
		}
	}
	
	function getMethods() {
		$result = array();
		$result[] = array(
	                'name'   => 'getTagsByRegExp',
	                'desc'   => 'returns tags for given Regular Expression',
					'params' => array(
			                    'tags (required)' => 'string',
			                    'namespace (optional)' => 'string',),
	                'return' => array('tags' => 'array'),
		);
		$result[] = array(
	                'name'   => 'getTagsByNamespace',
	                'desc'   => 'returns tags for given namespace',
					'params' => array(
			                    'namespace' => 'string',),
	                'return' => array('tags' => 'array'),
		);
		$result[] = array(
	                'name'   => 'getTagsBySiteID',
	                'desc'   => 'returns tags for given siteID',
					'params' => array(
			                    'siteID' => 'string',),
	                'return' => array('tags' => 'array'),
		);

		return $result;
	}
	function isNewTagVersion(){
		$Htag  =$this->Htag;
		if(!$Htag)return false;
		$info =$Htag->getInfo();
		return (strtotime($info['date'])>strtotime('2012-08-01'))?true:false;
	}
	
	//sucht im Tagindex nach Tags mittels preg Ausdrucken
	function getTagsByRegExp($tags, $ns = ''){
		
		$Htag  =$this->Htag;
		if(!$Htag)return false;
		$info=$Htag->getInfo();
		if($this->isNewTagVersion())
			return $this->getTagsByRegExp_New($tags, $ns,false);
		$fullTags = array(); //gefundene Tags
		 
		$tags = $Htag->_parseTagList($tags, true);
		$alltags = $this->getTagsByNamespace($ns,false);
		foreach($tags  as  $tag){
			foreach($alltags as $alltag){
				if(@preg_match('/^'.$tag.'$/',$alltag)){
					$label =strrchr($alltag,':');
					$label = $label !=''?$label:$alltag;
					$fullTags[$alltag] = ucwords(str_replace('_',' ',trim($label,':')));
				}
			}
		}
		$fullTags = array_unique($fullTags);
		asort($fullTags);
		
		return  $fullTags;
	}
	
	protected $_tagsByNamespace = array();
	function getTagsByNamespace($ns = '',$acl_safe = true){
		if(!isset($this->_tagsByNamespace[$ns])) {
			$Htag  =$this->Htag;
			if(!$Htag)return false;
		
			if($this->isNewTagVersion())
				return array_keys($this->getTagsByRegExp_New('.*',$ns,$acl_safe));
			
			$index = array_filter($Htag->topic_idx);

			$tags=array();
			if($ns === ''){
				if($acl_safe) {
					foreach($index as $tag=>$page_r){
						foreach($page_r as $page) {
							$perm = auth_quickaclcheck($page);
							if (!$perm < AUTH_READ){
								$tags[]=$tag;
								break;
							}
						}
					}
				} else {
					$tags = array_keys($index);
				}
			} else {
				foreach ($index as $tag=>$page_r){
					if($this->_checkPageArrayInNamespace($page_r,$ns)){
						$tags[]=$tag;
					}
				}

			}
			$this->_tagsByNamespace[$ns]=array_unique($tags);
		}
		return $this->_tagsByNamespace[$ns];
	}
	
	function _checkPageArrayInNamespace($page_r,$ns,$acl_safe = true){
		$Htag  =$this->Htag;
		if(!$Htag)return false;
		foreach($page_r as $page){
			if ($ns && (strpos(':'.getNS($page).':', ':'.$ns.':') === 0)){
				if (!$acl_safe) return true;
				$perm = auth_quickaclcheck($page);
				if (!$perm < AUTH_READ)
					return true;

			}
			//if($Htag->_isVisible($page,$ns))
			//	return true;
			
		}
		return false;
	}
	
	function _checkPageArrayInNamespace_New($page_r,$ns,$acl_safe = true){
		$Htag  =$this->Htag;
		if(!$Htag)return false;
		foreach($page_r as $page){
			if($Htag->_isVisible($page,$ns)) {
				if (!$acl_safe) return true;
				$perm = auth_quickaclcheck($page);
				if (!$perm < AUTH_READ)
					return true;
			
			}

		}
		return false;
	}
	
	/*
	 * liefert alle Tags für eine bestimmte seite Zurück
	 */
	function getTagsBySiteID($siteID){
		if($this->isNewTagVersion())
			return $this->getTagsBySiteId_New($siteID);
		
		$Htag  =$this->Htag;
		if(!$Htag)return false;
		
		$index = array_filter($Htag->topic_idx);
		/*echo '<pre>';
		print_r($index);
		echo '</pre>';*/
		$tags=array();
		foreach ($index as $tag=>$page_r){
			if(in_array($siteID,$page_r)){
				$tags[]=$tag;
			}
		}
		$tags=array_unique($tags);
		return $tags;
	}
	
	function getTagsBySiteId_New($siteID){
		$meta = p_get_metadata($siteID,'subject');
		if($meta === NULL) $meta=array();
		return $meta;
	}
	
	function _tagCompare ($tag1,$tag2){
		return $tag1==$tag2;
	}
	function getTagsByRegExp_New($tag_expr,$ns = '',$acl_safe=true){
		$tags = $this->getIndex('subject','_w');
		$tag_label_r = array();
		foreach($tags  as  $tag){
			if(@preg_match('/^'.$tag_expr.'$/i',$tag) && $this->_checkTagInNamespace($tag,$ns,$acl_safe)){
				//$label =stristr($tag,':');
				$label = strrchr($tag,':');
				$label = $label !=''?$label:$tag;
				$tag_label_r[$tag] = ucwords(trim(str_replace('_',' ',trim($label,':'))));
			}
		}
		asort($tag_label_r);
		return $tag_label_r;
	}
	function _checkTagInNamespace($tag,$ns,$acl_safe=true){
		if($ns == '') return true;
		$indexer = idx_get_indexer();
		$pages = $indexer->lookupKey('subject', $tag, array($this, '_tagCompare'));
		return $this->_checkPageArrayInNamespace_New($pages,$ns,$acl_safe);
	}
	
	/*
	 * from inc/indexer.php 
	 */
	public function getIndex($idx, $suffix) {
        global $conf;
        $fn = $conf['indexdir'].'/'.$idx.$suffix.'.idx';
        if (!@file_exists($fn)) return array();
        return file($fn, FILE_IGNORE_NEW_LINES);
    }
	
	protected $ps_ns = '';
	protected $ps_pages_id = array();
	protected $ps_pages = array();
	
	function getPagesByTag($tag,$ns=''){
		$tags = explode(' ',$tag);
		$this->startPageSearch($ns);
		foreach($tags as $t){
			if($t{0} == '+') $this->addAndTag(substr($t,1));
			elseif($t{0} == '-') $this->addSubTag(substr($t,1));
			else $this->addOrTag($t);
		}
		return $this->getPages();
	}
	
	function startPageSearch($ns = '') {
		$this->ps_ns = $ns;
		$this->ps_pages_id = array();
		$this->ps_pages = array();
	}
	
	function addAndTag($tag){
		$tags = $this->getTagsByRegExp($tag,$this->ps_ns);
		$pages = array();
		foreach($tags as $t=>$v){ 
			$Hpages =$this->Htag->getTopic($this->ps_ns,null,$t);
			foreach($Hpages as $p){
				$pages[] = $p['id'];
				if(!isset($this->ps_pages[$p['id']])) $this->ps_pages[$p['id']] = $p;
			}
			
		}
		$pages = array_unique($pages);
		$this->ps_pages_id = array_intersect($this->ps_pages_id,$pages);
	}
	
	function addSubTag($tag){
		$tags = $this->getTagsByRegExp($tag,$this->ps_ns);
		$pages = array();
		foreach($tags as $t=>$v){ 
			$Hpages =$this->Htag->getTopic($this->ps_ns,'',$t);
			foreach($Hpages as $p){
				$pages[] = $p['id'];
			}
		}
		$pages = array_unique($pages);
		$this->ps_pages_id = array_diff($this->ps_pages_id,$pages);
	}
	function addOrTag($tag){
		$tags = $this->getTagsByRegExp($tag,$this->ps_ns);
		$pages = array();
		foreach($tags as $t=>$v){ 
			$Hpages =$this->Htag->getTopic($this->ps_ns,'',$t);
			foreach($Hpages as $p){
				$pages[] = $p['id'];
				if(!isset($this->ps_pages[$p['id']])) $this->ps_pages[$p['id']] = $p;
			}
		}
		$pages = array_unique($pages);
		$this->ps_pages_id = array_merge($this->ps_pages_id,$pages);
		$this->ps_pages_id = array_unique($this->ps_pages_id);
		
		//print_r($this->ps_pages_id);
	}
	function getPages(){
		$ret = array();
		foreach ($this->ps_pages_id as $id){
			$ret[] = $this->ps_pages[$id];
		}
		return $ret;
	}
	
	function getImageLinkByTag($tag){
		$id = $this->getConf('nsTagImage').':'.str_replace(array(' ',':'),'_',$tag);
		$src = $id .'.jpg';
		if(!@file_exists(mediaFN($src))) {
			$src = $id .'.png';
			if(!@file_exists(mediaFN($src))) {
				$src = $id .'.jpeg';
				if(!@file_exists(mediaFN($src))) {
					$src = false;
				}
			}
		}
		if($src != false) {
			return ml($src);
		}
		return false;
	}
	
	/*
	 * return all pages defined by tag_list_r in a specific namespace
	 * 
	 * @param ns string the namespace to look in
	 * @param tag_list_r array an array containing strings with tags seperated by ' '
	 *
	 */
	function getAllPages($ns,$tag_list_r){
		$pages = array();
		$pages[''] = '';
		
		$tag_list = implode(' ',$tag_list_r);

		$page_r = $this->getPagesByTags($ns,$tag_list);

		foreach($page_r as $page){
			$title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
			$title = $title?$title:$page;
			$pages[$page]=strip_tags($title);  //FIXME hsc() doesent work with chosen
		}

		asort($pages);
		return $pages;
	}
	
	/*
	 * gets the pages defined by tag_list
	 *
	 * partially copied from tag->helper with less checks (on cache) and no meta lookups
	 * @param ns string the namespace to look in
	 * @param tag_list string the tags seperated by ' '
	 *
	 * @return array array of page ids
	 */
	function getPagesByTags($ns,$tag_list) {
		$page_names = array();
		$tags_parsed = $this->Htag->_parseTagList($tag_list, true);
		$pages_lookup = $this->Htag->_tagIndexLookup($tags_parsed);
		foreach($pages_lookup as  $page_lookup){
			// filter by namespace, root namespace is identified with a dot // root namespace is specified, discard all pages who lay outside the root namespace
			if((($ns == '.') && (getNS($page_lookup) == false)) || ( (strpos(':'.getNS($page_lookup).':', ':'.$ns.':') === 0)) || $ns === '') {
				$perm = auth_quickaclcheck($page_lookup);
				if (!($perm < AUTH_READ))
					$page_names[] = $page_lookup;
			}
		}
		return $page_names;		
	}
}

