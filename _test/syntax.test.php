<?php

/**
 * @group plugin_tagfilter
 * @group plugins
 */
class plugin_tagfilter_syntax_test extends DokuWikiTest
{

    protected $pluginsEnabled = [
        'tag', 'tagfilter'
    ];

    public function setup(): void
    {
        parent::setup();
        $this->_createPages();
    }


    public function test_basic_syntax()
    {
        global $ID, $INFO;
        $ID = 'test:plugin_tagfilter:start';
        $INFO = pageinfo(); //filepath is used
        $xhtml = p_wiki_xhtml('test:plugin_tagfilter:start');
        $doc = phpQuery::newDocument($xhtml);

        $form = pq('form[data-plugin=tagfilter]', $doc);

        //echo $form;
        $select = pq('select', $form);
        $this->assertTrue($select->count() === 3);
        $this->assertEquals('T1', $select->eq(0)->attr('name'));
        $this->assertEquals('T2', $select->eq(1)->attr('name'));
        $this->assertEquals('T3', $select->eq(2)->attr('name'));

        $this->assertEquals('T1 selection', $select->eq(0)->attr('data-placeholder'));
        $this->assertEquals('T2 selection', $select->eq(1)->attr('data-placeholder'));
        $this->assertEquals('T3 selection', $select->eq(2)->attr('data-placeholder'));

        $this->assertNull($select->eq(0)->attr('multiple'));
        $this->assertNull($select->eq(1)->attr('multiple'));
        $this->assertNull($select->eq(2)->attr('multiple'));

        $options = pq('option', $select->eq(0));
        $this->assertEquals('', $options->eq(0)->text());
        $this->assertEquals('Blorg', $options->eq(1)->text());

        $options = pq('option', $select->eq(1));
        $this->assertEquals('', $options->eq(0)->text());
        $this->assertEquals('A', $options->eq(1)->text());
        $this->assertEquals('B', $options->eq(2)->text());

        $options = pq('option', $select->eq(2));
        $this->assertEquals('', $options->eq(0)->text());
        $this->assertEquals('1', $options->eq(1)->text());
        $this->assertEquals('2', $options->eq(2)->text());
    }

    public function test_syntax_pagesearch()
    {
        global $ID, $INFO;
        $ID = 'test:plugin_tagfilter:start2';
        $INFO = pageinfo(); //filepath is used
        $xhtml = p_wiki_xhtml('test:plugin_tagfilter:start2');
        $doc = phpQuery::newDocument($xhtml);

        $form = pq('form[data-plugin=tagfilter]', $doc);
        $select = pq('select', $form);
        $this->assertTrue($select->count() === 4);


        $options = pq('option', $select->eq(0));
        $this->assertEquals('', $options->eq(0)->text());
        $this->assertEquals('Tagpage1', $options->eq(1)->text());
        $this->assertEquals('Tagpage2', $options->eq(2)->text());
    }

    public function test_syntax_multi()
    {
        global $ID, $INFO;
        $ID = 'test:plugin_tagfilter:start3';
        $INFO = pageinfo(); //filepath is used
        $xhtml = p_wiki_xhtml('test:plugin_tagfilter:start3');
        $doc = phpQuery::newDocument($xhtml);

        $form = pq('form[data-plugin=tagfilter]', $doc);

        $select = pq('select', $form);
        $this->assertTrue($select->count() === 3);
        $this->assertEquals('multiple', $select->eq(0)->attr('multiple'));
        $this->assertEquals('multiple', $select->eq(1)->attr('multiple'));
        $this->assertEquals('multiple', $select->eq(2)->attr('multiple'));

        $options = pq('option', $select->eq(0));
        $this->assertEquals('Blorg', $options->eq(0)->text());

    }

    protected function _createPages()
    {
        saveWikiText('test:plugin_tagfilter:tags:tagpage1', '==== Tagpage1 ====' . DOKU_LF . '{{tag>cat1:blorg cat2:a cat3:1}}', 'test');
        saveWikiText('test:plugin_tagfilter:tags:tagpage2', '==== Tagpage2 ====' . DOKU_LF . '{{tag>cat1:blorg cat2:b cat3:2}}', 'test');
        saveWikiText('test:plugin_tagfilter:start', '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*}}', 'test');
        saveWikiText('test:plugin_tagfilter:start2', '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*&pagesearch}}', 'test');
        saveWikiText('test:plugin_tagfilter:start3', '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*&multi}}', 'test');

        idx_addPage('test:plugin_tagfilter:tags:tagpage1', false);
        idx_addPage('test:plugin_tagfilter:tags:tagpage2', false);
    }


}
