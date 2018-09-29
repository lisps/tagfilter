<?php
use dokuwiki\Form\Form;

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
class syntax_plugin_tagfilter_compare extends DokuWiki_Syntax_Plugin {

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
		$this->Lexer->addSpecialPattern("\{\{tagcompare>.*?\}\}",$mode,'plugin_tagfilter_compare');
	}

    /*
     * Handle the matches
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
		global $ID;
		$HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
		$opts['id']=$this->incItemPos();

		$match=trim(substr($match,13,-2));

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

		$flags = $opt['tfFlags'];
		if($mode === 'metadata') return false;
		if($mode === 'xhtml') {
			$renderer->info['cache'] = false;
			
			/* @var $Htagfilter helper_plugin_tagfilter */
			$Htagfilter = $this->loadHelper('tagfilter');
			$HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
			$renderer->cdata("\n");


			$cachedata = $HtagfilterSyntax->getTagPageRelations($opt);
			$cachedata[] = $HtagfilterSyntax->prepareList($cachedata[1],$flags);

			list($tagselect_r, $pageids, $preparedPages) = $cachedata;
			
			//check for read access
			foreach($pageids as $key=>$pageid) {
				if(! $Htagfilter->canRead($pageid)) {
					unset($pageids[$key]);
				}
			}
			
			//check tags for visibility
			foreach($tagselect_r['tagPages'] as &$select_r) {
				foreach($select_r as $tag=>$pageid_r) {
					if(count(array_intersect(($pageid_r), $pageids)) == 0) {
						unset($select_r[$tag]);
					}
				}
			}
			
			$dropdownValues = array(''=>'');
			foreach($preparedPages as $key=>$page) {
				if(!in_array($page['id'],$pageids)) {
					unset($preparedPages[$key]);
				}
				$dropdownValues[$page['id']] = $page['title'];
			}
			
			//dbg($INPUT->arr('tagcompare_page'));
			$selectedValues = $INPUT->arr('tagcompare_page');
			echo '<div class="table plugin_tagcompare">';
			$form = new Doku_Form(array(
			    'id'=>'tagcomparedd_'.$ii,
			    'data-plugin'=>'tagcompare',
			    'method' => 'GET',
			));
			$form->addHidden('id', $ID);
			$form->addElement('<table>');
			$form->addElement('<thead>');
			$form->addElement('<tr>');
			$form->addElement('<th>');
			$form->addElement(hsc('Tags'));
			$form->addElement('</th>');

            for($ii = 0; $ii < 4; $ii++) {
                $form->addElement('<th>');
                $form->addElement(form_makeListboxField('tagcompare_page['.$ii.']', $dropdownValues, isset($selectedValues[$ii])?$selectedValues[$ii]:null , '', '', 'tagcompare' , array()));
                $form->addElement('</th>');
            }
            $form->addElement('</tr>');
            $form->addElement('</thead>');
            
			$form->addElement('<tbody>');
			
			if($flags['images']) {
			    /** @var $HPageimage helper_plugin_pageimage */
			    $HPageimage = $this->loadHelper('pageimage');
    			$form->addElement('<tr>');
    			$form->addElement('<th></th>');
    			for($ii = 0; $ii < 4; $ii++) {
    			    $form->addElement('<td>');
    			    if(isset($selectedValues[$ii]) && !empty($selectedValues[$ii])) {
    			        $form->addElement($HPageimage->td($selectedValues[$ii],array('firstimage' => true)));
    			    }
    			    $form->addElement('</td>');
    			}
    			$form->addElement('</tr>');
			}
			foreach($tagselect_r['tagPages'] as $idx => $tag_r) {
			    $form->addElement('<tr>');
			    $form->addElement('<th>');
			    $form->addElement(hsc($tagselect_r['label'][$idx]));
			    $form->addElement('</th>');
			    
			    for($ii = 0; $ii < 4; $ii++) {
			        $form->addElement('<td>');
			        foreach($tag_r as $tagName => $tags) {
    			        if(in_array($selectedValues[$ii],$tags)) {
    			             $form->addElement(hsc($Htagfilter->getTagLabel($tagName)). '<br>');
    			        }
			        }
			        $form->addElement('</td>');
			    }
			    
			    $form->addElement('</tr>');
			}
			
			$form->addElement('<tr>');
			$form->addElement('<th>');
			$form->addElement('Link');
			$form->addElement('</th>');
			
			for($ii = 0; $ii < 4; $ii++) {
			
			    $form->addElement('<th>');
			     
			    if(isset($selectedValues[$ii]) && !empty($selectedValues[$ii])) {
			     $form->addElement('<a href="'.wl($selectedValues[$ii]).'" class="wikilink1">Link</a>');
			    }
			
			    $form->addElement('</th>');
			
			}
			$form->addElement('</tr>');
			
			$form->addElement('</tbody>');
			$form->addElement('</table>');
			$form->addElement('</div>');
			

			$renderer->doc .= $form->getForm();
		}	
		return true;
	}
	

	
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
