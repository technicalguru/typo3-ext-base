<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

// Installing the plugins
$i = 1;
while (file_exists(t3lib_extMgm::extPath($_EXTKEY).'pi'.$i.'/class.tx_'.$_EXTKEY.'_pi'.$i.'.php')) {
	t3lib_extMgm::addPItoST43($_EXTKEY,'pi'.$i.'/class.tx_'.$_EXTKEY.'_pi'.$i.'.php','_pi'.$i, 'list_type', 0);
	$i++;
}

?>