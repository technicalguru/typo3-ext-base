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

require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_database.php');

define(SCHEDULE_PERIOD, 'period');
define(SCHEDULE_TIMESTAMP, 'timestamp');

class tx_rsextbase_scheduler extends tx_rsextbase_database {
	
	/**
	 * The application key
	 * @var string
	 */
	var $applicationKey;
	
	/**
	 * All defined tasks for this application
	 * @var array
	 */
	var $tasks = array();
	
	/**
	 * Time of execution.
	 * @var int
	 */
	var $executionTime = 0;
	
	/**
	 * The data folder ID
	 * @var int
	 */
	var $dataFolder = 0;
	
	/**
	 * 
	 * @param $pi - plugin this object belongs to
	 * @param $applicationKey - application key in database
	 */
	function init($dataFolder, $applicationKey) {
		$this->dataFolder = $dataFolder;
		$this->applicationKey = $applicationKey;
		$this->executionTime = time();
		
		$this->loadTasks();
		
	}

	/**
	 * Loads all tasks from database.
	 */
	function loadTasks() {
		$this->tasks = array();
		
		$where = "application='".$this->applicationKey."' AND pid=".$this->dataFolder;
		$tasks = $this->getRecords('tx_rsextbase_scheduler', $where);
		
		// Unserialize data
		foreach ($tasks AS $task) {
			$task['schedule_data'] = unserialize($task['schedule_data']);
			$this->tasks[] = $task;
		}
	}
	
	/**
	 * Returns the number of loaded tasks.
	 */
	function getTaskCount() {
		return count($this->tasks);
	}
	
	/**
	 * Returns names of tasks that must be executed.
	 * The tasks are marked in database as being executed and must be explicitely freed
	 * by calling taskFinished($name).
	 * @param string $taskParam name of tasks to be executed
	 */
	function getTasks($taskParam = '') {
		$candidates = array();
		
		// Check tasks to be executed
		if (!$taskParam) {
			// from schedule
			foreach ($this->tasks AS $task) {
				if ($this->isDue($task)) {
					$candidates[] = $task;
				}
			}
		} else {
			// from definition outside
			$arr = explode(',', $taskParam);
			foreach ($this->tasks AS $task) {
				if (in_array($task['task'], $arr)) {
					$candidates[] = $task;
				}
			}
		}
		
		// Check if task is not blocked or last execution timed out
		$execTasks = array();
		foreach ($candidates AS $task) {
			if (!$task['current_run']) {
				$execTasks[] = $task;
			} else if (($task['default_runtime'] > 0) && ($task['current_run']+$task['default_runtime'] < time())) {
				$execTasks[] = $task;
			} else if (($task['default_runtime'] > 0) && ($task['current_run']+3600*24 < time())) {
				$execTasks[] = $task;
			}
		}
		
		// Now mark tasks being executed in database
		$rc = array();
		foreach ($execTasks AS $task) {
			$update = array(
				'current_run' => $this->executionTime,
				'last_run' => $this->executionTime,
			);
			$this->updateRecord('tx_rsextbase_scheduler', $task['uid'], $update);
			$rc[] = $task['task'];
		}
		
		return $rc;
	}
	
	/**
	 * Returns true when the given task is due for execution.
	 * @param array $task task record from database
	 */
	function isDue($task) {
		if ($task['schedule_type'] == SCHEDULE_PERIOD) return $this->isPeriodDue($task);
		if ($task['schedule_type'] == SCHEDULE_TIMESTAMP) return $this->isTimestampDue($task);
		
		return false;
	}
	
	/**
	 * Assumes that task is a period task and will execute if period in seconds given as schedule_data
	 * has been passed. A buffer of 10 seconds is given, meaning a period of 60 seconds is already passed
	 * when 5ß seconds were gone since last execution.
	 * @param array $task
	 */
	function isPeriodDue($task) {
		return $task['last_run'] + $task['schedule_data'] < $this->executionTime + 10;
	}
	
	/**
	 * Checks if the task is due according to given execution timestamp. schedule data is 
	 * a serialized array that has same keys as the getdate() return value. Each key of the
	 * array will be checked against the current timestamp. -1 means that this key shall not
	 * be checked (you can omit it too). Seconds will never be checked.
	 * @param array $task
	 */
	function isTimestampDue($task) {
		$data = $task['schedule_data'];
		
		// Get information about current time
		$now = getdate($this->executionTime);
		$previous = $this->getPreviousTimestampDue($data)-10;
		
		// Did task run before previous timestamp? 
		return $task['last_run'] < $previous;
	}
	
