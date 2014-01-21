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
	function register(&$controller) {
		$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
		$controller->register_hook('DOKUWIKI_STARTED', 'AFTER',  $this, '_addparams');
	}

	function _addparams(&$event, $param) {
		global $JSINFO;
		global $INPUT;
    // filter for ft* in GET
    $f = create_function('$value', 'return strpos($value,"tf") === 0;');
    $get_tagfilter = array_filter(array_keys($_GET),$f);

		//filter for ft<key>_<label> and add it to JSINFO to select it via JavaScript
		foreach($get_tagfilter as $param){
			$ret = preg_match('/^tf(\d+)\_([\w\:הצü\-]+)/',$param,$matches);

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
}
