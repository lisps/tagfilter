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
	}
	
	/**
	 * Inserts the toolbar button
	 */
	function insert_button(&$event, $param) {
		$event->data[] = array(	
			'type'   => 'format',
			'title' => 'Tagfilter plugin',
			'icon'   => '../../plugins/tagfilter/tagfilter.png',
			'sample' => '<S/M/SC/MC> <namespace>? <Label>=<TagAusdruck>=<Tags Selected>|... &<pagelistoptions>',
			'open' => '{{tagfilter',
			'close'=>'}}',
			'insert'=>'',
		);
	}
}
