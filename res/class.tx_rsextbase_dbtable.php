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

/** Base class for db table access in Typo3 */
class tx_rsextbase_dbtable {

	var $table;
	var $database;
	var $debugSQL = FALSE;
	
	/**
	  * Creates this object.
	  * @param object $database - the rsextbase_database object
	  * @param string $table    - table name
	  */
	public function __construct(&$database, $table) {
		$this->database = &$database;
		$this->table = $table;
	}

	/********************************************************************
	  SELECT
	 ********************************************************************/
	/**
	  * Returns records of the table represented by this object.
	  * @param string $where - WHERE clause
	  * @param int    $n     - number of objects to be returned (empty for all)
	  * @param string $order - ORDER clause
	  */
	function select($where, $n = '', $order = '') {
		if ($this->debugSQL) {
			$previous = $this->database->debugSQL;
			$this->database->debugSQL = $this->debugSQL;
		}
		$rc = $this->database->getRecords($this->table, $where, $order, $n);
		if ($this->debugSQL) {
			$this->database->debugSQL = $previous;
			if (is_array($rc)) {
				echo "Query returned ".count($rc)." record(s)<br/>";
			} else {
				echo "Query returned no records<br/>";
			}
		}
		return $rc;
	}

	/**
	  * Returns number of records of the table represented by this object.
	  * @param string $where - WHERE clause
	  */
	function count($where) {
		$arr = $this->database->selectRecord('count(*) AS cnt', $this->table, $where);
		return $arr['cnt'];
	}

	/**
	  * Returns a single record of the table represented by this object.
	  * @param string $where - WHERE clause (assumed to select single record)
	  * @param string $order - ORDER clause
	  */
	function selectSingle($where, $order = '') {
		$arr = $this->select($where, $order, 1);
		return $arr[0];
	}
	
	/**
	  * Returns a single record of the table represented by this object.
	  * @param int $uid - UID of record
	  */
	function selectByUid($uid) {
		$uid = $GLOBALS['TYPO3_DB']->quoteStr($uid, $table);
		return $this->selectSingle("uid=$uid");
	}

	/********************************************************************
	  INSERT
	 ********************************************************************/
	/**
	 * Inserts the record given.
	 * @param $record - the record to be inserted
	 * @return the record inserted
	 */
	function insert($record) {
		return $this->database->insertRecord($this->table, $record);
	}

	/********************************************************************
	  UPDATE
	 ********************************************************************/
	/**
	 * Update records.
	 * @param string $where  - WHERE clause
	 * @param array  $fields - fields to be updated
	 */
	function update($where, $fields) {
		$this->database->updateRecordsWhere($this->table, $where, $fields);
	}

	/**
	 * Updates a single record.
	 * @param string $uid    - UID of record
	 * @param array  $fields - updated field
	 */
	function updateByUid($uid, $fields) {
		$this->database->updateRecord($this->table, $uid, $fields);
	}

	/********************************************************************
	  DELETE
	 ********************************************************************/
	/**
	 * Delete a record from the table.
	 * @param int     $uid    - UID of record to be deleted
	 * @param boolean $delete - TRUE if physically delete record, FALSE if logically
	 */
	function deleteByUid($uid, $delete = FALSE) {
		$this->database->removeRecord($this-table, $uid, $delete);
	}

	/**
	 * Delete records from the table.
	 * @param string  $where  - WHERE clause
	 * @param boolean $delete - TRUE if physically delete record, FALSE if logically
	 */
	function delete($where, $delete = FALSE) {
		$this->database->deleteRecords($this->table, $where, $delete);
	}
	
}

?>
