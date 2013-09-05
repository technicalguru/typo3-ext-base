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


class tx_rsextbase_database {

	var $pi;
	
	/**
	 * 
	 * @param $pi - plugin this object belongs to
	 */
	function init(&$pi) {
		$this->pi = $pi;
	}

	/********************************************************************
	 COMMON DATABASE FUNCTIONS
	 *********************************************************************/
	/**
	 * 
	 * @param $columns
	 * @param $table
	 * @param $where
	 */
	function selectRecords($columns, $table, $where, $order = '') {
		if ($order == '') $order = $this->getDefaultSorting($table);
		$rc = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($columns, $table, $where, '', $order);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$rc[] = $row;
		}
		return $rc;
	}

	/**
	 *
	 * @param $table
	 * @param $where
	 */
	function getRecords($table, $where, $order = '') {
		return $this->selectRecords('DISTINCT *', $table, $where, '', $order);
	}

	/**
	 * 
	 * @param $columns
	 * @param $table
	 * @param $where
	 */
	function selectRecord($columns, $table, $where) {
		$rc = $this->selectRecords($columns, $table, $where);
		return $rc[0];
	}

	/**
	 * 
	 * @param $table
	 * @param $where
	 */
	function getRecord($table, $where) {
		$rc = $this->getRecords($table, $where);
		return $rc[0];
	}

	/**
	 * 
	 * @param $table
	 * @param $uid
	 */
	function getRecordByUid($table, $uid) {
		$uid = $GLOBALS['TYPO3_DB']->quoteStr($uid, $table);
		return $this->getRecord($table, "uid=$uid");
	}

	/**
	 * 
	 * @param $table
	 * @param $record
	 */
	function createRecord($table, $record) {
		$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $record);
		if ($res) return $GLOBALS['TYPO3_DB']->sql_insert_id();
		return 0;
	}

	/**
	 * 
	 * @param $table
	 * @param $uid
	 * @param $delete
	 */
	function removeRecord($table, $uid, $delete) {
		if ($delete) {
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($table, "uid=$uid");
		} else {
			$this->updateRecord($table, "uid=$uid", array('deleted' => 1));
		}
	}

	function deleteRecords($table, $where) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($table, $where);
	}
	
	/**
	 * 
	 * @param $table
	 * @param $where
	 * @param $fields
	 */
	function updateRecordsWhere($table, $where, $fields) {
		$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields);
		return $res;
	}

	/**
	 * Updates a record
	 * @param string $table
	 * @param string $uid
	 * @param array  $fields
	 */
	function updateRecord($table, $uid, $fields) {
		return $this->updateRecordsWhere($table, "uid=$uid", $fields);
	}

	/**
	 * Returns the default sorting for the given table.
	 * @param string $table
	 */
	function getDefaultSorting($table) {
		// Get the default sorting from config
		if ($this->pi->config['config.']['database.'][$table]) return $this->pi->config['config.']['database.'][$table];
		return '';
	}
	
	/********************************************************************
	 USERS
	 *********************************************************************/
	/**
	 * Returns all active users.
	 */
	function getUsers() {
		$where = 'deleted=0 AND disable=0 AND pid='.$this->pi->config['userFolder'];
		return $this->getUsersWhere($where);
	}

	/**
	 * Returns all active users.
	 */
	function getUsersWhere($where) {
		$rc = array();
		$users = $this->getRecords('fe_users', $where);
		foreach ($users AS $user) {
			$user['_is_online'] = $this->isUserOnline($user);
			$rc[] = $user;
		}
		return $rc;
	}

	/**
	 * Returns user with given uid (or current user if uid is 0).
	 * The user must not be deleted.
	 * @param $uid UID of user.
	 */
	function getUser($uid = 0) {
		if ($uid == 0) return $GLOBALS['TSFE']->fe_user->user;
		$rc = array();
		$where = 'deleted=0 AND uid='.$uid.' AND pid='.$this->pi->config['userFolder'];
		$rc = $this->getRecord('fe_users', $where);
		if (is_array($rc)) $rc['_is_online'] = $this->isUserOnline($rc);
		return $rc;
	}

	/**
	 * Returns user by given name.
	 * @param $username name of user
	 * @param $disabled is 0 if disabled users are excluded 
	 */
	function getUserByName($username, $disabled) {
		$rc = array();
		$username = $GLOBALS['TYPO3_DB']->fullQuoteStr($username, 'fe_users');
		$where = "deleted=0 AND username=$username AND pid=".$this->pi->config['userFolder'];
		if (!$disabled) {
			$where .= ' AND disable=0';
		}
		$rc = $this->getRecord('fe_users', $where);
		if (is_array($rc)) $rc['_is_online'] = $this->isUserOnline($rc);
		return $rc;
	}

	/**
	 * 
	 * @param $email
	 * @param $registered
	 */
	function getUserByEmail($email, $registered) {
		$rc = array();
		$email = $GLOBALS['TYPO3_DB']->fullQuoteStr($email, 'fe_users');
		$where = "deleted=0 AND email=$email AND pid=".$this->pi->config['userFolder'];
		if (!$registered) {
			$where .= ' AND disable=0';
		}
		$rc = $this->getRecord('fe_users', $where);
		if (is_array($rc)) $rc['_is_online'] = $this->isUserOnline($rc);
		return $rc;
	}

	/**
	 * 
	 * @param mixed $user
	 */
	function isAdminUser($user = 0) {
		if (!$user) $user = $GLOBALS["TSFE"]->fe_user->user;
		if (!is_array($user)) $user = $this->getUser($user);
		$groups = explode(',', $this->pi->config['adminGroups']);
		foreach ($groups AS $gr) {
			if ($this->inGroup($gr, $user['usergroup'])) return 1;
		}
		return 0;
	}

	/**
	 * 
	 * @param $user
	 */
	function createUser($user) {
		return $this->createRecord('fe_users', $user);
	}

	/**
	 * 
	 * @param $uid
	 * @param $fields
	 */
	function updateUser($uid, $fields) {
		return $this->updateRecord('fe_users', $uid, $fields);
	}

	/**
	 * 
	 * @param $uid
	 * @param $delete
	 */
	function removeUser($uid, $delete) {
		return $this->removeRecord('fe_users', $uid, $delete);
	}

	/********************************************************************
	 ONLINE USERS
	 *********************************************************************/

	/**
	 * 
	 */
	function getOnlineUsers() {
		if (!isset($this->onlineUsers)) {
			$where = 'a.uid=b.ses_userid AND a.deleted=0 AND a.disable=0 AND a.pid='.$this->pi->config['userFolder'];
			$rows = $this->getRecords('fe_users a, fe_sessions b', $where);
			foreach ($rows AS $row) {
				if ($this->isOnline($row['ses_tstamp'])) {
					$row['_is_online'] = 1;
					$this->onlineUsers[$row['uid']] = $row;
				}
			}
		}
		return array_values($this->onlineUsers);
	}

	/**
	 * 
	 * @param $record
	 */
	function isUserOnline($record) {
		$uid = is_array($record) ? $record['uid'] : $record;

		$this->getOnlineUsers();
		return isset($this->onlineUsers[$uid]);
	}

	function getOnlineUserCount() {
		$online = $this->getOnlineUsers();
		return count($online);
	}
	
	/**
	 * 
	 * @param $tstamp
	 */
	function isOnline($tstamp) {
		$max_idle_time = $this->pi->config['maxIdleTime'];
		if (!$max_idle_time) $max_idle_time = 1800;
		$time = time();
		$diff = $time - intval($tstamp);
		if ($diff < 0) $rc = 1;
		else if ($diff < $max_idle_time) $rc = 1;
		else $rc = 0;
		return $rc;
	}

	/********************************************************************
	 GROUPS
	 *********************************************************************/

	/**
	 * get all groups.
	 */
	function getGroups() {
		$where = 'deleted=0 AND pid='.$this->pi->config['userFolder'];
		return $this->getRecords('fe_groups', $where);
	}

	/**
	 * get a specific group.
	 * @param $uid
	 */
	function getGroup($uid) {
		$rc = array();
		$where = 'deleted=0 AND hidden=0 AND uid='.$uid.' AND pid='.$this->pi->config['userFolder'];
		return $this->getRecord('fe_groups', $where);
	}

	/********************************************************************
	 USERS AND GROUPS
	 *********************************************************************/

	/**
	 * Returns all users in given group.
	 * @param int $group group
	 */
	function getUsersInGroup($group) {
		$guid = is_array($group) ? $group['uid'] : $group;
		
		if ($this->groupMembers[$guid]) return $this->groupMembers[$guid];

		$rc = array();

		// Check all members
		$where = 'deleted=0 AND disable=0 AND pid='.$this->pi->config['userFolder'];
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', $where, '', $this->getDefaultSorting('fe_users'));
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($this->inGroup($guid, $row['usergroup'])) {
				$rc[] = $row;
			}
		}

		$this->groupMembers[$guid] = $rc;
		return $rc;
	}

	/**
	 * Returns true if user is in group
	 * @param unknown_type $user complete user record.
	 * @param unknown_type $group group
	 */
	function isUserInGroup($user, $group) {
		$guid = is_array($group) ? $group['uid'] : $group;
		$haystack = is_array($user) ? $user['usergroup'] : $user; 
		return $this->inGroup($guid, $haystack);
	}
	
	/**
	 * Checks whether group is in haystack.
	 * @param $groupId
	 * @param $haystack
	 */
	function inGroup($group, $haystack) {
		$guid = is_array($group) ? $group['uid'] : $group;
		
		$groups = $this->explodeGroup($guid);
		$arr = explode(',', $haystack);
		foreach ($groups AS $gr) {
			if (in_array($gr, $arr)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Explodes the group into an array of group ids that belong to this group also.
	 * @param $group
	 */
	function explodeGroup($group) {
		$guid = is_array($group) ? $group['uid'] : $group;
		
		$rc = array($guid);
		while (TRUE) {
			$before = count($rc);
			$where = 'deleted=0 AND hidden=0 AND pid='.$this->pi->config['userFolder'];
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_groups', $where);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$arr = explode(',', $row['subgroup']);
				foreach ($rc AS $gr) {
					if (in_array($gr, $arr) && !in_array($row['uid'], $rc)) {
						$rc[] = $row['uid'];
						break;
					}
				}
			}

			$after = count($rc);
			if ($before == $after) break;
		}
		return $rc;
	}


}

?>