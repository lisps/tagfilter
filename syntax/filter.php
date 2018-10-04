<?php
/**
 * DokuWiki Plugin tagfilter (Syntax Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps    
 */

/*
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_tagfilter_filter extends DokuWiki_Syntax_Plugin {

    private $_itemPos = array();
    function incItemPos() {
        global $ID;
        if(array_key_exists($ID,$this->_itemPos)) {
            return $this->_itemPos[$ID]++;
        } else {
            $this->_itemPos[$ID] = 1;
            return 0;
        }
    }
    function getItemPos(){
        global $ID;
        if(array_key_exists($ID,$this->_itemPos)) {
            $this->_itemPos[$ID];
        } else {
            return 0;
        }
    }
	
    /*
     * What kind of syntax are we?
     */
    function getType() {return 'substition';}

    /*
     * Where to sort in?
     */
    function getSort() {return 155;}

    /*
     * Paragraph Type
     */
    function getPType(){return 'block';}

    /*
     * Connect pattern to lexer
     */
    function connectTo($mode) {
		$this->Lexer->addSpecialPattern("\{\{tagfilter>.*?\}\}",$mode,'plugin_tagfilter_filter');
	}

    /*
     * Handle the matches
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
		global $ID;
		$HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
		$opts['id']=$this->incItemPos();

		$match=trim(substr($match,12,-2));

        list($match, $flags) = explode('&', $match, 2);
        $flags = explode('&', $flags);
        list($ns, $tag) = explode('?', $match);

        if (!$tag) {
            $tag = $ns;
            $ns   = '';
        }

        if (($ns == '*') || ($ns == ':')) $ns = '';
        elseif ($ns == '.') $ns = getNS($ID);
        else $ns = cleanID($ns);
		
		$opts['ns']=$ns;
		
		//only flags for tagfilter 
		$opts['tfFlags'] = $HtagfilterSyntax->parseFlags($flags);
		
		//all flags for pagelist plugin
		$opts['flags']=array_map('trim',$flags);
		
		//read and parse tag 
		$tagselect_r = array();
		$select_expr_r = array_map('trim',explode('|',$tag));
		foreach($select_expr_r as $key=>$usr_syntax){
			$usr_syntax = explode("=",$usr_syntax);//aufsplitten in Label,RegExp,DefaultWert
				
			$tagselect_r['label'][$key]  = trim($usr_syntax[0]);
			$tagselect_r['tag_expr'][$key]  = trim($usr_syntax[1]);
			$tagselect_r['selectedTags'][$key] = isset($usr_syntax[2])?explode(' ',$usr_syntax[2]):array();
		}
		
		$opts['tagselect_r'] = $tagselect_r;
		 
		return ($opts);
    }
            
    /* 
     * Create output
     */
    function render($mode, Doku_Renderer $renderer, $opt)
	{
		global $INFO;
		global $ID;
		global $INPUT;
		global $USERINFO;
		global $conf;
		/* @var  $HtagfilterSyntax helper_plugin_tagfilter_syntax */ 
		$HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
		$flags = $opt['tfFlags'];

		if($mode === 'metadata') return false;
		if($mode === 'xhtml') {
			$renderer->info['cache'] = false;
			
			/* @var $Htagfilter helper_plugin_tagfilter */
			$Htagfilter = $this->loadHelper('tagfilter');
			$renderer->cdata("\n");

			
			$depends = array('files'=>array(
				$INFO['filepath'],
				DOKU_CONF . '/acl.auth.php',
				getConfigFiles('main'),		
			));
			
			if($flags['cache']){
				$depends['age']=$flags['cache'];
			} else if($flags['cache'] === false){
				//build cache dependencies
				$dir  = utf8_encodeFN(str_replace(':','/',$opt['ns']));
				$data = array();
				search($data,$conf['datadir'],array($HtagfilterSyntax,'search_all_pages'),array('ns' => $opt['ns'],'excludeNs'=>$flags['excludeNs']),$dir); //all pages inside namespace
				$depends['files'] = array_merge($depends['files'],$data);
			} else {
				$depends['purge'] = true;
			}
			
			$cache_key = 'plugin_tagfilter_'.$ID . '_' . $opt['id'];
			$cache = new cache($cache_key, '.tcache');
			if(!$cache->useCache($depends)) {
				$cachedata = $HtagfilterSyntax->getTagPageRelations($opt);
				$cachedata[] = $HtagfilterSyntax->prepareList($cachedata[1],$flags);
				$cache->storeCache(serialize($cachedata));
			} else {
				$cachedata = unserialize($cache->retrieveCache());
			}

			list($tagselect_r, $pageids, $preparedPages) = $cachedata;
			
			$cache_u_key = 'plugin_tagfilter_'.$ID . '_' . $opt['id'] . '_' . $_SERVER['REMOTE_USER'].$_SERVER['HTTP_HOST'].$_SERVER['SERVER_PORT'];
			$cache_u = new cache($cache_u_key, '.tucache');
			
			foreach($pageids as &$pageid) {
				if(!page_exists($pageid)) {
					unset($pageid);
					$cache->removeCache();
					$cache_u->removeCache();
				}
			} unset($pageid);
			
			if(!$cache_u->useCache(array('files'=>array($cache->cache)))) {
				$output = '';
				
				//check for read access
				foreach($pageids as $key=>$pageid) {
					if(! $Htagfilter->canRead($pageid)) {
						unset($pageids[$key]);
					}
				}
				
				//check tags for visibility
				foreach($tagselect_r['tagPages'] as &$select_r) {
				    if(!is_array($select_r)) $select_r = array();
					foreach($select_r as $tag=>$pageid_r) {
						if(count(array_intersect(($pageid_r), $pageids)) == 0) {
							unset($select_r[$tag]);
						}
					}
				}
				
				foreach($preparedPages as $key=>$page) {
					if(!in_array($page['id'],$pageids)) {
						unset($preparedPages[$key]);
					}
				}
				 
				$form = new Doku_Form(array(
					'id'=>'tagdd_'.$opt['id'],
					'data-idx'=>$opt['id'],
					'data-plugin'=>'tagfilter',
					'data-tags' => json_encode($tagselect_r['tagPages']),
				));
				$output .= "\n";
				//Fieldset manuell hinzufügen da ein style Parameter übergeben werden soll
				$form->addElement(array(
						'_elem'=>'openfieldset', 
						'_legend'=>'Tagfilter',
						'style'=>'text-align:left;width:99%',
						'id'=>'__tagfilter_'.$opt['id'],
						'class'=>($flags['labels']!==false)?'':'hidelabel',
						
				));
				$form->_infieldset=true; //Fieldset starten
				
				if($flags['pagesearch']){
					$label = $flags['pagesearchlabel'];
	
					$pagetitle_r = array();
					foreach($pageids as $pageid) {
						$pagetitle_r[$pageid] = $Htagfilter->getPageTitle($pageid);
					}
					asort($pagetitle_r, SORT_NATURAL|SORT_FLAG_CASE);
	
					$selectedTags = array();
					$id = '__tagfilter_page_'.$opt['id'];
	
					$options = array(//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
						'onChange'=>'tagfilter_submit('.$opt['id'].','.json_encode($opt['ns']).','.json_encode(array($opt['flags'],$flags)).')',
						'class'=>'tagdd_select tagfilter tagdd_select_'.$opt['id']  . ($flags['chosen']?' chosen':''),
						'data-placeholder'=>hsc($label.' '.$this->getLang('choose')),
						'data-label'=>hsc(utf8_strtolower(trim($label))),
						);
					if($flags['multi']){ //unterscheidung ob Multiple oder Single
						$options['multiple']='multiple';
						$options['size']=$this->getConf("DropDownList_size");
					} else {
						$options['size']=1;
						$pagetitle_r = array_reverse($pagetitle_r,true);
						$pagetitle_r['']='';
						$pagetitle_r = array_reverse($pagetitle_r,true);
					}
					$form->addElement(form_makeListboxField($label, $pagetitle_r, $selectedTags , $label, $id, 'tagfilter', $options));
				}
				$output .= '<script type="text/javascript">/*<![CDATA[*/ var tagfilter_container = {}; /*!]]>*/</script>'."\n";
				//$output .= '<script type="text/javascript">/*<![CDATA[*/ '.'tagfilter_container.tagfilter_'.$opt['id'].' = '.json_encode($tagselect_r['tags2']).'; /*!]]>*/</script>'."\n";
				foreach($tagselect_r['tagPages'] as $key=>$pages){
					$id=false;
					$label = $tagselect_r['label'][$key];
					$selectedTags = $tagselect_r['selectedTags'][$key];
					
					//get tag labels
					$tags = array();
					
					foreach(array_keys($tagselect_r['tagPages'][$key]) as $tagid) {
						$tags[$tagid] = $Htagfilter->getTagLabel($tagid);
					}
					
					foreach($selectedTags as &$item) {
						$item = utf8_strtolower(trim($item));
					} unset($item);
					
						
					$options = array(//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
						'onChange'=>'tagfilter_submit('.$opt['id'].','.json_encode($opt['ns']).','.json_encode(array($opt['flags'],$flags)).')',
						'class'=>'tagdd_select tagfilter tagdd_select_'.$opt['id'] . ($flags['chosen']?' chosen':''),
						'data-placeholder'=>hsc($label.' '.$this->getLang('choose')),
						'data-label'=>hsc(str_replace(' ','_',utf8_strtolower(trim($label)))),
						
						);
					if($flags['multi']){ //unterscheidung ob Multiple oder Single
						$options['multiple']='multiple';
						$options['size']=$this->getConf("DropDownList_size");
					} else {
						$options['size']=1;
						$tags = array_reverse($tags,true);
						$tags['']='';
						$tags = array_reverse($tags,true);
					}
					
					if($flags['chosen']){
						$links = array();
						foreach($tags as $k=>$t){
							$links[$k]=array(
								'link'=>$Htagfilter->getImageLinkByTag($k),
							);
						}
						$jsVar = 'tagfilter_jsVar_'.rand();
						$output .= '<script type="text/javascript">/*<![CDATA[*/ tagfilter_container.'.$jsVar.' ='
											.json_encode($links).
											'; /*!]]>*/</script>'."\n";
	
						$id='__tagfilter_'.$opt["id"].'_'.rand();
	
						if($flags['tagimage']){
						    $options['data-tagimage'] = $jsVar;
						}
	
					}			
					$form->addElement(form_makeListboxField($label, $tags, $selectedTags , $label, $id, 'tagfilter' , $options));
				}
				
				$form->addElement(form_makeButton('button','', $this->getLang('Delete filter'), array('onclick'=>'tagfilter_cleanform('.$opt['id'].',true)')));
				if($flags['count']) {
				    $form->addElement('<div class="tagfilter_count">'.$this->getLang('found_count').': ' . '<span class="tagfilter_count_number"></span></div>');
				}
				$form->endFieldset();
				$output .= $form->getForm();//Form Ausgeben
							
				$output.= "<div id='tagfilter_ergebnis_".$opt['id']."' class='tagfilter'>";
				//dbg($opt['flags']);
				$output .= $HtagfilterSyntax->renderList($preparedPages,$flags, $opt['flags']);
				$output.= "</div>";
				
				$cache_u->storeCache($output);
				
			} else {
				$output =$cache_u->retrieveCache();
			}
			
			$renderer->doc .= $output;
		}	
		return true;
	}
	

	
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
