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


class tx_rsextbase_ajaxbase {

	var $pi;
	var $methods;
	
	/**
	 * Initialization.
	 * @param $pi the parent plugin for callbacks and configuration.
	 */
	function init(&$pi) {
		$this->pi = $pi;
		$this->methods = array();
		$this->registerMethods();
	}

	/**
	 * Main function that processes the AJX request.
	 * This function will try to match the method name in the request and call appropriate method.
	 * @param $jsonRequest the AJAX request 
	 * @param $dispatcher the dispatcher plugin, required for callbacks
	 * @return JSON response to be sent back to HTTP client
	 */
	function main($jsonRequest, &$dispatcher) {
		// This is a security feature: The module must execute the
		// correct method itself so outsiders cannot call ANY module
		// and method they want
		$methodName = $this->getMethod($jsonRequest['method']);
		if (!$methodName) {
			$error = array(
				'id' 	=> 1,
				'msg'	=> $dispatcher->pi_getLL('err_no_such_method'),
			);
			return $this->error($error, $jsonRequest);
		}
		
		// Does this method exist?
		if (!method_exists($this, $methodName)) {
			$error = array(
				'id' 	=> 1,
				'msg'	=> $dipatcher->pi_getLL('err_no_such_method'),
			);
			return $this->error($error, $jsonRequest);
		}
		
		// Call the method
		return $this->$methodName($jsonRequest);
	}
	
	/**************************************************************
	 * METHOD HANDLING
	 **************************************************************/
	/**
	 * Returns the method with given name.
	 * This is for matching purposes to deny Web-Calls to every method.
	 * @param $methodName web-name of method requested
	 * @return real name of method 
	 */
	function getMethod($methodName) {
		return $this->methods[$methodName];
	}
	
	/**
	 * Registers all methods that can be called.
	 * Classes must override this (does nothing by default)
	 */
	function registerMethods() {
		// Do nothing
	}
	
	/**
	 * Registers a method for web-calls
	 * @param $name web-name of method
	 * @param $method real name of method
	 */
	function registerMethod($name, $method) {
		$this->methods[$name] = $method;
	}
	
	/**************************************************************
	 * ERROR HANDLING
	 **************************************************************/
	
	/**
	 * Returns an error for JSON replies.
	 * @param $errorObject error to be encoded
	 * @param $request original request
	 * @return error response in JSON
	 */
	function error($errorObject, $request) {
		$response = array(
			'error' => $errorObject,
			'id' => $request['id'],
		);
		return json_encode($response);
	}
	
	/**************************************************************
	 * RETURN HANDLING
	 **************************************************************/
	
	/**
	 * Returns a success result for JSON replies
	 * @param $result result to be encoded
	 * @param $request original request
	 * @return success response in JSON
	 */
	function response($result, $request) {
		$response = array(
			'result' => $result,
			'id' => $request['id'],
		);
		return json_encode($response);
	}
	
	
}

?>
