<?php
/**
 * DokuWiki Plugin tagfilter (Syntax Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps    
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/*
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_tagfilter extends DokuWiki_Syntax_Plugin {

    private $idcount = 0;
	
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
    function getPType(){return 'normal';}

    /*
     * Connect pattern to lexer
     */
    function connectTo($mode) {
		$this->Lexer->addSpecialPattern("\{\{tagfilter>.*?\}\}",$mode,'plugin_tagfilter');
	}

    /*
     * Handle the matches
     */
    function handle($match, $state, $pos, &$handler) {
		global $ID;
		
		$opts['id']=$this->idcount++;

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
		
		
		$opts['tags']=explode('|',$tag);
		
		//only flags for tagfilter 
		$opts['tfFlags'] = $this->parseFlags($flags);
		
		//all flags for pagelist plugin
		$opts['flags']=$flags;
		
		return ($opts);
    }
            
    /*
     * Create output
     */
    function render($mode, &$renderer, $opt)
	{
		global $INFO;
		global $ID;
		global $INPUT;
		$jquery='';
		$flags = $opt['tfFlags'];
		//

		if($flags['cache'])
			p_set_metadata($ID, array('date'=>array('valid'=>array('age'=>$flags['cache'])))); 
		else {
			p_set_metadata($ID, array('date'=>array('valid'=>array('age'=>0)))); 
			$renderer->info['cache'] = false;
		}
		$Htagfilter  =& plugin_load('helper', 'tagfilter');
		if($mode == 'metadata') return false;
		if($mode == 'xhtml') {

			$renderer->cdata("\n");
				
			$form = new Doku_Form(array(
				'id'=>'tagdd_'.$opt['id'],
				'data-idx'=>$opt['id'],
				'data-plugin'=>'tagfilter',
			));
			$renderer->cdata("\n");
			//Fieldset manuell hinzufügen da ein style Parameter übergeben werden soll
			$form->addElement(array('_elem'=>'openfieldset', '_legend'=>'Tagfilter','style'=>'text-align:left;width:100%','id'=>'__tagfilter_'.$opt['id']));
			$form->_infieldset=true; //Fieldset starten
			
			$tagselect_r = array();
			foreach($opt['tags'] as $key=>$tag){
				$tag = explode("=",$tag);//aufsplitten in Label,RegExp,DefaultWert
				
				$tagselect_r['label'][$key]  = trim($tag[0]);
				$tagselect_r['tags'][$key] =  $Htagfilter->getTagsByRegExp(trim($tag[1]),$opt['ns']);
				$tagselect_r['selectedTags'][$key] = isset($tag[2])?explode(' ',$tag[2]):array();
				
			}
			
			if($flags['pagesearch']){
				$label = $flags['pagesearchlabel'];
				$tags = array();
				foreach($tagselect_r['tags'] as $tags_r){
					$tags = array_merge($tags,array_keys($tags_r));
				}

				$tags = $Htagfilter->getAllPages($opt['ns'],$tags);
				$selectedTags = array();
				$id = '__tagfilter_page_'.$opt['id'];
				$jquery .= 'jQuery("#'.$id.'").chosen({
								allow_single_deselect: true,});';
				$options = array(//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
					'onChange'=>'tagfilter_submit('.$opt['id'].','.json_encode($opt['ns']).','.json_encode($opt['flags']).')',
					'class'=>'tagdd_select tagfilter tagdd_select_'.$opt['id'],
					'data-placeholder'=>hsc($label.' '.$this->getLang('choose')),
					'data-label'=>hsc(utf8_strtolower(trim($label))),
					);
				if($flags['multi']){ //unterscheidung ob Multiple oder Single
					$options['multiple']='multiple';
					$options['size']=$this->getConf("DropDownList_size");
				}
				$form->addElement(form_makeListboxField($label, $tags, $selectedTags , $label, $id, 'tagfilter', $options));
			}
			foreach($opt['tags'] as $key=>$tag){
				$id=false;
                $tag = explode("=",$tag);//aufsplitten in Label,RegExp,DefaultWert
				$label = $tagselect_r['label'][$key];
				$tags = $tagselect_r['tags'][$key];
				$selectedTags = $tagselect_r['selectedTags'][$key];

				foreach($selectedTags as &$item)
					$item = utf8_strtolower(trim($item));
					
				$options = array(//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
					'onChange'=>'tagfilter_submit('.$opt['id'].','.json_encode($opt['ns']).','.json_encode($opt['flags']).')',
					'class'=>'tagdd_select tagfilter tagdd_select_'.$opt['id'],
					'data-placeholder'=>hsc($label.' '.$this->getLang('choose')),
					'data-label'=>hsc(str_replace(' ','_',utf8_strtolower(trim($label)))),
					
					);
				if($flags['multi']){ //unterscheidung ob Multiple oder Single
					$options['multiple']='multiple';
					$options['size']=$this->getConf("DropDownList_size");
				}
				else {
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
					$renderer->doc .= '<script type="text/javascript">/*<![CDATA[*/ var '.$jsVar.' ='
										.json_encode($links).
										'; /*!]]>*/</script>'."\n";

					$id='__tagfilter_'.$opt["id"].'_'.rand();
					$jquery .= 'jQuery("#'.$id.'").chosen({
								allow_single_deselect: true,';
					if($flags['tagimage']){
						$jquery .=  'template: function (text,value, templateData) {
							if(!(value in '.$jsVar.')) {return "";}
								return [
									('.$jsVar.'[value]["link"] == false) ? "":
										"<span style=\'float:right;height:100%;vertical-align:center;padding-top:3px;\'>"+
											"<img height='.($flags['multi']?'32px':'32px').' src=\'"+'.$jsVar.'[value]["link"]+"\'>"+
										"</span>",
										"<span>"+text+"</span>",
									('.$jsVar.'[value]["link"] == false) ? "":"<div style=\'clear:both;\'></div>"
								].join("");
							},
							templateSelected: function (text,value, templateData) {
								if(!(value in '.$jsVar.')) {return "";}
									return [
										('.$jsVar.'[value]["link"] == false) ? "":
											"<span style=\'float:right;height:100%;vertical-align:center;padding-top:3px;\'>"+
												"<img height='.($flags['multi']?'32px':'16px').' src=\'"+'.$jsVar.'[value]["link"]+"\'></span>",
											"<span>"+text+"</span>",
										('.$jsVar.'[value]["link"] == false) ? "":"<div style=\'clear:both;\'></div>"
									].join("");
						
							},';
					}
					$jquery .= '});' ."\n";
				}			
				$form->addElement(form_makeListboxField($label, $tags, $selectedTags , $label, $id, 'tagfilter', $options));
			}
			$form->addElement(form_makeButton('button','', $this->getLang('Delete filter'), array('onclick'=>'tagfilter_cleanform('.$opt['id'].')')));
			$form->endFieldset();
			$renderer->doc .= $form->getForm();//Form Ausgeben
			//Ergebnisfeld Ausgeben mit ScriptCode zum Verzögerten Laden des ersten Inhalts
			$renderer->doc.= "
