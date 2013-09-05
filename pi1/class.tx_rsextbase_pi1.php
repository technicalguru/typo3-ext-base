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
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 */
require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_pibase.php');
require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_ajaxbase.php');


/**
 * Plugin 'AJAX Dispatcher' for the 'rsextbase' extension.
 *
 * @author	Ralph Schuster <typo3@ralph-schuster.eu>
 * @package	TYPO3
 * @subpackage	tx_rsextbase
 */
class tx_rsextbase_pi1 extends tx_rsextbase_pibase {

	var $relPath		= 'pi1';
	var $prefixId		= 'tx_rsextbase_pi1';
	var $scriptRelPath	= 'pi1/class.tx_rsextbase_pi1.php';

	function getPluginContent() {
			return tx_rsextbase_pibase::getTestHtml($this, 'testCall', $this->config['ajaxTypeNum']);
	}
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rsextbase/pi1/class.tx_rsextbase_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rsextbase/pi1/class.tx_rsextbase_pi1.php']);
}

?>