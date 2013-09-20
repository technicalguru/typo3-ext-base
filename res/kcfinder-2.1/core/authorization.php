<?php

if (defined('E_DEPRECATED')) {
        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
} else {
        error_reporting(E_ALL ^ E_NOTICE);
}

// find the typo3conf folder by walking up the dir tree
$typo3conf = 'typo3conf';
while (!file_exists($typo3conf) && !is_dir($typo3conf)) {
	$typo3conf = '../'.$typo3conf;
}

// ******************
// Constants defined
// ******************

define('PATH_typo3conf', $typo3conf);

class Authorization {

	var $dbhost;
	var $dbname;
	var $user;
	var $passwd;

	function Authorization() {
		// Is this a new typo3 installation?
		if (file_exists(PATH_typo3conf.'/LocalConfiguration.php')) {
			$conf = require PATH_typo3conf.'/LocalConfiguration.php';
			$this->dbhost = $conf['DB']['host'];
			$this->dbname = $conf['DB']['database'];
			$this->user   = $conf['DB']['username'];
			$this->passwd = $conf['DB']['password'];
		} else {
			require_once(PATH_typo3conf.'/localconf.php');

			$this->dbhost = $typo_db_host;
			$this->dbname = $typo_db;
			$this->user   = $typo_db_username;
			$this->passwd = $typo_db_password;
		}
	}
	
	public function isAuthorized(&$uploader) {
		$rc = false;
		$con = mysql_connect($this->dbhost, $this->user, $this->passwd);
		if (!$con) {
    		die;
		}
		mysql_select_db($this->dbname) || die;
		
		$result = mysql_query("SELECT * FROM fe_sessions");
		while ($row = mysql_fetch_assoc($result)) {
			//echo $uploader->cookie['fe_typo_user']." : $row[ses_id] : $row[ses_name] : $row[ses_userid] : $row[ses_tstamp]<br/>\n";
			if (($uploader->cookie['fe_typo_user'] == $row['ses_id']) && ($row['ses_name'] == 'fe_typo_user')) {
				// This is the session, is it still valid?
				if (time - $row[ses_tstamp] < 3600) {
					$rc = true;
					break;
				}
				$rc = false;
				break;
			}
		}
		mysql_free_result($result);
		mysql_close($con);
		return $rc;
	} 

}







?>