<div id='tagfilter_ergebnis_".$opt['id']."' class='tagfilter'>
<script type='text/javascript'>jQuery(document).ready(function(){
	setTimeout(\"getSelectByFormId('".$opt['id']."')[0].onchange()\",2000*(".$opt['id']."));
	".$jquery."});
</script>
</div>";
			
		}	
		return true;
	}
	
	
	/*
	 * parseFlags checks for tagfilter flags and returns them as true/false
	 * @param $flags array 
	 * @return array tagfilter flags
	 */
	function parseFlags($flags){
		if(!is_array($flags)) return false;
		$conf = array(
			'multi' => false,
			'chosen' => false,
			'tagimage' => false,
			'pagesearch' => false,
			'pagesearchlable' => 'Seiten',
			'cache' => $this->getConf("cache_age"),

		);

		foreach($flags as $k=>$flag) {
			list($flag,$value) = explode('=',$flag,2);
			switch($flag) {
				case 'multi':
					$conf['multi'] = true;
					break;
				case 'chosen':
					$conf['chosen'] = true;
					break;
				case 'tagimage': 
					$conf['tagimage']= true;
					break;
				case 'pagesearch': 
					$conf['pagesearch']= true;
					if($value != ''){
						$conf['pagesearchlabel'] = hsc($value);
					}
					break;
				case 'cache':
					$conf['cache'] = intval($value);
					break;
				case 'nocache':
					$conf['cache'] = false;
					break;
			}
		}
	
		return $conf;
	}
	
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
