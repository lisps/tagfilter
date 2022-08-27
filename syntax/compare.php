<?php

use dokuwiki\Form\Form;

/**
 * DokuWiki Plugin tagfilter (Syntax Component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */

class syntax_plugin_tagfilter_compare extends syntax_plugin_tagfilter_filter
{

    /*
     * What kind of syntax are we?
     */
    function getType()
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
    function getPType()
    {
        return 'block';
    }

    /*
     * Connect pattern to lexer
     */
    function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern("\{\{tagcompare>.*?\}\}", $mode, 'plugin_tagfilter_compare');
    }

    /*
     * Handle the matches
     */
    function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = trim(substr($match, 13, -2));

        return $this->getOpts($match);
    }

    /*
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $opt)
    {
        global $ID;
        global $INPUT;

        $flags = $opt['tagfilterFlags'];
        if ($format === 'metadata') return false;
        if ($format === 'xhtml') {
            $renderer->nocache();

            /* @var helper_plugin_tagfilter $Htagfilter */
            $Htagfilter = $this->loadHelper('tagfilter');
            /* @var helper_plugin_tagfilter_syntax $HtagfilterSyntax */
            $HtagfilterSyntax = $this->loadHelper('tagfilter_syntax');
            $renderer->cdata("\n");


            list($tagFilters, $allPageids) = $HtagfilterSyntax->getTagPageRelations($opt);
            $preparedPages = $HtagfilterSyntax->prepareList($allPageids, $flags);

            //check for read access
            foreach ($allPageids as $key => $pageid) {
                if (!$Htagfilter->canRead($pageid)) {
                    unset($allPageids[$key]);
                }
            }

            //check tags for visibility
            foreach ($tagFilters['pagesPerMatchedTags'] as $pagesPerMatchedTag) {
                foreach ($pagesPerMatchedTag as $tag => $pageidsPerTag) {
                    if (count(array_intersect($pageidsPerTag, $allPageids)) == 0) {
                        unset($pagesPerMatchedTag[$tag]);
                    }
                }
            }

            $dropdownValues = ['' => ''];
            foreach ($preparedPages as $key => $page) {
                if (!in_array($page['id'], $allPageids)) {
                    unset($preparedPages[$key]);
                }
                $dropdownValues[$page['id']] = $page['title'];
            }

            //dbg($INPUT->arr('tagcompare_page'));
            $selectedValues = $INPUT->arr('tagcompare_page');
            var_dump($selectedValues);
            echo '<div class="table plugin_tagcompare">';
            $form = new Doku_Form([
                'id' => 'tagcomparedd_' . $opt['id'],
                'data-plugin' => 'tagcompare',
                'method' => 'GET',
            ]);
            $form->addHidden('id', $ID);
            $form->addElement('<table>');
            $form->addElement('<thead>');
            $form->addElement('<tr>');
            $form->addElement('<th>');
            $form->addElement(hsc('Tags'));
            $form->addElement('</th>');

            for ($ii = 0; $ii < 4; $ii++) {
                $form->addElement('<th>');
                $form->addElement(form_makeListboxField('tagcompare_page[' . $ii . ']', $dropdownValues, $selectedValues[$ii] ?? null, '', '', 'tagcompare', []));
                $form->addElement('</th>');
            }
            $form->addElement('</tr>');
            $form->addElement('</thead>');

            $form->addElement('<tbody>');

            if ($flags['images']) {
                /** @var helper_plugin_pageimage $HPageimage */
                $HPageimage = $this->loadHelper('pageimage');
                $form->addElement('<tr>');
                $form->addElement('<th></th>');
                for ($ii = 0; $ii < 4; $ii++) {
                    $form->addElement('<td>');
                    if (!empty($selectedValues[$ii])) {
                        $form->addElement($HPageimage->td($selectedValues[$ii], ['firstimage' => true])); //fixme pageimage->td() does not accept flags as 2nd arg.
                    }
                    $form->addElement('</td>');
                }
                $form->addElement('</tr>');
            }
            // for each tagexpression a row is added, where tags that match the tagexpression are shown if they are also
            // used on the page selected for that table column
            foreach ($tagFilters['pagesPerMatchedTags'] as $idx => $pagesPerMatchedTag) {
                $form->addElement('<tr>');
                $form->addElement('<th>');
                $form->addElement(hsc($tagFilters['label'][$idx]));
                $form->addElement('</th>');

                for ($ii = 0; $ii < 4; $ii++) {
                    $form->addElement('<td>');
                    //more tags per cell(=per page) possible
                    foreach ($pagesPerMatchedTag as $tagName => $pageidsPerTag) {
                        if (isset($selectedValues[$ii]) & in_array($selectedValues[$ii], $pageidsPerTag)) {
                            $form->addElement(hsc($Htagfilter->getTagLabel($tagName)) . '<br>');
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

            for ($ii = 0; $ii < 4; $ii++) {

                $form->addElement('<th>');

                if (!empty($selectedValues[$ii])) {
                    $form->addElement('<a href="' . wl($selectedValues[$ii]) . '" class="wikilink1">Link</a>');
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
