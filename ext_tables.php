<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

// TypoScript Template
t3lib_div::loadTCA('tt_content');
t3lib_extMgm::addStaticFile($_EXTKEY,'static/rs_base_extension/', 'RS Base Extension');

/***********************************************************************************************
 * DATABASE EXTENSIONS
 ***********************************************************************************************/
$TCA['tx_rsextbase_scheduler'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler',
		'label'     => 'uid',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'default_sortby' => 'ORDER BY crdate',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'/res/icons/tx_rsextbase_scheduler.gif',
	),
);

/***********************************************************************************************
 * PLUGINS
 ***********************************************************************************************/
$i = 1;
while (file_exists(t3lib_extMgm::extPath($_EXTKEY).'pi'.$i.'/class.tx_'.$_EXTKEY.'_pi'.$i.'.php')) {
	$piId = 'pi'.$i;
	$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_'.$piId] = 'layout,select_key';
	$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_'.$piId] = 'pi_flexform';
	t3lib_extMgm::addPlugin(array('LLL:EXT:'.$_EXTKEY.'/'.$piId.'/locallang.xml:tt_content.list_type', $_EXTKEY.'_'.$piId), 'list_type');
	t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_'.$piId, 'FILE:EXT:'.$_EXTKEY.'/'.$piId.'/flexform.xml');
	$i++;
}

?>