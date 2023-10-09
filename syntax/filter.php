<?php

use dokuwiki\Cache\Cache;

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

class syntax_plugin_tagfilter_filter extends DokuWiki_Syntax_Plugin
{

    /** @var int[] counts forms per page for creating an unique form id */
    protected $formCounter = [];

    protected function incrementFormCounter()
    {
        global $ID;
        if (array_key_exists($ID, $this->formCounter)) {
            return $this->formCounter[$ID]++;
        } else {
            $this->formCounter[$ID] = 1;
            return 0;
        }
    }

    protected function getFormCounter()
    {
        global $ID;
        if (array_key_exists($ID, $this->formCounter)) {
            return $this->formCounter[$ID];
        } else {
            return 0;
        }
    }

    /*
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    /*
     * Where to sort in?
     */
    function getSort()
    {
        return 155;
    }

    /*
     * Paragraph Type
     */
    public function getPType()
    {
        return 'block';
    }

    /*
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern("\{\{tagfilter>.*?\}\}", $mode, 'plugin_tagfilter_filter');
    }

    /*
     * Handle the matches
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = trim(substr($match, 12, -2));

        return $this->getOpts($match);
    }

    /**
     * Parses syntax written by user
     *
     * @param string $match The text matched in the pattern
     * @return array with:<br>
     *      int 'id' unique number for current form,
     *      string 'ns' list only pages from this namespace,
     *      array 'pagelistFlags' all flags set by user in syntax, will be supplied directly to pagelist plugin,
     *      array 'tagfilterFlags' only tags for the tagfilter plugin @see helper_plugin_tagfilter_syntax::parseFlags()
     */
    protected function getOpts($match)
    {
        global $ID;

        /** @var helper_plugin_tagfilter_syntax $HtagfilterSyntax */
        $HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
        $opts['id'] = $this->incrementFormCounter();

        list($match, $flags) = array_pad(explode('&', $match, 2), 2, '');
        $flags = explode('&', $flags);


        list($ns, $tag) = array_pad(explode('?', $match), 2, '');
        if ($tag === '') {
            $tag = $ns;
            $ns = '';
        }

        if (($ns == '*') || ($ns == ':')) {
            $ns = '';
        } elseif ($ns == '.') {
            $ns = getNS($ID);
        } else {
            $ns = cleanID($ns);
        }

        $opts['ns'] = $ns;

        //only flags for tagfilter
        $opts['tagfilterFlags'] = $HtagfilterSyntax->parseFlags($flags);

        //all flags set by user for pagelist plugin
        $opts['pagelistFlags'] = array_map('trim', $flags);

        //read and parse tag
        $tagFilters = [];
        $selectExpressions = array_map('trim', explode('|', $tag));
        foreach ($selectExpressions as $key => $parts) {
            $parts = explode("=", $parts);//split in Label,RegExp,Default value

            $tagFilters['label'][$key] = trim($parts[0]);
            $tagFilters['tagExpression'][$key] = trim($parts[1] ?? '');
            $tagFilters['selectedTags'][$key] = isset($parts[2]) ? explode(' ', $parts[2]) : [];
        }

        $opts['tagFilters'] = $tagFilters;

        return $opts;
    }