	function getPreviousTimestampDue($timeDef) {
		$timestamp = $this->executionTime;
		$t = getdate($timestamp);
		
		//echo "<h4>Previous</h4>\n";
		//echo "<pre>\ntimeDef=";
		//print_r($timeDef);
		
		// Get back to second=0
		$timestamp -= $t['seconds'];
		$t = getdate($timestamp);
		
		// Get back to minute defined
		if ($this->adjustTimestamp($timeDef, $t, 'minutes')) {
			$cmpValue = $this->getPreviousValue($timeDef['minutes'], $t['minutes']);
			$diff = $t['minutes'] - $cmpValue;
			if ($diff > 0) $timestamp -= $diff*60;
			else $timestamp -= (60+$diff)*60;
			$t = getdate($timestamp);
		}
		
		// Get back to hour defines
		if ($this->adjustTimestamp($timeDef, $t, 'hours')) {
			$cmpValue = $this->getPreviousValue($timeDef['hours'], $t['hours']);
			$diff = $t['hours'] - $cmpValue;
			if ($diff > 0) $timestamp -= $diff*3600;
			else $timestamp -= (24+$diff)*3600;
			$t = getdate($timestamp);
		}
		
		// Get back to weekday defined
		if ($this->adjustTimestamp($timeDef, $t, 'wday')) {
			$cmpValue = $this->getPreviousValue($timeDef['wday'], $t['wday']);
			$diff = $t['wday'] - $cmpValue;
			if ($diff > 0) $timestamp -= $diff*24*3600;
			else $timestamp -= (7+$diff)*24*3600;
			$t = getdate($timestamp);
		}
		
		// get back to day of month defined
		if ($this->adjustTimestamp($timeDef, $t, 'mday')) {
			$cmpValue = $this->getPreviousValue($timeDef['mday'], $t['mday']);
			$diff = $t['mday'] - $cmpValue;
			if ($diff > 0) $timestamp -= $diff*24*3600;
			else {
				while ($timeDef['mday'] != $t['mday']) {
					$timestamp -= 24*3600;
					$t = getdate($timestamp);
				}
			}
			$t = getdate($timestamp);
		}
		
		// Get back to month defined
		// TODO
				
		// Get back to day of year defined
		// TODO
				
		// Get back to year defined
		// TODO
		
		//echo "\nprevious=";	print_r($t);
		//echo "\n".date(DATE_RFC2822, $timestamp)."</pre>";
		
		return $timestamp;
	}
	
	/**
	 * Returns false when the task definition requires this property and current time definition
	 * is not equal to this yet.
	 * @param array $taskTimeDef date array of task definition
	 * @param array $timeDef date array of current timestamp
	 * @param string $name name of property
	 */
	function adjustTimestamp($taskTimeDef, $timeDef, $name) {
		if (isset($taskTimeDef[$name])) {
			if ($taskTimeDef[$name] >= 0) {
				$cmpValue = $this->getPreviousValue($taskTimeDef[$name], $timeDef[$name]);
				return $cmpValue != $timeDef[$name];
			}
		}
		return false;
	}
	
	/**
	 * Returns the previous value defined in taskDefValues list closest to timestamp value
	 * @param string $taskDefValue comma separated list of values
	 * @param int $timeValue current timestamp value
	 */
	function getPreviousValue($taskDefValue, $timeValue) {
		$arr = explode(',', $taskDefValue);
		$rc = -1;
		
		// Search nearest BEFORE current value
		foreach ($arr AS $value) {
			if ($value > $timeValue) break;
			$rc = $value;
		}
		
		// Latest from list if nearest not found
		if ($rc < 0) {
			$rc = $arr[count($arr)-1];
		}
		
		//echo "previous of $taskDefValue at $timeValue is $rc<br/>";
		return $rc;
	}
	
	/**
	 * Returns the task with given name
	 * @param string $name
	 */
	function getTask($name) {
		foreach ($this->tasks AS $task) {
			if ($task['task'] == $name) return $task;
		}
		return false;
	}
	
	/**
	 * Creates a new task with given values
	 * @param string $name - name of task
	 * @param string $scheduleType - type of schedule (period or timestamp)
	 * @param mixed $scheduleData - data of schedule
	 * @param int $defaultRuntime - timeout of task
	 */
	function createTask($name, $scheduleType, $scheduleData, $defaultRuntime = 0) {
		$arr = array(
			'pid' => $this->dataFolder,
			'crdate' => time(),
			'tstamp' => time(),
			'application' => $this->applicationKey,
			'task' => $name,
			'schedule_type' => $scheduleType,
			'schedule_data' => serialize($scheduleData),
			'default_runtime' => $defaultRuntime,
			'current_run' => 0,
			'last_run' => 0,
		);

		$uid = $this->createRecord('tx_rsextbase_scheduler', $arr);
		$rc = $this->getRecordByUid('tx_rsextbase_scheduler', $uid);
		$rc['schedule_data'] = unserialize($rc['schedule_data']);
		$this->tasks[] = $rc;
		
		return $rc;
	}
	
	/**
	 * Updates or creates the task properties. This method does not reset the last execution time.
	 * @param string $name
	 * @param string $scheduleType
	 * @param mixed $scheduleData
	 * @param int $defaultRuntime
	 */
	function updateTask($name, $scheduleType, $scheduleData, $defaultRuntime = 0) {
		$task = $this->getTask($name);
		if (is_array($task)) {
			$update = array(
				'tstamp' => time(),
				'schedule_type' => $scheduleType,
				'schedule_data' => serialize($scheduleData),
				'default_runtime' => $defaultRuntime,
			);
			$this->updateRecord('tx_rsextbase_scheduler', $task['uid'], $update);
			$this->loadTasks();
		} else {
			$this->createTask($name, $scheduleType, $scheduleData, $defaultRuntime);
		}
	}
	
	/**
	 * Marks the task as finished.
	 * Note that the loaded tasks will not be refreshed.
	 * @param string $name
	 */
	function taskFinished($name) {
		if (is_array($name)) $name = $name['task'];
		$task = $this->getTask($name);
		if (is_array($task)) {
			$this->updateRecord('tx_rsextbase_scheduler', $task['uid'], array('current_run' => 0));
		}
	}
	
	/**
	 * Marks given tasks as finished.
	 * Also reloads the internal task list.
	 * @param array $names
	 */
	function tasksFinished($names) {
		foreach ($names AS $name) {
			$this->taskFinished($name);
		}
		$this->loadTasks();
	}
	
	/**
	 * Deletes the given task from database.
	 * @param string $name
	 */
	function deleteTask($name) {
		$where = "application='".$this->applicationKey."' AND task='$name' AND pid=".$this->dataFolder;
		$this->deleteRecords('tx_rsextbase_scheduler', $where);
		$this->loadTasks();
	}
}

?>