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

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_database.php');
require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_ajaxbase.php');
require_once(t3lib_extMgm::extPath('t3jquery').'class.tx_t3jquery.php');

class tx_rsextbase_pibase extends tslib_pibase {

	var $fieldErrors;
	var $config;
	var $id;
	var $db;
	var $ajax;
	var $local_cObj;
	var $extKey = 'rsextbase';
	var $strippedExtKey;
	
	/**
	 * Always call this function before starting
	 * @param $conf configuration
	 */
	function init($conf) {
		if (isset($this->config)) return;
		$this->conf = $conf;
		
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;
		$this->pi_initPIflexForm();
		$this->pi_setPiVarDefaults();
		$this->local_cObj = t3lib_div::makeInstance("tslib_cObj");
		$this->local_cObj->setCurrentVal($GLOBALS["TSFE"]->id);
		$this->id = $GLOBALS["TSFE"]->id;
		$this->fieldErrors = array();
		if (!isset($this->strippedExtKey)) $this->strippedExtKey = str_replace('_', '', $this->extKey);

		// Configuration
		$this->config = $this->conf;
		$this->setConfiguration('mode');
		$this->setConfiguration('ajaxTypeNum');
		$this->setConfiguration('templateFile');
		$this->setConfiguration('tinyMceTemplate');
		
		// User related config
		$this->setConfiguration('userFolder');
		$this->setConfiguration('adminGroups');
		$this->setConfiguration('maxIdleTime');
		
		// Email Config
		$this->setConfiguration('disableEmails');
		$this->setConfiguration('fromEmailAddress');
		$this->setConfiguration('fromEmailName');
		$this->setConfiguration('replyEmailAddress');
		$this->setConfiguration('emailDebugCopy');
		$this->setConfiguration('verboseEmail');
		
		// General HTML config
		$this->setConfiguration('dateFormat');
		$this->setConfiguration('timeFormat');
		$this->setConfiguration('datetimeFormat');
		
		// Page IDs
		$this->setConfiguration('viewProfilePID');
		
		// Template loading
		$this->loadTemplate('templateFile', 'EXT:'.$this->extKey.'/'.$this->relPath.'/template.tmpl');

		// Database
		$this->createDatabaseObject();
		
		// jQuery support
		tx_t3jquery::addJqJS();
	}

	/**
	 * Creates the database object
	 */
	function createDatabaseObject() {
		$this->db = t3lib_div::makeInstance('tx_rsextbase_database');
		$this->db->init(&$this->config);
	}
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param       string          $content: The PlugIn content
	 * @param       array           $conf: The PlugIn configuration
	 * @return      The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->init($conf);
		
		// We don't care about the type and just route the request
		$content = '';
		