    /**
     * Create output
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $opt data created by handler()
     * @return boolean rendered correctly?
     */
    public function render($format, Doku_Renderer $renderer, $opt)
    {
        global $INFO, $ID, $conf, $INPUT;

        /* @var  helper_plugin_tagfilter_syntax $HtagfilterSyntax */
        $HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
        $flags = $opt['tagfilterFlags'];

        if ($format === 'metadata') return false;
        if ($format === 'xhtml') {
            $renderer->nocache();

            $renderer->cdata("\n");

            $depends = [
                'files' => [
                    $INFO['filepath'],
                    DOKU_CONF . 'acl.auth.php',
                ]
            ];
            $depends['files'] = array_merge($depends['files'], getConfigFiles('main'));

            if ($flags['cache']) {
                $depends['age'] = $flags['cache'];
            } else if ($flags['cache'] === false) {
                //build cache dependencies TODO check if this bruteforce method (adds just all pages of namespace as dependency) is proportional
                $dir = utf8_encodeFN(str_replace(':', '/', $opt['ns']));
                $data = [];
                $opts = [
                    'ns' => $opt['ns'],
                    'excludeNs' => $flags['excludeNs']
                ];
                search($data, $conf['datadir'], [$HtagfilterSyntax, 'search_all_pages'], $opts, $dir); //all pages inside namespace
                $depends['files'] = array_merge($depends['files'], $data);
            } else {
                $depends['purge'] = true;
            }

            //cache to store tagfilter options, matched pages and prepared data
            $filterDataCacheKey = 'plugin_tagfilter_' . $ID . '_' . $opt['id'];
            $filterDataCache = new Cache($filterDataCacheKey, '.tcache');
            if (!$filterDataCache->useCache($depends)) {
                $cachedata = $HtagfilterSyntax->getTagPageRelations($opt);
                $cachedata[] = $HtagfilterSyntax->prepareList($cachedata[1], $flags);
                $filterDataCache->storeCache(serialize($cachedata));
            } else {
                $cachedata = unserialize($filterDataCache->retrieveCache());
            }

            list($tagFilters, $allPageids, $preparedPages) = $cachedata;

            // cache to store html per user
            $htmlPerUserCacheKey = 'plugin_tagfilter_' . $ID . '_' . $opt['id'] . '_' . $INPUT->server->str('REMOTE_USER')
                . $INPUT->server->str('HTTP_HOST') . $INPUT->server->str('SERVER_PORT');
            $htmlPerUserCache = new Cache($htmlPerUserCacheKey, '.tucache');

            //purge cache if pages does not exist anymore
            foreach ($allPageids as $key => $pageid) {
                if (!page_exists($pageid)) {
                    unset($allPageids[$key]);
                    $filterDataCache->removeCache();
                    $htmlPerUserCache->removeCache();
                }
            }

            if (empty($flags['include'])) {
                if (!$htmlPerUserCache->useCache(['files' => [$filterDataCache->cache]])) {
                    $html = $this->htmlOutput($tagFilters, $allPageids, $preparedPages, $opt);
                    $htmlPerUserCache->storeCache($html);
                } else {
                    $html = $htmlPerUserCache->retrieveCache();
                }

                $renderer->doc .= $html;
            } else {
                // Use include plugin. Does not use the htmlPerUserCache. TODO?

                // attention: htmlPrepareOutput modifies $tagFilters, $allPageids, $preparedPages.
                $this->htmlPrepareOutput($tagFilters, $allPageids, $preparedPages, $opt);
                $renderer->doc .= $this->htmlFormOutput($tagFilters, $allPageids, $opt);
                $renderer->doc .= "<div id='tagfilter_ergebnis_" . $opt['id'] . "' class='tagfilter'>";

                $includeHelper = $this->loadHelper('include');
                $includeFlags = $includeHelper->get_flags($flags['include']);

                foreach($preparedPages as $page) {
                    $renderer->nest($includeHelper->_get_instructions($page['id'], '', 'page', 0, $includeFlags));
                }

                $renderer->doc .= "</div>";
            }
        }
        return true;
    }

    /**
     * Returns html of the tagfilter form
     *
     * @param array $tagFilters
     * @param array $allPageids
     * @param array $preparedPages
     * @param array $opt option array from the handler
     * @return string
     */
    private function htmlOutput($tagFilters, $allPageids, $preparedPages, array $opt)
    {
        // attention: htmlPrepareOutput modifies $tagFilters, $allPageids, $preparedPages.
        $this->htmlPrepareOutput($tagFilters, $allPageids, $preparedPages, $opt);

        $output = $this->htmlFormOutput($tagFilters, $allPageids, $opt)
            . $this->htmlPagelistOutput($preparedPages, $opt);

        return $output;
    }

