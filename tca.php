<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_rsextbase_scheduler'] = array (
	'ctrl' => $TCA['tx_rsextbase_scheduler']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'application,task,schedule_type,schedule_data'
	),
	'feInterface' => $TCA['tx_rsextbase_scheduler']['feInterface'],
	'columns' => array (
		// APPLICATION
		'application' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.application',
			'config' => array (
				'type' => 'input',
				'size' => '48',
				'eval' => 'required,trim',
			),
		),
		// TASK
		'task' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.task',
			'config' => array (
				'type' => 'input',
				'size' => '48',
				'eval' => 'required,trim',
			),
		),
		// SCHEDULE TYPE
		'schedule_type' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.schedule_type',
			'config' => array (
				'type' => 'input',
				'size' => '48',
				'eval' => 'required,trim',
			),
		),
		// SCHEDULE DATA
		'schedule_data' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.schedule_data',
			'config' => array (
				'type' => 'input',
				'size' => '48',
				'eval' => 'required,trim',
			),
		),
		// CURRENT RUN
		'current_run' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.current_run',
			'config' => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'trim,datetime',
				'default'  => '0',
				'checkbox' => '1',
			),
		),
		// LAST RUN
		'last_run' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:rsextbase/locallang_db.xml:tx_rsextbase_scheduler.last_run',
			'config' => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'required,trim,datetime',
				'default'  => '0',
				'checkbox' => '0',
			),
		),
	),
	'types' => array (
		'0' => array('showitem' => 'application,task,schedule_type,schedule_data,current_run,last_run'),
	),
	'palettes' => array (
	)
);

?>
