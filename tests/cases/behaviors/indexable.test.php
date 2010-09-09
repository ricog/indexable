<?php

App::import('Behavior', 'Indexable.Indexable');

class TestIndexableBehavior extends IndexableBehavior {

	function returnSettings(&$Model) {
		return $this->settings;
	}

	function testIndexifyField(&$Model, $string) {
		return $this->_indexifyField($Model, $string);
	}
}

class Article extends CakeTestModel {
	var $name = 'Article';
	var $useTable = 'articles';
	var $actsAs = array(
		'TestIndexable' => array(
			'fields' => array(
				'title',
			),
		),
	);
}

class ArticleCustom extends CakeTestModel {
	var $name = 'ArticleCustom';
	var $useTable = 'articles';
	var $actsAs = array(
		'TestIndexable' => array(
			'fields' => array(
				'title',
			),
			'rules' => array(
				'replace_after' => array(
					'all others' => array(
						'pattern' => '[^\w\- ]',
						'replacement' => ' ',
						'order' => 100,
					),
					'internal hyphens' => array(
						'pattern' => '(\w)-(\w)',
						'replacement' => '$1 $2',
						'order' => 150,
					),
					'custom rule' => array(
						'pattern' => '    ',
						'replacement' => 'MANY SPACES',
						'order' => 175,
					),
				),
			),
		),
	);
}

class IndexableTestCase extends CakeTestCase { 
	var $fixtures = array('plugin.indexable.article');

	function startTest() {
		$this->Article =& ClassRegistry::init('Article');
		$this->ArticleCustom =& ClassRegistry::init('ArticleCustom');
	}

	function testIndexifyField() {
		$strings = array(
			array(
				'string' => 'testing normal string',
				'expected' => array('testing normal string'),
			),
			array(
				'string' => 'testing-hyphens',
				'expected' => array('testing hyphens'),
			),
			array(
				'string' => 'testing, some, commas',
				'expected' => array('testing', 'some', 'commas'),
			),
			array(
				'string' => 'testing indexify\'s apostrophe removal',
				'expected' => array('testing indexifys apostrophe removal'),
			),
			array(
				'string' => '-hyphens removed from beginning and end -',
				'expected' => array('hyphens removed from beginning and end'),
			),
			array(
				'string' => 'allow, duplicate, duplicate, strings',
				'expected' => array('allow', 'duplicate', 'duplicate', 'strings'),
			),
		);

		foreach ($strings as $string) {
			$this->assertEqual($string['expected'], $this->Article->testIndexifyField($string['string']));
		}
	}

	function testCustomRules() {
		// Check the stock settings
		$result = $this->Article->returnSettings();
		$result = $result['Article'];
		$expected = array(
			'rules' => array(
				'dividers' => ',\/;:',
				'replace_before' => array(
					'apostrophe' => array(
						'pattern' => "\'",
						'replacement' => '',
						'order' => 100,
					),
					'html' => array(
						'pattern' => "<.*?>",
						'replacement' => '',
						'order' => 200,
					),
					'parenthesis' => array(
						'pattern' => "\(.*?\)",
						'replacement' => '',
						'order' => 300,
					),
				),
				'replace_after' => array(
					'all others' => array(
						'pattern' => '[^\w ]',
						'replacement' => ' ',
						'order' => 100,
					),
					'compress space' => array(
						'pattern' => ' +',
						'replacement' => ' ',
						'order' => 200,
					),
				),
				'trim' => true,
			),
			'fields' => array(
				'title',
			),
		);

		$this->assertEqual($expected, $result);

		// Set a custom rule
		$result = $this->ArticleCustom->returnSettings();
		$result = $result['ArticleCustom']['rules']['replace_after'];
		$expected = array(
			'all others' => array(
				'pattern' => '[^\w\- ]',
				'replacement' => ' ',
				'order' => 100,
			),
			'compress space' => array(
				'pattern' => ' +',
				'replacement' => ' ',
				'order' => 200,
			),
			'internal hyphens' => array(
				'pattern' => '(\w)-(\w)',
				'replacement' => '$1 $2',
				'order' => 150,
			),
			'custom rule' => array(
				'pattern' => '    ',
				'replacement' => 'MANY SPACES',
				'order' => 175,
			),
		);
		$this->assertEqual($expected, $result);

		// Test the new custom rule
		$result = $this->ArticleCustom->testIndexifyField('-hyphens at, front, and back- ');
		$expected = array('-hyphens at', 'front', 'and back-');
		$this->assertEqual($expected, $result);

		// Test the new custom rule with hyphens in the middle
		$result = $this->ArticleCustom->testIndexifyField('-hyphens-in-the-middle-');
		$expected = array('-hyphens in the middle-');
		$this->assertEqual($expected, $result);

        // Test a word with hyphen and a space at beginning
        $result = $this->ArticleCustom->testIndexifyField(' -space-and-hyphen');
        $expected = array('-space and hyphen');
        $this->assertEqual($expected, $result);

		// Test a custom rule that should only work if rule sorting is working
        $result = $this->ArticleCustom->testIndexifyField('x    x');
        $expected = array('xMANY SPACESx');
        $this->assertEqual($expected, $result);
	}

	function endTest() {
		unset($this->Article);
		unset($this->ArticleCustom);
		ClassRegistry::flush();
	}

}
