<?php
/**
 * Takes an element ID finds it's settings, returns either raw data or markup
 * to be used in the requesting app
 *
 * @package diy.org.cashmusic
 * @author CASH Music
 * @link http://cashmusic.org/
 *
 * Copyright (c) 2011, CASH Music
 * Licensed under the Affero General Public License version 3.
 * See http://www.gnu.org/licenses/agpl-3.0.html
 *
 **/
class ElementPlant extends PlantBase {
	protected $elements_array=array();
	protected $typenames_array=array();
	
	public function __construct($request_type,$request) {
		$this->request_type = 'element';
		$this->plantPrep($request_type,$request);
		$this->buildElementsArray();
	}
	
	public function processRequest() {
		if ($this->action) {
			switch ($this->action) {
				case 'addelement':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('name','type','options_data','user_id')) return $this->sessionGetLastResponse();
					$result = $this->addElement($this->request['name'],$this->request['type'],$this->request['options_data'],$this->request['user_id']);
					if ($result) {
						return $this->pushSuccess(array('element_id' => $result),'success. element id included in payload');
					} else {
						return $this->pushFailure('there was an error adding the element');
					}
					break;
				case 'editelement':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('element_id','name','options_data')) return $this->sessionGetLastResponse();
					$result = $this->editElement($this->request['element_id'],$this->request['name'],$this->request['options_data']);
					if ($result) {
						return $this->pushSuccess($this->getElement($result),'success. element included in payload');
					} else {
						return $this->pushFailure('there was an error editing the element');
					}
					break;
				case 'getelement':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('element_id')) return $this->sessionGetLastResponse();
						$result = $this->getElement($this->request['element_id']);
						if ($result) {
							return $this->pushSuccess($result,'success. element included in payload');
						} else {
							return $this->pushFailure('there was an error retrieving the element');
						}
					break;
				case 'deleteelement':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('element_id')) return $this->sessionGetLastResponse();
						$result = $this->deleteElement($this->request['element_id']);
						if ($result) {
							return $this->pushSuccess($result,'success. deleted');
						} else {
							return $this->pushFailure('there was an error deleting the element');
						}
					break;
				case 'getelementsforuser':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('user_id')) return $this->sessionGetLastResponse();
						$result = $this->getElementsForUser($this->request['user_id']);
						if ($result) {
							return $this->pushSuccess($result,'success. element(s) array included in payload');
						} else {
							return $this->pushFailure('no elements were found or there was an error retrieving the elements');
						}
					break;
				case 'getmarkup':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					if (!$this->requireParameters('element_id')) return $this->sessionGetLastResponse();
					$result = $this->getElementMarkup($this->request['element_id'],$this->request['status_uid']);
					if ($result) {
						return $this->pushSuccess($result,'success. markup in the payload');
					} else {
						return $this->pushFailure('markup not found');
					}
					break;
				case 'getsupportedtypes':
					if (!$this->checkRequestMethodFor('direct')) return $this->sessionGetLastResponse();
					$result = $this->getSupportedTypes();
					if ($result) {
						return $this->pushSuccess($result,'success. types array in the payload');
					} else {
						return $this->pushFailure('there was a problem getting the array');
					}
					break;
				default:
					return $this->response->pushResponse(
						400,$this->request_type,$this->action,
						$this->request,
						'unknown action'
					);
			}
		} else {
			return $this->response->pushResponse(
				400,
				$this->request_type,
				$this->action,
				$this->request,
				'no action specified'
			);
		}
	}
	
	/**
	 * Builds an associative array of all Element class files in /classes/elements/
	 * stored as $this->elements_array and used to include proper markup in getElementMarkup()
	 *
	 * @return void
	 */protected function buildElementsArray() {
		if ($elements_dir = opendir(CASH_PLATFORM_ROOT.'/classes/elements/')) {
			while (false !== ($file = readdir($elements_dir))) {
				if (substr($file,0,1) != "." && !is_dir($file)) {
					$tmpKey = strtolower(substr_replace($file, '', -4));
					$this->elements_array["$tmpKey"] = $file;
				}
			}
			closedir($elements_dir);
		}
	}

	public function buildTypeNamesArray() {
		if ($elements_dir = opendir(CASH_PLATFORM_ROOT.'/classes/elements/')) {
			while (false !== ($file = readdir($elements_dir))) {
				if (substr($file,0,1) != "." && !is_dir($file)) {
					$element_object_type = substr_replace($file, '', -4);
					$tmpKey = strtolower($element_object_type);
					include(CASH_PLATFORM_ROOT.'/classes/elements/'.$file);
					
					// Would rather do this with $element_object_type::type but that requires 5.3.0+
					// Any ideas?
					$this->typenames_array["$tmpKey"] = constant($element_object_type . '::name');
				}
			}
			closedir($elements_dir);
		}
	}

	public function getElement($element_id) {
		$result = $this->db->getData(
			'elements',
			'id,name,type,user_id,options',
			array(
				"id" => array(
					"condition" => "=",
					"value" => $element_id
				)
			)
		);
		if ($result) {
			$the_element = array(
				'id' => $result[0]['id'],
				'name' => $result[0]['name'],
				'type' => $result[0]['type'],
				'user_id' => $result[0]['user_id'],
				'options' => json_decode($result[0]['options'])
			);
			return $the_element;
		} else {
			return false;
		}
	}
	
	public function getElementsForUser($user_id) {
		$result = $this->db->getData(
			'elements',
			'*',
			array(
				"user_id" => array(
					"condition" => "=",
					"value" => $user_id
				)
			)
		);
		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	public function getSupportedTypes() {
		return array_keys($this->elements_array);
	}

	public function getElementMarkup($element_id,$status_uid) {
		$element = $this->getElement($element_id);
		$element_type = $element['type'];
		$element_options = $element['options'];
		if ($element_type) {
			$for_include = CASH_PLATFORM_ROOT.'/classes/elements/'.$this->elements_array[$element_type];
			if (file_exists($for_include)) {
				include($for_include);
				$element_object_type = substr_replace($this->elements_array[$element_type], '', -4);
				$element_object = new $element_object_type($status_uid,$element_options);
				return $element_object->getMarkup();
			}
		} else {
			return false;
		}
	}

	public function addElement($name,$type,$options_data,$user_id=0) {
		$options_data = json_encode($options_data);
		$result = $this->db->setData(
			'elements',
			array(
				'name' => $name,
				'type' => $type,
				'options' => $options_data,
				'user_id' => $user_id
			)
		);
		if ($result) { 
			return $result;
		} else {
			return false;
		}
	}
	
	public function editElement($element_id,$name,$options_data) {
		$options_data = json_encode($options_data);
		$result = $this->db->setData(
			'elements',
			array(
				'name' => $name,
				'options' => $options_data,
			),
			array(
				'id' => array(
					'condition' => '=',
					'value' => $element_id
				)
			)
		);
		if ($result) { 
			return $result;
		} else {
			return false;
		}
	}

	public function deleteElement($element_id) {
		$result = $this->db->deleteData(
			'elements',
			array(
				'id' => array(
					'condition' => '=',
					'value' => $element_id
				)
			)
		);
		if ($result) { 
			return $result;
		} else {
			return false;
		}
	}

} // END class 
?>