    private function htmlPrepareOutput(&$tagFilters, &$allPageids, &$preparedPages, array $opt)
    {
        /* @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');

        //check for read access
        foreach ($allPageids as $key => $pageid) {
            if (!$Htagfilter->canRead($pageid)) {
                unset($allPageids[$key]);
            }
        }

        //check tags for visibility
        foreach ($tagFilters['pagesPerMatchedTags'] as &$pagesPerMatchedTag) {
            if (!is_array($pagesPerMatchedTag)) {
                $pagesPerMatchedTag = [];
            }
            foreach ($pagesPerMatchedTag as $tag => $pageidsPerTag) {
                if (count(array_intersect($pageidsPerTag, $allPageids)) == 0) {
                    unset($pagesPerMatchedTag[$tag]);
                }
            }
        }
        unset($pagesPerMatchedTag);

        foreach ($preparedPages as $key => $page) {
            if (!in_array($page['id'], $allPageids)) {
                unset($preparedPages[$key]);
            }
        }
    }

    private function htmlFormOutput($tagFilters, $allPageids, array $opt) {
        /* @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');

        $flags = $opt['tagfilterFlags'];
        $output = '';

        $form = new Doku_Form([
            'id' => 'tagdd_' . $opt['id'],
            'data-idx' => $opt['id'],
            'data-plugin' => 'tagfilter',
            'data-tags' => json_encode($tagFilters['pagesPerMatchedTags']),
        ]);
        $output .= "\n";
        //Fieldset manuell hinzufügen da ein style Parameter übergeben werden soll
        $form->addElement([
            '_elem' => 'openfieldset',
            '_legend' => 'Tagfilter',
            'style' => 'text-align:left;width:99%',
            'id' => '__tagfilter_' . $opt['id'],
            'class' => ($flags['labels'] !== false) ? '' : 'hidelabel',

        ]);
        $form->_infieldset = true; //Fieldset starten

        if ($flags['pagesearch']) {
            $label = $flags['pagesearchlabel'];

            $pagetitles = [];
            foreach ($allPageids as $pageid) {
                $pagetitles[$pageid] = $Htagfilter->getPageTitle($pageid);
            }
            asort($pagetitles, SORT_NATURAL | SORT_FLAG_CASE);

            $selectedTags = [];
            $id = '__tagfilter_page_' . $opt['id'];

            $attrs = [//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
                'onChange' => 'tagfilter_submit(' . $opt['id'] . ',' . json_encode($opt['ns']) . ',' . json_encode([$opt['pagelistFlags'], $flags]) . ')',
                'class' => 'tagdd_select tagfilter tagdd_select_' . $opt['id'] . ($flags['chosen'] ? ' chosen' : ''),
                'data-placeholder' => hsc($label . ' ' . $this->getLang('choose')),
                'data-label' => hsc(utf8_strtolower(trim($label))),
            ];
            if ($flags['multi']) { //unterscheidung ob Multiple oder Single
                $attrs['multiple'] = 'multiple';
                $attrs['size'] = $this->getConf("DropDownList_size");
            } else {
                $attrs['size'] = 1;
                $pagetitles = array_reverse($pagetitles, true);
                $pagetitles[''] = '';
                $pagetitles = array_reverse($pagetitles, true);
            }
            $form->addElement(form_makeListboxField($label, $pagetitles, $selectedTags, $label, $id, 'tagfilter', $attrs));
        }
        $output .= '<script type="text/javascript">/*<![CDATA[*/ var tagfilter_container = {}; /*!]]>*/</script>' . "\n";
        //$output .= '<script type="text/javascript">/*<![CDATA[*/ '.'tagfilter_container.tagfilter_'.$opt['id'].' = '.json_encode($tagFilters['tags2']).'; /*!]]>*/</script>'."\n";
        foreach ($tagFilters['pagesPerMatchedTags'] as $key => $pagesPerMatchedTag) {
            $id = false;
            $label = $tagFilters['label'][$key];
            $selectedTags = $tagFilters['selectedTags'][$key];

            //get tag labels
            $tags = [];

            foreach (array_keys($pagesPerMatchedTag) as $tagid) {
                $tags[$tagid] = $Htagfilter->getTagLabel($tagid);
            }

            foreach ($selectedTags as &$item) {
                $item = utf8_strtolower(trim($item));
            }
            unset($item);


            $attrs = [//generelle Optionen für DropDownListe onchange->submit von id namespace und den flags für pagelist
                'onChange' => 'tagfilter_submit(' . $opt['id'] . ',' . json_encode($opt['ns']) . ',' . json_encode([$opt['pagelistFlags'], $flags]) . ')',
                'class' => 'tagdd_select tagfilter tagdd_select_' . $opt['id'] . ($flags['chosen'] ? ' chosen' : ''),
                'data-placeholder' => hsc($label . ' ' . $this->getLang('choose')),
                'data-label' => hsc(str_replace(' ', '_', utf8_strtolower(trim($label)))),

            ];
            if ($flags['multi']) { //unterscheidung ob Multiple oder Single
                $attrs['multiple'] = 'multiple';
                $attrs['size'] = $this->getConf("DropDownList_size");
            } else {
                $attrs['size'] = 1;
                $tags = array_reverse($tags, true);
                $tags[''] = '';
                $tags = array_reverse($tags, true);
            }

            if ($flags['chosen']) {
                $links = [];
                foreach ($tags as $k => $t) {
                    $links[$k] = [
                        'link' => $Htagfilter->getImageLinkByTag($k),
                    ];
                }
                $jsVar = 'tagfilter_jsVar_' . rand();
                $output .= '<script type="text/javascript">/*<![CDATA[*/ tagfilter_container.' . $jsVar . ' ='
                    . json_encode($links) .
                    '; /*!]]>*/</script>' . "\n";

                $id = '__tagfilter_' . $opt["id"] . '_' . rand();

                if ($flags['tagimage']) {
                    $attrs['data-tagimage'] = $jsVar;
                }

            }
            $form->addElement(form_makeListboxField($label, $tags, $selectedTags, $label, $id, 'tagfilter', $attrs));
        }

        $form->addElement(form_makeButton('button', '', $this->getLang('Delete filter'), ['onclick' => 'tagfilter_cleanform(' . $opt['id'] . ',true)']));
        if ($flags['count']) {
            $form->addElement('<div class="tagfilter_count">' . $this->getLang('found_count') . ': ' . '<span class="tagfilter_count_number"></span></div>');
        }
        $form->endFieldset();
        $output .= $form->getForm();//Form Ausgeben

        return $output;
    }

    private function htmlPagelistOutput($preparedPages, array $opt) {
        /* @var  helper_plugin_tagfilter_syntax $HtagfilterSyntax */
        $HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');

        $output = '';

        $output .= "<div id='tagfilter_ergebnis_" . $opt['id'] . "' class='tagfilter'>";
        //dbg($opt['pagelistFlags']);
        $output .= $HtagfilterSyntax->renderList($preparedPages, $opt['tagfilterFlags'], $opt['pagelistFlags']);
        $output .= "</div>";

        return $output;
    }
}
