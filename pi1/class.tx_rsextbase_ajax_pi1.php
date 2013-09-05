<?php

/***************************************************************
*  Copyright notice
*
*  (c) 2010 Administrator <typo3@ralph-schuster.eu>
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

require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_ajaxbase.php');


/**
 * Plugin 'Test Ajax Calls' for the 'rsextbase' extension.
 *
 * @author      Administrator <typo3@ralph-schuster.eu>
 * @package     TYPO3
 * @subpackage  tx_rsextbase
 */
class tx_rsextbase_ajax_pi1 extends tx_rsextbase_ajaxbase {
	
	function registerMethods() {
		$this->registerMethod('testCall', 'testCall');
	}
	
	function testCall($request) {
		return $this->response('AJAX Test Call succeeded for rsextbase', $request);
	}
	
}

?>