		if ($this->isAjaxRequest()) {
			return $this->dispatchAjax();
		} else {
			if (method_exists($this, 'getPluginContent')) {
				return $this->getPluginContent();
			} else {
				return $this->pi_wrapInBaseClass("Invalid Plugin Usage: No HTML output defined for ".$this->prefixId."::getPluginContent()");
			}
		}
	}
	
	/***************************************************************
	 * AJAX Handling
	 ***************************************************************/
	
	/**
	 * Call this function if you want to answer AJAX requests.
	 * @param $conf configuration
	 */
	function initAjax($conf) {
		$this->init($conf);
		
		$ajaxName = 'tx_'.$this->strippedExtKey.'_ajax_'.$this->relPath;
		$ajaxPath = t3lib_extMgm::extPath($this->extKey).$this->relPath.'/class.'.$ajaxName.'.php';
		if (!file_exists($ajaxPath)) return 0;
		require_once($ajaxPath);
		$this->ajax = t3lib_div::makeInstance($ajaxName);
		$this->ajax->init($this);
		return 1;
	}
	
	/**
	 * Returns true when the current call is an AJAX call (checks typeNum).
	 */
	function isAjaxRequest() {
		return ($this->config['ajaxTypeNum'] != 0) && ($this->config['ajaxTypeNum'] == $GLOBALS['TSFE']->type);
	}
	
	/**
	 * If an AJAX call was dipatched, this method will be called to take care of it.
	 * The method will forward processing to corresponding plugin.
	 * @return JSON response
	 */
	function dispatchAjax() {
		$request = $this->getJsonRequest();
		if (!$request) return tx_rsextbase_ajaxbase::error($this->pi_getLL('err_invalid_request'));
		
		// Load the class
		require_once($request['classPath']);
		
		// Instantiate class
		$obj = t3lib_div::makeInstance($request['className']);
		$obj->cObj = $this->cObj;
		
		// Configure class with AJAX
		$conf = $GLOBALS['TSFE']->tmpl->setup['plugin.'][$request['className'].'.'];
		$rc = $obj->initAjax($conf);
		if (!$rc) tx_rsextbase_ajaxbase::error($this->pi_getLL('err_no_ajax'));
		
		// Method dispatch is done by AJAX class itself
		return $obj->ajax->main($request, $this);
	}
	
	/**
	 * Creates the JSON request object from GET/POST vars.
	 * @return JSON request object or 0 if JSON request was invalid.
	 */
	function getJsonRequest() {
		$method = t3lib_div::_GP('method');
		$mDef = explode('::', $method, 3);
		
		// No class, cannot dispatch
		if (count($mDef) < 3) return 0;
		
		// Get class definitions (class name and path of file)
		$classDef = $this->getClassDef($mDef);
		if (!$classDef) return 0;
		
		// extract the params by iterating over all GP vars
		$params = array();
		$gp = t3lib_div::_POST();
		foreach ($gp AS $key => $value) {
			if ($key == 'method') continue;
			if ($key == 'id') continue;
			if ($key == 'rid') continue;
			if ($key == 'type') continue;
			$params[$key] = $value;
		}
		$gp = t3lib_div::_GET();
		foreach ($gp AS $key => $value) {
			if ($key == 'method') continue;
			if ($key == 'id') continue;
			if ($key == 'rid') continue;
			if ($key == 'type') continue;
			if (isset($params[$key])) continue;
			$params[$key] = $value;
		}
		
		$rc = array(
			'classPath' => $classDef[0],
			'className' => $classDef[1],
			'method'    => $mDef[2],
			'params'    => $params,
			'id'        => t3lib_div::_GP('rid'),
		);
		return $rc;
	}
	
	/**
	 * Returns the class definition of given plugin.
	 * The method already checks if extension and plugin exist and returns 0 in case of problems.
	 * @param $mDef method definition, e.g. ('tx_my_extension', 'pi13', 'myMethod')
	 * @return array: 'className' - name of class, 'classPath' - location of class file for inclusion
	 */
	function getClassDef($mDef) {
		// Build the class name
		$className = 'tx_'.str_replace('_', '', $mDef[0]).'_'.$mDef[1];
		
		// Build the class path
		$classPath = t3lib_extMgm::extPath($mDef[0]).$mDef[1].'/class.'.$className.'.php';
		if (!file_exists($classPath)) return 0;
		
		return array($classPath, $className);
	}

	function getTestHtml(&$pi, $methodName, $ajaxTypeNum) {
		$url = $pi->pi_getPageLink($pi->id, '', array('type' => $ajaxTypeNum));
			
		$content = '<script type="text/javascript">';
		$content .= '$(document).ready(function() {';
		$content .=
					'	$("#testlink").click(function(){'.
					'		$.ajax({ url: "'.$url.'", data: { "method": "'.$pi->extKey.'::'.$pi->relPath.'::'.$methodName.'", "time": "2pm", "rid" : 56 }, success: function(data, textStatus, request) {'.
            		'			$("#ajaxcontent").fadeOut("fast",function(){'.
                    '				$("#ajaxcontent").html(data.result).fadeIn("normal");'.
                    '       	});'.
                    '       }, dataType: "json"});'.
					'	}); '.
					'});';

		$content .= '</script>';
		$content .= "<p>Dies ist das AJAX Dispatch Plugin. Klicken Sie auf den Test-Link, um zu ".
						"&uuml;berpr&uuml;fen, ob AJAX korrekt konfiguriert wurde.</p>\n".
                        "<p><a id=\"testlink\" href=\"#\">AJAX testen...</a></p>".
                        "<p id=\"ajaxcontent\" style=\"border: #c0c0c0 solid 1px; background-color: #F3F781; padding: 5px;\">Text erscheint hier</p>";
		return $pi->pi_wrapInBaseClass($content);
	}

	/***************************************************************
	 * Configuration Handling
	 ***************************************************************/
	
	/**
	 * Helper function to get a configuration variable loaded.
	 * A variable will be checked in following order:
	 *   1. definition by Flexform
	 *   2. definition by TS configuration in root, e.g. plugin.tx_ext_pi13.varName
	 *   3. definition by global TS config, e.g. plugin.tx_ext_pi13.config.varName
	 * The first definition wins.
	 * @param $varName name of variable to be set.
	 */
	function setConfiguration($varName) {
		$flexValue = $this->pi_getFFvalue( $this->cObj->data['pi_flexform'], $varName, 'sDEF' );
		$configValue = $this->conf['config.'][$varName];
		$tsValue = $this->conf[$varName];
		
		if ($flexValue) {
			$this->config[$varName] = $flexValue;
		} else if ($tsValue) {
			$this->config[$varName] = $tsValue;
		} else {
			$this->config[$varName] = $configValue;
		}
	}
	
	/**
	 * Loads a file resource into config variable.
	 * @param string $varName name of config var
	 * @param string $default default value
	 */
	function loadTemplate($varName, $default = '') {
		$this->setConfiguration($varName);
		if (!$this->config[$varName]) $this->config[$varName] = $default;
		
		if ($this->config[$varName]) {
			$this->config[$varName] = $this->cObj->fileResource($this->config[$varName]);
		}
		return $this->config[$varName];
	}

	/************************************************************************************
	 GET/POST HANDLING
	 ************************************************************************************/
	/**
	 * Returns the name of the GET/POST param for a field in specific mode of operation.
	 * Parameters can be configured using [PLUGINCONF].GPvar.fieldName = paramName.
	 * If no such configuration was done, then the default tx_ext_piXX[fieldName] will
	 * be returned.
	 * @param $mode mode of operation
	 * @param $param name of field
	 * @return name of GET/POST parameter
	 */
	function getGPvarName($mode, $param) {
		$paramName = $this->config[$mode.'.']['GPvar.'][$param];
		if (!$paramName) $paramName = $this->prefixId.'['.$param.']';
		return $paramName;
	}

	/**
	 * Returns the value of the GET/POST param for a field in specific mode of operation.
	 * Parameter definition see getGPvarName().
	 * @param $mode mode of operation
	 * @param $param name of field
	 * @param $default default value if not set
	 * @return current value of GET/POST parameter
	 */
	function getGPvar($mode, $param, $default = NULL) {
		if (!$param) throw new Exception("param ist NULL");
		
		$paramName = $this->getGPvarName($mode, $param);
		
		// As we can alter piVars, they will be taken directly
		if ($paramName == $this->prefixId."[$param]") {
			//echo "Returning $param directly = ".$this->piVars[$param]."<br/>";
			if (isset($this->piVars[$param]) {
				return $this->piVars[$param];
			}
			return $default;
		}
		
		//echo "Returning $param from GP method => $paramName<br/>";
		
		// Parse the name
		$matches = array();
		if (preg_match('/^([^\[]+)/', $paramName, $matches)) {
			$gp = t3lib_div::_GP($matches[1]);
			$offset = strlen($matches[1]);
			$matches = array();
			while ($gp && preg_match('/\[([^\]]+)\]/', $paramName, $matches, 0, $offset)) {
				$gp = $gp[$matches[1]];
				$offset += strlen($matches[1])+2;
			}
			if (isset($gp) && !is_array($gp)) $gp = trim($gp);
			if (isset($gp)) return $gp;
		}
		return $default;
	}

	/**
	 * Sets the value of the GET/POST param for a field in specific mode of operation.
	 * Parameter definition see getGPvarName(). The value will only be set if
	 * the parameter is in scope of prefixId (default).
	 * @param $mode mode of operation
	 * @param $param name of field
	 */
	function setGPvar($mode, $param, $value) {
		$paramName = $this->getGPvarName($mode, $param);
		if ($paramName == $this->prefixId."[$param]") {
			//echo "Setting $param to $value<br/>";
			$this->piVars[$param] = $value;
		}
	}
	
	/**
	 * Process GET/POST and set array values accordingly
	 * Enter description here ...
	 * @param $mode mode of operation
	 * @param $valueArr to be set
	 * @return 1 if processing was successful and value array can be saved. If
	 *         function returns 0, array will contain set values but they must not be saved.
	 */
	function processGPvars($mode, &$valueArr) {
		
		// Remember whether result can be saved
		$result = TRUE;

		// TODO: This is to be checked where we get the fieldnames from to be processed
		$fields = explode(',', $this->config[$mode.'.']['fieldnames']);
		foreach ($fields AS $field) {
			// Ignore field if it is viewed only
			$type = $this->config[$mode.'.']['type.'][$field];
			if (($type == 'view') || ($type == 'ignore')) continue;

			// Get the value submitted
			$value = $this->getGPvar($mode, $field);

			// if field was not delivered
			if (!isset($value) && ($type != 'form_checkbox') && ($type != 'form_simple_checkbox') && ($type != 'form_image')) continue;

			// Exception for file types
			if ($type == 'form_image') {
				$value = array(
					'name' => $GLOBALS['_FILES'][$this->prefixId]['name'][$field],
					'type' => $GLOBALS['_FILES'][$this->prefixId]['type'][$field],
					'tmp_name' => $GLOBALS['_FILES'][$this->prefixId]['tmp_name'][$field],
					'size' => $GLOBALS['_FILES'][$this->prefixId]['size'][$field],
					'error' => $GLOBALS['_FILES'][$this->prefixId]['error'][$field],
				);
			} else if (($type == 'form_checkbox') || ($type == 'form_simple_checkbox')) {
				// Explicitely set value to null if not delivered (HTML specific)
				if (!$value) $value = 0;
			} else if ($type == 'form_date') {
				// Parse dates
				if ($value) {
					$tArr = strptime($value, "%d/%m/%Y");
					if ($tArr) {
						$time = mktime($tArr['tm_hour'],$tArr['tm_min'],$tArr['tm_sec'],
						        $tArr['tm_mon']+1,$tArr['tm_mday'],$tArr['tm_year']+1900);
						$value = $time;
						// Special handling of 0 (no date given)
						if ($value < 3600*24) $value = 0;
					} else {
						$this->fieldErrors[$field] = $this->pi_getLL($mode.'_err_date_format');
						continue;
					}
				}
			}

			// Find the object for processing
			$objName = $this->config[$mode.'.'][$field.'.']['processingClass'];
			if ($objName) {
				if (!$OBJECTS[$objName]) $OBJECTS[$objName] = t3lib_div::makeInstance($objName);
				$obj = $OBJECTS[$objName];
			} else {
				$obj = $this;
			}

			// Call the method for processing
			$funcName = 'process'.str_replace(' ','',ucwords(str_replace('_',' ',$field))).'GPvar';
			if (method_exists($obj, $funcName)) {
				// processThisAndThatGPvar()
				$obj->$funcName($this, $mode, $value, $valueArr);
			} else if (method_exists($obj, '_generalProcessGPvars')) {
				$obj->_generalProcessGPvars($this, $mode, $field, $value, $valueArr);
			} else {
				// Set the value directly
				$this->_generalProcessGPvars($this, $mode, $field, $value, $valueArr);
			}
		}

		// return whether processing was successful
		if (!count($this->fieldErrors)) {
			return 1;
		}
		
		return 0;
	}
	
	/**
	 * Validates fieldname not to be empty
	 * @param string $mode
	 * @param string $fieldname
	 */
	function validateEmpty($mode, $fieldname) {
		$value = $this->getGPvar($mode, $fieldname);
		if (!$value) {
			$this->fieldErrors[$fieldname] = $this->getErrorText($fieldname.'_empty', $mode);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Validates a date
	 * @param string $mode
	 * @param string $fieldname
	 * @param integer $min
	 * @param integer $max
	 */
	function validateDate($mode, $fieldname, $min = 0, $max = 0) {
		// Check date format
		$time = $this->getGPvar($mode, $fieldname);
		// First parse the date and write it back into the piVar array !
		$time = $this->parseDate($time);
		$this->setGPvar($mode, $fieldname, $time);
		
		if (!$time) {
			// Formatting problem
			$this->fieldErrors[$fieldname] = $this->getErrorText($fieldname.'_invalid_format', $mode);
			return TRUE;
		} else if ($min && ($time < $min)) {
			$this->fieldErrors[$fieldname] = $this->getErrorText($fieldname.'_min_exceeded', $mode);
			return TRUE;
		} else if ($max && ($time > $max)) {
			$this->fieldErrors[$fieldname] = $this->getErrorText($fieldname.'_max_exceeded', $mode);
			return TRUE;
		}
		return FALSE;
	}

	
	/************************************************************************************
	 TEMPLATE HANDLING
	 ************************************************************************************/
	/**
	 * Returns the template subpart of given name from current main template.
	 * @param $name name of subpart
	 */
	function getSubTemplate($name) {
		return $this->getSubpart($this->config['templateFile'], $name);
	}
	
	/**
	 * Main function to work on templates.
	 * @param template - template
	 * @param mode - mode of operation
	 * @param valueArr - array of values to be replaces in template
	 * @return template replaced with all values according to configuration of mode
	 */
	function fillTemplate($template, $mode, $valueArr) {
		$singleMarkers = array();
		$subpartMarkers = array();
		$wrapped = array();
		$this->getMarkers($template,$singleMarkers,$subpartMarkers,$wrapped, $mode, $valueArr);
		return $this->substituteMarkerArray($template, $singleMarkers,$subpartMarkers,$wrapped, $mode);
	}

	/**
	 * Helper function to instantiate (and buffer) custom marker handling classes
	 * @param string $mode mode of operation
	 * @param string $fieldname field name
	 * @param string $classType configuration class type to look for
	 */
	function getCustomizedObject($mode, $fieldname, $classType = 'markerClass') {
		if (!$classType) $classType = 'markerClass';
		
		$objName = strtolower($this->config[$mode.'.'][$fieldname.'.'][$classType]);
		$objName2 = strtolower($this->config[$fieldname.'.'][$classType]);
		$objName3 = strtolower($this->config[$classType]);
		if ($objName) {
			if (!$this->OBJECTS[$objName]) $this->OBJECTS[$objName] = t3lib_div::makeInstance($objName);
			$obj = $this->OBJECTS[$objName];
		} else if ($objName2) {
			if (!$this->OBJECTS[$objName2]) $this->OBJECTS[$objName2] = t3lib_div::makeInstance($objName2);
			$obj = $this->OBJECTS[$objName2];
		} else if ($objName3) {
			if (!$this->OBJECTS[$objName3]) $this->OBJECTS[$objName3] = t3lib_div::makeInstance($objName3);
			$obj = $this->OBJECTS[$objName3];
		} else {
			$obj = $this;
		}
		return $obj;
	}

	/**
	 * Main function to retrieve all markers and their values.
	 * This function will not replace anything but just fill fill marker arrays with correct values.
	 * @param template - the template to be parsed
	 * @param singleMarkers - array that will be filled with values to be replaced for single markers
	 * @param subpartMarkers - array that will be filled with replacements for section markers
	 * @param wrapped - not being used
	 * @param mode - mode of operation
	 * @param valuArr - array of values being used for replacement
	 */
	function getMarkers(&$template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$conf = $this->config[$mode.'.'];
		$rc = array();
		$subMarkerCount = 0;

		// find all marker <!-- ###SUBPART_MARKER### begin/end --> and ###SINGLE_MARKER###
		preg_match_all('!(\<\!--+\s*)?###([A-Z0-9_-]*)###\s*(([a-zA-Z0-9]*) *--+>)?!is', $template, $match);
		$markerLevel = 0;
		foreach ($match[2] as $idx => $marker) {
			$fieldname = strtolower($marker);
			$markerType = strtolower($match[4][$idx]);
			$isSubpartMarker = $markerType ? 1 : 0;
			
			if ($isSubpartMarker) {
				// Adjust marker level
				if ($markerType == 'begin') $markerLevel++;
				if ($markerType == 'end') $markerLevel--;
					
				// Handle only top level markers
				if ($markerLevel != 1) continue;
					
				// Do only BEGIN markers
				if ($markerType != 'begin') continue;
					
				if ($this->isVisible($fieldname, $mode, $valueArr)) {
					$obj = $this->getCustomizedObject($mode, $fieldname);

					$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'Markers';
					$subpartTemplate = $this->getSubpart($template, $marker);
					if (method_exists($obj, $funcName)) {
						// e.g. getThisAndThatMarkers (THIS_AND_THAT)
						$obj->$funcName($this, $subpartTemplate, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					} else if (method_exists($this, $funcName)) {
						// e.g. getThisAndThatMarkers (THIS_AND_THAT)
						$this->$funcName($this, $subpartTemplate, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					} else if (method_exists($obj, '_getFieldMarkers')) {
						// call a general function to take care of this value
						$obj->_getFieldMarkers($this, $subpartTemplate, $marker, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					} else if (method_exists($this, '_getFieldMarkers')) {
						// call a general function to take care of this value
						$this->_getFieldMarkers($this, $subpartTemplate, $marker, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					}

					// Ensure that subpart marker is set, otherwise set it empty
					if (!isset($subpartMarkers['###'.$marker.'###'])) {
						$subpartMarkers['###'.strtoupper($fieldname).'###'] = '';
					}
					$subMarkerCount++;
				} else {
					$subpartMarkers['###'.strtoupper($fieldname).'###'] = '';
				}
			} else {
				// Single Markers only in marker level 0
				if ($markerLevel != 0) continue;
				
				// Beware: markers already set by previous methods will be ignored
				if (isset($singleMarkers['###'.$marker.'###'])) continue;
					
				if ($this->isVisible($fieldname, $mode, $valueArr)) {
					$obj = $this->getCustomizedObject($mode, $fieldname);

					$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'Marker';
					if (method_exists($obj, $funcName)) {
						// e.g. getThisAndThatMarker (THIS_AND_THAT)
						$obj->$funcName($this, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					} else if (method_exists($this, $funcName)) {
						// e.g. getThisAndThatMarker (THIS_AND_THAT)
						$this->$funcName($this, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
					} else {
						// Populated by values from $valueArr // stdWrapped
						$field = strtolower($marker);
						$singleMarkers['###'.$marker.'###'] = $this->getFieldMarkerValue($field, $mode, $valueArr);
					}
				} else {
					$singleMarkers['###'.$marker.'###'] = '';
				}

				// make error handling if marker was set and continue on next marker
				if (isset($singleMarkers['###'.$marker.'###'])) {
					$singleMarkers['###'.$marker.'###'] = $this->wrapError($mode, $fieldname, $singleMarkers['###'.$marker.'###']);
					continue;
				}

			}
		}

		// Special markers
		$singleMarkers['###SUB_MARKER_COUNT###'] = $subMarkerCount;
		$singleMarkers['###PAGE_URI###'] = $this->pi_getPageLink($this->id, '');
		$singleMarkers['###TSFE_ID###'] = $this->getFieldMarkerValue('tsfe_id', $mode, array('tsfe_id'=> $this->id));
		$singleMarkers['###EXTPATH###'] = t3lib_extMgm::siteRelPath($this->extKey);
		$singleMarkers['###RSEXTBASEPATH###'] = t3lib_extMgm::siteRelPath('rsextbase');
		$singleMarkers['###BASEURL###'] = $GLOBALS['TSFE']->tmpl->setup['config.']['baseURL'];
		
	}

	/**
	 * General function to replace a section by its content.
	 * This function is mainly a place holder will only set the marker value to 
	 * the content of the section template
	 * @param $caller
	 * @param $template
	 * @param $marker
	 * @param $singleMarkers
	 * @param $subpartMarkers
	 * @param $wrapped
	 * @param $mode
	 * @param $valueArr
	 */
	function _getFieldMarkers($caller, $template, $marker, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		// Just make a sub-call:
		$content = $this->fillTemplate($template, $mode, $valueArr);
		$subpartMarkers['###'.$marker.'###'] = $content;
	}

	/**
	 * Function that returns the correct language label.
	 * Locallang.xml must either contain <mode>_<fieldname> or just <fieldname> as key.
	 * @param $field
	 * @param $mode
	 * @param $valueArr
	 */
	function getLabel($field, $mode, $valueArr) {
		//if ($this->isVisible($field, $mode, $valueArr)) {
			$rc = $this->pi_getLL($mode.'_'.strtolower($field));
			if (!$rc) $rc = $this->pi_getLL(strtolower($field));
			if (!is_array($rc)) return $rc;
			return '';
		//}
		//return '';
	}

	/**
	 * Returns the LL text $mode_err_$key or err_$key.
	 * @param string $key
	 * @param string $mode
	 */
	function getErrorText($key, $mode) {
		$rc = $this->pi_getLL($mode.'_err_'.$key);
		if (!$rc) $rc = $this->pi_getLL('err_'.$key);
		return $rc;
	}
	
	/**
	 * This will wrap the value with the error message if an error message was found.
	 * Error messages are wrapped according to TS setup <mode>.<field>.error.
	 * If no such definition was made, <mode>.default.error will be used
	 * @param $mode
	 * @param $field
	 * @param $value
	 */
	function wrapError($mode, $field, $value) {
		// Do nothing if no field error is set
		if (!$this->fieldErrors[$field]) return $value;

		$data = array (
			'_error' => $this->fieldErrors[$field],
			'_value' => $value,
		);


		// Get the correct error config
		if ($this->config[$mode.'.'][$field.'.']['error.']) {
			$errConf1 = $this->config[$mode.'.'][$field.'.']['error'];
			$errConf2 = $this->config[$mode.'.'][$field.'.']['error.'];
		} else if ($this->config[$mode.'.']['default.']['error.']) {
			$errConf1 = $this->config[$mode.'.']['default.']['error'];
			$errConf2 = $this->config[$mode.'.']['default.']['error.'];
		} else if ($this->config['default.']['error.']) {
			$errConf1 = $this->config['default.']['error'];
			$errConf2 = $this->config['default.']['error.'];
		} else {
			$errConf1 = NULL;
			$errConf2 = NULL;
		}

		// Wrap it
		if ($errConf1) {
			$this->local_cObj->data = $data;
			$rc = $this->local_cObj->cObjGetSingle($errConf1, $errConf2);
		} else {
			$rc = $value.$error;
		}

		return $rc;
	}

	/**
	 * Returns true if there is an error for this field or at all.
	 * @param string $field
	 */
	function hasErrors($field = '') {
		if ($field) {
			return $this->fieldErrors[$field] ? 1 : 0;
		}
		return count($this->fieldErrors);
	}
	
	/**
	 * Will return the correct value according to TS setup for the field.
	 * Please note that the correct TS setup will be returns from method getWrapConfig()
	 * @param field - field name
	 * @param mode - mode of operation
	 * @param valuArr - array of values
	 * @param configField - field name to be used in TS setup
	 * @return value according to TS setup.
	 */
	function getFieldMarkerValue($field, $mode, $valueArr, $configField = '') {
		$conf = $this->config[$mode.'.'];
		$field = strtolower($field);
		if (!$configField) $configField = $field;
		$value = $valueArr[$field];

		// Special markers?
		$rc = $this->getSpecialFieldMarkerValue($field, $mode, $valueArr);
		if ($rc) return $rc;

		// Do we have a TS description?
		$valueConf = $this->getWrapConfig($field, $configField, $mode, $valueArr);
		$rc = $this->invokeCObject($field, $valueConf, $valueArr, $value);
				
		// There need to be made some replacements before returning
		return $this->injectStdWrapVariables($rc, $field, $mode, $valueArr, $configField);
	}

	/**
	 * Invokes the cObj rendering.
	 * @param array $configField name of field
	 * @param unknown_type $confArray array of 2 arrays containg the cObj config.
	 * @param unknown_type $valueArr array of values (data array)
	 * @param unknown_type $value value to be rendered
	 */
	function invokeCObject($configField, $confArray, $valueArr, $value) {
		if ($confArray) {
			// Use it
			$this->local_cObj->data = $valueArr;
			$this->local_cObj->data['_value'] = $value;
			$this->local_cObj->data['_field'] = $configField;
			$rc = $this->local_cObj->cObjGetSingle($confArray[0], $confArray[1]);
			
			// Check if we need to include CSS in header
			if ($confArray[1]['includeCSS.']) {
				foreach ($confArray[1]['includeCSS.'] AS $key => $value) {
					$GLOBALS['TSFE']->additionalHeaderData[$key] = '<link rel="stylesheet" type="text/css" href="'.
						$GLOBALS['TSFE']->tmpl->getFileName($value).
						'" media="all">';
				}
			}
		} else {
			// Just return the value from the array
			$rc = $value;
		}
		return $rc;
	}
	
	/**
	 * Makes a standard wrap if configured for this value as $field_stdWrap in $mode config.
	 * @param string $mode
	 * @param string $field
	 * @param string $value
	 */
	function stdWrap($mode, $field, $value) {
		$wrapConf = $this->config[$mode.'.'][$field.'_stdWrap.'];
		if (!$wrapConf) return $value;
		return $this->local_cObj->stdWrap($value, $wrapConf);
	}
	
	/**
	 * Returns labels, GET/POST variables and their values if fieldname is prefixed accordingly
	 * Enter description here ...
	 * @param string $field
	 * @param string $mode
	 * @param string $valueArr
	 */
	function getSpecialFieldMarkerValue($field, $mode, $valueArr) {
		if (preg_match('/^L_(.*)$/i', $field, $temp)) {
			// Labels are prefixed with 'L_'
			return $this->getLabel($field, $mode, $valueArr);
		} else if (preg_match('/^GPVAR_(.*)$/i', $field, $temp)) {
			// This is a special marker to be replaced by the GPVAR name
			$field = strtolower($temp[1]);
			return $this->getGPvarName($mode, $field);
		} else if (preg_match('/^GPVAL_(.*)$/i', $field, $temp)) {
			// This is a special marker to be replaced by the value of GET/POST variable
			$field = strtolower($temp[1]);
			return $this->getGPvar($mode, $field);
		}
	}
	
	/**
	 * This method will returns the correct TS setup configuration for this field.
	 * @param string $field - fieldname
	 * @param string $configField - fieldname in TS setup
	 * @param string $mode - mode of operation
	 * @param array $valueArr - array of values
	 * @return TS setup for field an mode of operation.
	 */
	function getWrapConfig($field, $configField, $mode, $valueArr) {
		$conf = $this->config[$mode.'.'];

		// Return the special config if available
		if ($conf[$configField.'.'] && $conf[$configField]) {
			return array($conf[$configField], $conf[$configField.'.']);
		};

		// Return default for type of the field
		$type = $conf['type.'][$configField];
		if ($type) {
			if ($conf['default.'][$type.'.']) {
				// Type Default for this mode
				return array($conf['default.'][$type], $conf['default.'][$type.'.']);
			} else if ($this->config['default.'][$type.'.']) {
				// General Type Default
				return array($this->config['default.'][$type], $this->config['default.'][$type.'.']);
			}
		}

		/*
		// Check if there is a default with same name
		if ($conf['default.'][$configField]) {
			return array($conf['default.'][$configField], $conf['default.'][$configFiel.'.']);
		}
		if ($this->config['default.'][$configField]) {
			return array($this->config['default.'][$configField], $this->config['default.'][$configFiel.'.']);
		}
		*/
		
		// Return default for this mode
		if ($conf['default.']['default']) {
			return array($conf['default.']['default'], $conf['default.']['default.']);
		}
		
		// Return default type for this mode
		$type = $conf['type.']['default'];
		if ($type) {
			if ($conf['default.'][$type.'.']) {
				// Type Default for this mode
				return array($conf['default.'][$type], $conf['default.'][$type.'.']);
			} else if ($this->config['default.'][$type.'.']) {
				// General Type Default
				return array($this->config['default.'][$type], $this->config['default.'][$type.'.']);
			}
		}
		
		// Return general default
		if ($this->config['default.']['default']) {
			return array($this->config['default.']['default'], $this->config['default.']['default.']);
		}
		
		// There is no definition
		return false;
	}

	/**
	 * Will inject additional values after replacements according to TS config took place.
	 * @param $template
	 * @param $field
	 * @param $mode
	 * @param $valueArr
	 * @param $configField
	 */
	function injectStdWrapVariables($template, $field, $mode, $valueArr, $configField) {
		
		$needles = array(
			'%%%GPVAR%%%',
			'%%%VALUE%%%',
			'%%%OPTIONS%%%',
			'%%%PREFIXID%%%',
			'%%%FIELD%%%',
			'%%%EXTPATH%%%',
			'%%%RSEXTBASEPATH%%%',
			'%%%IDVAR%%%',
			'%%%LABEL%%%',
		);
		$values = array(
			$this->getGPvarName($mode, $field),
			htmlspecialchars($valueArr[$field]),
			$this->getSpecialOptions($field, $mode, $valueArr, $configField),
			$this->prefixId,
			$field,
			t3lib_extMgm::siteRelPath($this->extKey),
			t3lib_extMgm::siteRelPath('rsextbase'),
			'id_'.$configField,
			$this->getLabel('l_'.$field, $mode, $valueArr),
		);
		$rc = str_replace($needles, $values, $template);
		return $rc;
	}

	/**
	 * 
	 * @param $field
	 * @param $mode
	 * @param $valueArr
	 * @param $configField
	 */
	function getSpecialOptions($field, $mode, $valueArr, $configField) {
		return '';
	}

	/**
	 * This function evaluates whether the given field is visible in mode of operation.
	 * The method will try various ways to find out:
	 *   1) check the method is<FieldName>Visible in marker class for this field
	 *   2) check the method is<FieldName>Visible in THIS class
	 *   3) check $valuArr[_is_visible_<fieldname>]
	 *   4) check TS config <mode>.<field>._isVisible
	 *   5) check whether field is in TS config list <mode>.fieldnames
	 * The first definition found will be returned.
	 * @param $field
	 * @param $mode
	 * @param $valueArr
	 */
	function isVisible($field, $mode, $valueArr) {
		// Labels depend on their fieldname
		if (preg_match('/^L_/i', $field)) $field = substr($field, 2);

		// GPVar names depend on their fieldname
		if (preg_match('/^GPVAR_/i', $field)) $field = substr($field, 6);

		// GPVar values depend on their fieldname
		if (preg_match('/^GPVAL_/i', $field)) $field = substr($field, 6);

		// Is there a function to tell us?
		$obj = $this->getCustomizedObject($mode, $field);

		$funcName = 'is'.str_replace(' ','',ucwords(str_replace('_',' ',$field))).'MarkerVisible';
		if (method_exists($obj, $funcName)) {
			// e.g. isThisAndThatMarkerVisible (THIS_AND_THAT)
			return $obj->$funcName($this, $mode, $valueArr);
		} else if (method_exists($this, $funcName)) {
			// e.g. isThisAndThatMarkerVisible (THIS_AND_THAT)
			return $this->$funcName($this, $mode, $valueArr);
		}

		// Is there a value _is_visible_$field ?
		if (isset($valueArr["_is_visible_$field"])) return $valueArr["_is_visible_$field"];

		// Checking if there is visible rule in setup
		if (isset($this->config[$mode.'.'][$field.'.']['_isVisible'])) {
			// Make it with stdWrap???
			return $this->config[$mode.'.'][$field.'.']['_isVisible'];
		}

		// Checking if there is a general function isMarkerVisible() telling us
		$funcName = 'isMarkerVisible';
		if (method_exists($obj, $funcName)) {
			return $obj->$funcName($this, $mode, $field, $valueArr);
		} else if (method_exists($this, $funcName)) {
			return $this->$funcName($this, $mode, $field, $valueArr);
		}

		// Check whether fieldnames are set at all
		if (isset($this->config[$mode.'.']['fieldnames'])) {
			// Check whether field is in field list
			$fieldList = explode(',', $this->config[$mode.'.']['fieldnames']);
			return in_array(strtolower($field), $fieldList);
		}
		
		// Field is visible
		return 1;
	}

	/**
	 * Make a field visible by programmatic means. The field will be added to TS config list <mode>.fieldnames
	 * @param $mode
	 * @param $field
	 */
	function enableField($mode, $field) {
		$fieldList = explode(',', $this->config[$mode.'.']['fieldnames']);
		$fieldList[] = $field;
		$this->config[$mode.'.']['fieldnames'] = implode(',', $fieldList);

	}

	/**
	 * Replacement method for templates.
	 * @param $content - the template to be replaced
	 * @param $markContentArray - single markers ###FIELDNAME###
	 * @param $subpartContentArray - section markers <!-- ###FIELDNAME### begin/end -->
	 * @param $wrappedSubpartContentArray - not used
	 * @return the template being replaced by all markers
	 */
	function substituteMarkerArray($content,$markContentArray,$subpartContentArray,$wrappedSubpartContentArray) {

		// If not arrays then set them
		if (!is_array($markContentArray))
		$markContentArray=array();      // Plain markers
		if (!is_array($subpartContentArray))
		$subpartContentArray=array();   // Subparts being directly substituted
		if (!is_array($wrappedSubpartContentArray))
		$wrappedSubpartContentArray=array();    // Subparts being wrapped

		// Finding keys and check hash:
		$sPkeys = array_keys($subpartContentArray);
		$wPkeys = array_keys($wrappedSubpartContentArray);

		// Finding subparts and substituting them with the subpart as a marker
		foreach ($sPkeys AS $marker) {
			$content = $this->substituteSubpart($content, $marker, $subpartContentArray[$marker]);
		}

		// Finding subparts and wrapping them with markers
		reset($wPkeys);
		while(list(,$wPK)=each($wPkeys))	{
			if(is_array($wrappedSubpartContentArray[$wPK])) {
				$parts = &$wrappedSubpartContentArray[$wPK];
			} else {
				$parts = explode('|',$wrappedSubpartContentArray[$wPK]);
			}
			$content = $this->substituteSubpart($content,$wPK,$parts);
		}

		return $this->cObj->substituteMarkerArray($content,$markContentArray);
	}

	/**
	 * Returns the subpart with the given marker name.
	 * @param $template
	 * @param $marker
	 */
	function getSubpart($template, $marker) {
		$info = $this->getSubpartInfo($template, $marker);
		if ($info) {
			return substr($template, $info['contentIndex'], $info['contentLength']);
		}
		return '';
	}

	/**
	 * Replaces the subpart with the given value
	 * @param $template
	 * @param $marker
	 * @param $replacement
	 */
	function substituteSubpart($template, $marker, $replacement) {
		$info = $this->getSubpartInfo($template, $marker);
		if ($info) {
			return substr($template, 0, $info['subpartIndex']).$replacement.substr($template, $info['subpartIndex'] + $info['subpartLength']);
		}
		return $template;
	}

	/**
	 * Returns internal information about a subpart.
	 * This information speeds up replacement.
	 * @param $template
	 * @param $marker
	 */
	function getSubpartInfo($template, $marker) {
		$marker = str_replace('#','',$marker);
		$beginMarker = '';
		$beginPos = -1;
		$endMarker = '';
		$endPos = -1;
		preg_match_all('!\<\!--[a-zA-Z0-9 ]*###([A-Z0-9_-|:]*)###([a-zA-Z0-9 ]*)-->!is', $template, $matches, PREG_OFFSET_CAPTURE);
		for ($i=0; $i<count($matches[0]); $i++) {
			$markerTag = $matches[0][$i][0];
			$markerPos = $matches[0][$i][1];
			$markerName = $matches[1][$i][0];
			$markerComment = strtolower(trim($matches[2][$i][0]));
			if ($marker == $markerName) {
				if (($markerComment == 'begin') && ($beginPos < 0)) {
					$beginPos = $markerPos;
					$beginMarker = $markerTag;
					$beginLength = strlen($beginMarker);
				}
				if (($markerComment == 'end') && ($beginPos >=0) && ($endPos < 0)) {
					$endPos = $markerPos;
					$endMarker = $markerTag;
					$endLength = strlen($endMarker);
					$contentStart = $beginPos + $beginLength;
					$contentEnd = $endPos;
					$contentLength = $contentEnd - $contentStart;
					$rc = array(
						'beginMarkerIndex' => $beginPos,
						'beginMarker' => $beginMarker,
						'beginMarkerLength' => $beginLength,
						'contentIndex' => $contentStart,
						'contentLength' => $contentLength,
						'endMarkerIndex' => $endPos,
						'endMarker' => $endMarker,
						'endMarkerLength' =>  $endLength,
						'subpartIndex' => $beginPos,
						'subpartLength' => $beginLength + $contentLength + $endLength,
					);
					return $rc;
				}
			}
		}
		return NULL;
	}
	
	
	/*******************************************************************************************
		TinyMCE functions
	 ******************************************************************************************/
	function convertFromDB($bodytext) {
		$parseHTML = t3lib_div::makeInstance('t3lib_parsehtml_proc');
		$parseHTML->init('tt_news:bodytext', $this->id);
		$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
		$thisConfig = $pageTSConfig['RTE.']['default.']['FE.'];
		$specConfig = $pageTSConfig['RTE.']['config.']['tt_news.']['bodytext.'];
		$value = $parseHTML->RTE_transform($bodytext, $specConf, 'rte', $thisConfig);
		return $value;
	}

	function convertToDB($bodytext) {
		$parseHTML = t3lib_div::makeInstance('t3lib_parsehtml_proc');
		$parseHTML->init('tt_news:bodytext', $this->id);
		$pageTSConfig = $GLOBALS['TSFE']->getPagesTSconfig();
		$thisConfig = $pageTSConfig['RTE.']['default.']['FE.'];
		$specConfig = $pageTSConfig['RTE.']['config.']['tt_news.']['bodytext.'];
		$value = $parseHTML->RTE_transform($bodytext, $specConf, 'db', $thisConfig);
		$value = preg_replace('!<link /!', '<link ', $value);
		return $value;
	}
	
	/**********************************************************************************************************
	 KCFINDER HANDLING
	 **********************************************************************************************************/
	/**
	 * Returns the configuration for KCFinder to be stored in a value array
	 * @param string $type image, file or media
	 * @param string $dir subdirectory of kcfinder's upload dir
	 * @param string $lang language code: de, en etc.
	 */
	function getKcfinderConfig($type, $dir, $lang = 'en') {
		return array (
			'type' => $type,
			'dir' => $dir,
			'lang' => $lang,
		);
	}
	
	/**
	 * Returns the KcFinder marker.
	 * The marker assumes that value 'kcfinder' contains KCFinder configuration.
	 * @param unknown_type $caller
	 * @param unknown_type $singleMarkers
	 * @param unknown_type $subpartMarkers
	 * @param unknown_type $wrapped
	 * @param unknown_type $mode
	 * @param unknown_type $valueArr
	 */
	function getKcfinderMarker($caller, &$singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr) {
		$settings = $valueArr['kcfinder'];
		if (is_array($settings)) {
			$settings['kcfinder'] = '/'.t3lib_extMgm::siteRelPath('rsextbase')."res/kcfinder/browse.php?type=$settings[type]&lang=$settings[lang]&dir=$settings[dir]";
		
			$singleMarkers['###KCFINDER###'] = $this->getFieldMarkerValue('kcfinder', $mode, $settings);
		} else {
			$singleMarkers['###KCFINDER###'] = '';
		}
	}
	
	
	
	/**********************************************************************************************************
	 EMAIL HANDLING
	 **********************************************************************************************************/

	/**
	 * Sends an email.
	 * This function will not send emails if it was disabled.
	 * @param $recipient
	 * @param $subject
	 * @param $HTMLContent
	 * @param $PLAINContent
	 */
	function sendEmail($recipient, $subject, $HTMLContent, $PLAINContent) {
		// Send normal only if allowed
		if (!$this->config['disableEmails']) {
			$this->_sendEmail($recipient, $subject, $HTMLContent, $PLAINContent);
		}
		// Send a copy to email address
		if ($this->config['emailDebugCopy']) {
			$subject = '###DEBUG### '.$subject;
			$prefix = "Mail to: ";
			if (is_array($recipient)) {
				$prefix .= implode(', ', $recipient);
			} else {
				$prefix .= $recipient;
			}
			$HTMLContent = $prefix."<hr/>".$HTMLContent;
			$PLAINContent = $prefix."\n-------------------------------\n".$PLAINContent;
			$this->_sendEmail($this->config['emailDebugCopy'], $subject, $HTMLContent, $PLAINContent);
		}
				// Send a copy to email address
		if ($this->config['verboseEmail']) {
			echo "<div style=\"background-color: #aaa; border: #000 solid 1px;\">$subject<hr/>$HTMLContent</div>";
		}
	}

	/**
	 * 
	 * @param $recipient
	 * @param $subject
	 * @param $HTMLContent
	 * @param $PLAINContent
	 */
	function _sendEmail($recipient, $subject, $HTMLContent, $PLAINContent) {
		$fromEmail = $this->config['fromEmailAddress'];
		$fromName = $this->config['fromEmailName'];
		$replyTo = $this->config['replyEmailAddress'];
		if ($replyTo == 'typo3@localhost') $replyTo = '';
		
		// wrap HTML content with css and body
		$HTMLContent = "<html>\n".
                        "<head>\n".
                        "<style type=\"text/css\">\n".
                        "* { font-size: 10pt; font-family: Verdana, Arial, Helvetica, sans-serif; }\n".
                        "table { margin: 10px 10px 10px 10px; }\n".
                        "td { font-size: 10pt; border-bottom: #c5d1e8 dotted 1px; }\n".
                        "th { font-size: 10pt; text-align: left; background: #c5d1e8; }\n".
                        "h1 { font-size: 14pt; margin-top: 15px; }\n".
                        "h2 { font-size: 10pt; margin-top: 15px; }\n".
                        "</style>\n".
                        "</head>\n".
                        "<body>\n".
						$HTMLContent.
                        "</body>\n".
                        "</html>";

		$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
		$Typo3_htmlmail->start();
		$Typo3_htmlmail->mailer = 'TYPO3 HTMLMail';
		$Typo3_htmlmail->subject = $subject;
		$Typo3_htmlmail->from_email = $fromEmail;
		$Typo3_htmlmail->returnPath = $fromEmail;
		$Typo3_htmlmail->from_name = $fromName;
		$Typo3_htmlmail->replyto_email = $replyTo ? $replyTo : $fromEmail;
		$Typo3_htmlmail->replyto_name = $replyTo ? '' : $fromName;
		$Typo3_htmlmail->priority = 3;
		$Typo3_htmlmail->theParts['html']['content'] = $HTMLContent;
		$Typo3_htmlmail->extractMediaLinks();
		$Typo3_htmlmail->extractHyperLinks();
		$Typo3_htmlmail->fetchHTMLMedia();
		$Typo3_htmlmail->substMediaNamesInHTML(0); // 0 = relative
		$Typo3_htmlmail->substHREFsInHTML();
		$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
		$Typo3_htmlmail->addPlain($PLAINContent);
		$Typo3_htmlmail->setHeaders();
		$Typo3_htmlmail->setContent();
		$Typo3_htmlmail->setRecipient($recipient);
		$Typo3_htmlmail->sendtheMail();
	}

	/*******************************************************************************************
	 MISC
	 ******************************************************************************************/
	/**
	 * Create random string with given length.
	 * @param $length
	 */
	function createRandomString($length) {
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		if ($length == 0) $length = 20;
		// initialize random generator
		srand((double)microtime()*1000000);
		$rc = '';
		while (strlen($rc) < $length) {
			$num = rand() % strlen($chars);
			$rc .= substr($chars, $num, 1);
		}
		return $rc;
	}

	/**
	 * Immediately redirect to given URL.
	 * @param $url
	 */
	function redirect($url) {
		$link = t3lib_div::locationHeaderUrl($url);
		header('Location: '.$link);
		exit();
	}

	/**
	 * Add values from one array to the other. This function is recursive.
	 * The target will get additional indices with $prefix_$fieldname.
	 * The method will not override values already set!
	 * @param array  $target the array that will be extended
	 * @param string $prefix the prefix to be used for new keys
	 * @param array  $array the array containing new keys and values
	 */
	function addArray(&$target, $prefix, $array) {
		foreach ($array AS $key => $value) {
			$targetKey = strtolower($prefix.'_'.$key);
			if (is_array($value)) {
				if (!isset($target[$targetKey])) {
					tx_rsextbase_pibase::addArray($target, $targetKey, $value);
				}
			}
			else {
				if (!isset($target[$targetKey])) {
					$target[$targetKey] = $value;
				}
			}
		}
	}
	
	/**
	 * Resizes an image
	 * @param array  $imageInfo
	 * @param string $source
	 * @param int    $maxW
	 * @param int    $maxH
	 */
	function resizeImage($imageInfo, $source, $maxW, $maxH) {
		// We use Typo3 onboard tools
		$ts = array (
			'img' => 'IMG_RESOURCE',
			'img.' => array(
				'file' => $source,
				'format' => $imgTypes[$imageInfo[2]],
				'file.' => array(
					'maxW' => $maxW,
					'maxH' => $maxH,
				),
			),
		);
		$rc = $this->local_cObj->IMG_RESOURCE($ts['img.']);

		return $rc;
	}

	
	/**
	 * Parses a date.
	 * Enter description here ...
	 * @param unknown_type $s
	 */
	function parseDate($s) {
		if (preg_match('/^\d+$/', $s)) return $s;
		$tArr = strptime($s, "%d/%m/%Y");
		if ($tArr) {
			$rc = mktime($tArr['tm_hour'],$tArr['tm_min'],$tArr['tm_sec'],
				$tArr['tm_mon']+1,$tArr['tm_mday'],$tArr['tm_year']+1900);
		} else {
			$rc = 0;
		}
		return $rc;
	}

	/**
	 * Returns the language keys in its preferred order
	 */
	function getLLCodes() {
		$rc = array();
		if ($GLOBALS['TSFE']->config['config']['language']) {
			$rc[] = $GLOBALS['TSFE']->config['config']['language'];
			if ($GLOBALS['TSFE']->config['config']['language_alt']) {
				$rc[] = $GLOBALS['TSFE']->config['config']['language_alt'];
			}
		}
		$rc[] = 'default';
		return $rc;
	}

	
	/**
	 * Try to aquire the lock.
	 * @param string $key
	 * @param int    $expire
	 */
	function acquire_lock($key, $expire=60) {
		if (!$this->semaphores[$key]) {
			$this->semaphores[$key] = sem_get($key, 1, 0777, TRUE);
			echo $this->semaphores[$key]."<br/>";
		}
		if (!$this->semaphores[$key]) return;
		
		sem_acquire($this->semaphores[$key]);
		return $this->semaphores[$key];
	}

	function release_lock($key) {
		if (!$this->semaphores[$key]) return;
		sem_release($this->semaphores[$key]);
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rsextbase/res/class.tx_rsextbase_pibase.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rsextbase/res/class.tx_rsextbase_pibase.php']);
}

?>
