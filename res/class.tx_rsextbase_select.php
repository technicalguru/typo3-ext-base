<?php 
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Ralph Schuster <typo3@ralph-schuster.eu>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once(t3lib_extMgm::extPath('jrt').'res/class.tx_jrt_pibase.php');

class tx_rsextbase_select extends tx_rsextbase_pibase {
	
	var $cObj;
	var $extKey = 'rsextbase';

	/**
	 * Main function.
	 * @param string $content
	 * @param array $conf
	 */
	function select($content, $conf) {
		$content = '';
		
		// Prepare config
		$this->config = $conf;
		$this->pi_USER_INT_obj = 0;
		$this->local_cObj = t3lib_div::makeInstance("tslib_cObj");
		$this->local_cObj->setCurrentVal($GLOBALS["TSFE"]->id);
		$this->id = $GLOBALS["TSFE"]->id;
		$this->createDatabaseObject();
		
		if ($this->config['multiselect']) $this->checkMultiSelectScripts();
		
		// Prepare value
		$this->valueArr = $this->cObj->data;
		$fieldname = $this->config['field'];
		
		// What is the selection class?
		$obj = $this->getCustomizedObject('select', $fieldname, 'selectionClass');
		
		// Get options
		$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'SelectOptions'; 
		if (method_exists($obj, $funcName)) {
			$options = $obj->$funcName($this, $fieldname, $this->valueArr);
		} else if (method_exists($this, $funcName)) {
			$options = $this->$funcName($this, $fieldname, $this->valueArr);
		} else {
			$funcName = 'getSelectOptions';
			if (method_exists($obj, $funcName)) {
				$options = $obj->$funcName($this, $fieldname, $this->valueArr);
			} else if (method_exists($this, $funcName)) {
				$options = $this->$funcName($this, $fieldname, $this->valueArr);
			} else {
				$options = $this->getOptions();
			}
		}
		
		// prepare selected value
		$selectedValue = $this->cObj->data[$fieldname];
		$funcName = 'is'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'OptionSelected'; 
		
		// Render options
		foreach ($options AS $option) {
			
			// Is option selected?
			$isSelected = 0;
			
			// check if we already have selection information
			if (isset($option['_is_selected'])) {
				$isSelected = $option['_is_selected'];
			} else {
				// Call a method if possible
				if (method_exists($obj, $funcName)) {
					$isSelected = $obj->$funcName($this, $fieldname, $this->valueArr, $option);
				} else if (method_exists($this, $funcName)) {
					$isSelected = $this->$funcName($this, $fieldname, $this->valueArr, $option);
				} else {
					// Last option
					if ($this->config['multiselect']) {
						$isSelected = t3lib_div::inList($selectedValue, $option[$this->config['table.']['valueField']]);
					} else {
						$isSelected = $selectedValue === $option[$this->config['table.']['valueField']]; 
					}
				}
			}
			
			// Render
			if ($isSelected) {
				$content .= $this->renderSelected($option)."\n";
			} else {
				$content .= $this->renderDefault($option)."\n";
			}
		}
		
		// Wrap it
		return $this->local_cObj->wrap($content, $this->config['wrap']);
	}
	
	/**
	 * Returns database records for configured table.
	 */
	function getOptions() {
		$rc = array();
		$conf = $this->config['table.'];
		$table = $conf['name'];
		
		if (is_array($conf)) {
			$query = $this->local_cObj->getQuery($table, $conf, TRUE);
			return $this->db->selectRecords($query['SELECT'], $query['FROM'], $query['WHERE'], $query['ORDERBY']);
		}
		
		$rc = array();
		$options = $this->config['options.'];
		if (is_array($options)) {
			foreach ($options AS $uid => $title) {
				$rc[] = array('uid' => $uid, 'title' => $title);
			}
		}
		
		return $rc;
	}
	
	/**
	 * Renders the given option as unselected option (default)
	 * @param array $option
	 */
	function renderDefault($option) {
		$conf = array($this->config['default'], $this->config['default.']);
		return $this->invokeCObject($this->field, $conf, $option, $option[$this->config['table.']['valueField']]);
	}
		
	/**
	 * Renders the given option as selected option
	 * @param array $option
	 */
	function renderSelected($option) {
		$conf = array($this->config['selected'], $this->config['selected.']);
		return $this->invokeCObject($this->field, $conf, $option, $option[$this->config['table.']['valueField']]);
	}
	
	/**
	 * Check if a script for applying multiselects was already produced.
	 */
	function checkMultiSelectScripts() {
		if (!$this->config['multiselect']) return;
		if (!$GLOBALS['RSEXTBASE']['Multiselect']['initScript']) {
			$template = $this->getSubpart($this->cObj->fileResource($this->config['multiselectTemplate']), 'INIT_SCRIPT');
			$template = $this->fillTemplate($template, 'multiselect', array());
			$GLOBALS['RSEXTBASE']['Multiselect']['initScript'] = $template;
			$GLOBALS['TSFE']->additionalHeaderData['rsextbase'] = $template;
		}
	}
}

?>