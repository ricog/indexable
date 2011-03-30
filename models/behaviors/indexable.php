<?php
/**
 * Indexable behavior
 * 
 * Provides a highly customizable indexing engine with minimal keystrokes
 */

class IndexableBehavior extends ModelBehavior {

/**
 * Default settings for this behavior
 * 
 * @var array
 * @access protected
 */
	protected $_baseConfig = array(
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
	);

/**
 * The model object for this model's index
 */
	public $IndexModel = null;
/**
 * Setup the behavior
 *
 * @param object $Model instance of model
 * @param array $config array of configuration settings.
 * @return void
 * @access public
 */
	function setup(&$Model, $config = array()) {
		if (!is_array($config)) {
			$config = array($config);
		}
		$settings = Set::merge($this->_baseConfig, $config);

		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $settings;
		} else {
			$this->settings[$Model->alias] = Set::merge($this->settings[$Model->alias], (array)$settings);
		}

//		$indexModel = $this->Model->name . 'Index';

/*
		$this->Model->bindModel(array(
			'hasMany' => array(
				$indexModel => array(
					'className' => 'Indexable.IndexableIndex',
					'foreignKey' => 'model_id',
				),
			),
		));
*/
//		$this->IndexModel = $this->Model->$indexModel;
//		$this->IndexModel->bindModel(array('belongsTo' => array($this->Model->alias => array('foreignKey' => 'model_id'))));
//		$this->IndexModel->setSource(strtolower($this->Model->name) . '_index');
	}

	function afterSave(&$Model, $created = null) {
		$this->_indexRecord($Model, $Model->data[$Model->alias]);
	}
	
/**
 * Builds or rebuilds the entire index
 */	
	function buildIndex(&$Model, $offset = null, $limit = null) {
		$params = array();
		if ($offset) {
			$params['offset'] = $offset;
		}
		if ($limit) {
			$params['limit'] = $limit;
		}
		$Model->contain();
		$records = $Model->find('all', $params);
		foreach ($records as $record) {
			$this->_indexRecord($Model, $record[$Model->alias]);
		}

		return true;
	}

/**
 * Indexes a single record.
 */
	function _indexRecord(&$Model, $data) {
		$IndexModel = $Model->{$Model->alias . 'Index'};
		$fields = $this->settings[$Model->alias]['fields'];

		if (empty($data['id'])) {
			if (!empty($Model->id)) {
				$data['id'] = $Model->id;
			} else {
				//TODO
				echo 'Record id not found, cannot create an index record'; exit;
			}
		}
		// Remove any previous indexing for this record
		$IndexModel->contain();
		$IndexModel->deleteAll(array('model_id' => $data['id']));
		
		// Add new index records
		$records = array();
		foreach ($fields as $field) {
			$records = array_merge($records, $this->_indexifyField($Model, $data[$field]));
		} unset($field);

		if (!empty($records)) {
			foreach ($records as $record) {
				if (!empty($record['data'])) {
					$index = array(
						$IndexModel->alias => array(
							'model_id' => $data['id'],
							'data' => $record['data'],
							'pretty' => $record['pretty'],
						),
					);
					$IndexModel->create();
					$IndexModel->save($index);
				}
			}
		}
	}

/**
 * Processes a string based on the plugin rules and returns an array
 * @param string $string
 */
	function _indexifyField(&$Model, $string) {
		$rules = $this->settings[$Model->alias]['rules'];

		// Process preliminary replacements
		$string = $this->__processReplacements($rules['replace_before'], $string);

		// Divide the string into multiple strings
		if (!empty($rules['dividers'])) {
			$strings = preg_split('/[' . $rules['dividers'] . ']/', $string);
		} else {
			$strings = array($string);
		}

		// Process remaining replacements and trim strings
		foreach ($strings as $key => $value) {

			$strings[$key]  = array(
				'data' => $this->__processReplacements($rules['replace_after'], $value),
				'pretty' => $value,
			);
			if (!empty($rules['trim'])) {
				$strings[$key]['data'] = trim($strings[$key]['data']);
				$strings[$key]['pretty'] = trim($strings[$key]['pretty']);
			}

			// Convert string to lowercase
			$strings[$key]['data'] = strtolower($strings[$key]['data']);

		} unset($key); unset($value);
			
		return $strings;
	}

	function __processReplacements($rules, $string) {
		if (!empty($rules)) {
			$rules = Set::sort($rules, '{[\w ]+}.order', 'asc');
			foreach ($rules as $ruleKey => $rule) {
				$string = preg_replace('/' . $rule['pattern'] . '/', $rule['replacement'], $string);
			} unset($ruleKey); unset($rule);
		}
		return $string;
	}
	
/**
 * Filter Conditions method
 * 
 * Returns an array of search conditions based on a search string and searchFields
 */
	function filterConditions(&$Model, $search) {
		$searchType = $this->settings[$Model->alias]['searchType'];
		$searchFields = $this->settings[$Model->alias]['searchFields'];
		
		foreach ($searchFields as $fieldName) {
			if (!strstr($fieldName, '.')) {
				$fieldName = $Model->alias . '.' . $fieldName;
			}
			if ($searchType == 'partial') {
				$conditions[$fieldName . ' LIKE'] = '%' . $search . '%';
			} elseif ($searchType == 'phrase') {
				$conditions['AND'] = array(
					$fieldName . ' LIKE' => '%' . $search . '%',
					$fieldName . ' REGEXP' => '[[:<:]]' . $search . '[[:>:]]',
				);
			} else {
				$conditions[$fieldName] = $search;
			}
		}
		return array('OR' => $conditions);
	}
}
