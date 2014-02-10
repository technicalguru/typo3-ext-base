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

class tx_rsextbase_tinymce extends tx_rsextbase_pibase {
	
	var $cObj;
	var $extKey = 'rsextbase';
	
	/**
	 * Renders a TinyMCE editor.
	 * @param string $content
	 * @param array $conf
	 */
	function tinymce($content, $conf) {
		/*
		echo "CONTENT<br/>\n";
		print_r($content);
		echo "\nCONF<br/>\n";
		print_r($conf);
		echo "\ncOBJ<br/>\n";
		print_r($this->cObj->data);
		*/
		
		// Prepare config
		$this->config = $conf;
		$this->pi_USER_INT_obj = 0;
		$this->local_cObj = t3lib_div::makeInstance("tslib_cObj");
		$this->local_cObj->setCurrentVal($GLOBALS["TSFE"]->id);
		$this->id = $GLOBALS["TSFE"]->id;
		
		// Prepare value
		$this->valueArr = $this->cObj->data;
		$this->value = $this->cObj->data['_value'];
		$this->field = $this->cObj->data['_field'];
		
		// Load template
		if (!$this->config['templateFile']) {
			$this->config['templateFile'] = 'EXT:rsextbase/res/tinymce.tmpl';
		}
		$this->config['templateFile'] = $this->cObj->fileResource($this->config['templateFile']);
		$content = $this->getPreContent();
		$content .= $this->getTextarea();
		$content .= $this->getPostContent();
		
		return $content;
	}
	
	/**
	 * Returns the pre-content JavaScript.
	 */
	function getPreContent() {
		$template = $this->getSubTemplate('TINYMCE_PRE_JS');
		return $this->fillTemplate($template, 'tinymce', $this->cObj->data);
	}
	
	/**
	 * Renders the textarea as configured.
	 */
	function getTextarea() {
		$conf = array($this->config['textarea'], $this->config['textarea.']);
		return $this->invokeCObject($this->field, $conf, $this->valueArr, $this->value);
	}
	
	/**
	 * Returns the post-content JavaScript.
	 */
	function getPostContent() {
		$template = $this->getSubTemplate('TINYMCE_POST_JS');
		return $this->fillTemplate($template, 'tinymce', $this->cObj->data);
	}
	
	/**
	 * Returns the KCFinder plugin marker 1 if ḰCFinder was enabled.
	 */
	function getKcfinder1Markers($caller, $template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$rc = '';
		if ($this->config['kcfinder']) {
			$rc = $this->fillTemplate($template, $mode, $valueArr);
		}
		$subpartMarkers['###KCFINDER1###'] = $rc;
	}
	
	/**
	 * Returns the KCFinder plugin marker 2 if ḰCFinder was enabled.
	 */
	function getKcfinder2Markers($caller, $template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$rc = '';
		if ($this->config['kcfinder']) {
			$valueArr['width']  = $this->config['kcfinder.']['width'];
			$valueArr['height'] = $this->config['kcfinder.']['height'];
			$rc = $this->fillTemplate($template, $mode, $valueArr);
		}
		$subpartMarkers['###KCFINDER2###'] = $rc;
	}
}

?>