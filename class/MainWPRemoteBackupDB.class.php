<?php

class MainWPRemoteBackupDB
{
    //Config
    private $mainwp_remote_backup_extension_db_version = '1.3';
    //Private
    private $table_prefix;
    //Singleton
    private static $instance = null;

    /**
     * @static
     * @return MainWPRemoteBackupDB
     */
    static function Instance()
    {
        if (MainWPRemoteBackupDB::$instance == null) MainWPRemoteBackupDB::$instance = new MainWPRemoteBackupDB();

        return MainWPRemoteBackupDB::$instance;
    }

    //Constructor
    function __construct()
    {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . "mainwp_";
    }

    private function tableName($suffix)
    {
        return $this->table_prefix . $suffix;
    }

    //Installs new DB
    function install()
    {
        $currentVersion = get_site_option('mainwp_remote_backup_extension_db_version');
        if ($currentVersion == $this->mainwp_remote_backup_extension_db_version) return;

        $sql = array();

        $tbl = 'CREATE TABLE `' . $this->tableName('remote_dest') . '` (
  `id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL,
  `title` text NOT NULL,
  `type` text NOT NULL,
  `field1` text NULL,
  `field2` text NULL,
  `field3` text NULL,
  `field4` text NULL,
  `field5` text NULL,
  `field6` text NULL,
  `field7` text NULL,
  `field8` text NULL,
  `field9` text NULL,
  `field10` text NULL,
  `field11` text NULL,
  `field12` text NULL,
  `field13` text NULL,
  `field14` text NULL,
  `field15` text NULL,
  `dtsCheck` int(11) NOT NULL';
        if ($currentVersion === false || $currentVersion == '') $tbl .= ',
  PRIMARY KEY  (`id`)  ';
        $tbl .= ')';
        $sql[] = $tbl;

        $tbl = 'CREATE TABLE `' . $this->tableName('task_remote_dest') . '` (
  `taskid` int(11) NOT NULL,
  `remote_dest_id` int(11) NOT NULL';
        if ($currentVersion === false || $currentVersion == '') $tbl .= ',
  PRIMARY KEY  (taskid,remote_dest_id)  ';
        $tbl .= ')';
        $sql[] = $tbl;

        $tbl = 'CREATE TABLE `' . $this->tableName('wp_remote_dest') . '` (
  `wpid` int(11) NOT NULL,
  `remote_dest_id` int(11) NOT NULL';
        if ($currentVersion === false || $currentVersion == '') $tbl .= ',
  PRIMARY KEY  (wpid,remote_dest_id)  ';
        $tbl .= ')';
        $sql[] = $tbl;

        $tbl = 'CREATE TABLE `' . $this->tableName('wp_remote_backups') . '` (
  `wpid` int(11) NOT NULL,
  `backuptype` varchar(20) NOT NULL,
  `backups` text NOT NULL,
  `dest_identifier` varchar(255) NOT NULL';
        if ($currentVersion === false || $currentVersion == '') $tbl .= ',
  PRIMARY KEY  (wpid,backuptype,dest_identifier(255))  ';
        $tbl .= ')';
        $sql[] = $tbl;

        $tbl = 'CREATE TABLE ' . $this->tableName('wp_remotebackup_progress') . ' (
  task_id int(11) NOT NULL,
  wp_id int(11) NOT NULL,
  last_run int(11) NOT NULL DEFAULT 0,
  remoteDestinations text NOT NULL DEFAULT ""
         )';
        $sql[] = $tbl;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query)
        {
            dbDelta($query);
        }

        $this->post_update();

        update_option('mainwp_remote_backup_extension_db_version', $this->mainwp_remote_backup_extension_db_version);
    }


    function post_update()
    {
        $currentVersion = get_site_option('mainwp_remote_backup_extension_db_version');
        if ($currentVersion === false) return;

        if (version_compare($currentVersion, '1.3', '<'))
        {
            /** @var $wpdb wpdb */
            global $wpdb;

            $wpdb->query('alter table `' . $this->tableName('wp_remote_backups') . '` drop primary key, add primary key(wpid,backuptype,dest_identifier(255))');
        }
    }

    public function insertRemoteBackups($wpId, $backupType, $identifier, $backups)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        return $wpdb->insert($this->tableName('wp_remote_backups'), array('wpid' => $wpId, 'backuptype' => $backupType, 'dest_identifier' => $identifier, 'backups' => json_encode($backups)));
    }

    public function getRemoteBackups($wpId, $backupType, $identifier)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        return $wpdb->get_row('SELECT * FROM ' . $this->tableName('wp_remote_backups') . ' WHERE wpid = ' . $wpId . ' AND backuptype = "'.$backupType.'" AND (dest_identifier is null or dest_identifier = "" or dest_identifier = "' . esc_sql($identifier) . '")', OBJECT);
    }

    public function updateRemoteBackups($wpId, $backupType, $identifier, $backups)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $remoteBackups = $this->getRemoteBackups($wpId, $backupType, $identifier);
        if ($remoteBackups == null)
        {
            return $this->insertRemoteBackups($wpId, $backupType, $identifier, $backups);
        }
        else
        {
            if ($remoteBackups->dest_identifier == $identifier)
            {
                return $wpdb->update($this->tableName('wp_remote_backups'), array('backups' => json_encode($backups)), array('wpid' => $wpId, 'backuptype' => $backupType, 'dest_identifier' => $identifier));
            }
            else
            {
                $wpdb->query('UPDATE ' . $this->tableName('wp_remote_backups') . ' SET backups = "' . esc_sql(json_encode($backups)) . '", dest_identifier="'.esc_sql($identifier).'" WHERE wpid = ' . $wpId . ' AND backuptype = "' . esc_sql($backupType) . '" AND (dest_identifier IS NULL OR dest_identifier = "")');
                return 1;
            }
        }
    }

    public function getRemoteDestinationById($id)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        return $wpdb->get_row('SELECT * FROM ' . $this->tableName('remote_dest') . ' WHERE id = ' . $id, OBJECT);
    }

    public function getRemoteDestination($userid, $type, $fields = array())
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        if (MainWPRemoteDestinationUtility::ctype_digit($userid))
        {
            $qry = 'SELECT * FROM ' . $this->tableName('remote_dest') . ' WHERE userid = ' . $userid . ' AND type = "' . $type . '"';
            foreach ($fields as $idx => $field)
            {
                if ($field != null)
                {
                    $qry .= ' AND field'.($idx+1). ' = "'.$this->escape($field).'"';
                }
            }

            return $wpdb->get_results($qry, OBJECT);
        }
        return null;
    }

    public function updateRemoteDestination($id, $fields)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        if (MainWPRemoteDestinationUtility::ctype_digit($id)) {
            return $wpdb->update($this->tableName('remote_dest'), $fields, array('id' => $id));
        }
        return null;
    }

    public function removeRemoteDestination($remoteDestination)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $wpdb->query('DELETE FROM ' . $this->tableName('task_remote_dest') . ' WHERE remote_dest_id=' . $remoteDestination->id );
        $wpdb->query('DELETE FROM ' . $this->tableName('wp_remote_dest') . ' WHERE remote_dest_id=' . $remoteDestination->id );
        $wpdb->query('DELETE FROM ' . $this->tableName('remote_dest') . ' WHERE id=' . $remoteDestination->id );

        return true;
    }

    public function addRemoteDestination($userid, $type, $fields)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        if (MainWPRemoteDestinationUtility::ctype_digit($userid)) {
            $insertFields = array('userid' => $userid, 'type' => $type);
            foreach ($fields as $idx => $field)
            {
                if ($field != null)
                {
                    $insertFields['field'.($idx+1)] = $field;
                }
            }

            if ($wpdb->insert($this->tableName('remote_dest'), $insertFields)) {
                return $this->getRemoteDestinationById($wpdb->insert_id);
            }
        }
        return null;
    }

    public function addRemoteDestinationWithValues($type, $values)
    {
        /** @var $wpdb wpdb */
        global $wpdb,$current_user;

        $userid = $current_user->ID;

        if (MainWPRemoteDestinationUtility::ctype_digit($userid)) {
            $insertFields = array('userid' => $userid, 'type' => $type);
            foreach ($values as $idx => $field)
            {
                $insertFields[$idx] = $field;
            }

            if ($wpdb->insert($this->tableName('remote_dest'), $insertFields)) {
                return $this->getRemoteDestinationById($wpdb->insert_id);
            }
        }
        return null;
    }

    public function clearRemoteDestinationsFromTask($taskId)
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        if (MainWPRemoteDestinationUtility::ctype_digit($taskId))
        {
            $wpdb->query('DELETE FROM ' . $this->tableName('task_remote_dest') . ' WHERE taskid=' . $taskId );
        }
    }
    public function addRemoteDestinationToTask($remoteDestinationId, $taskId)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        if (MainWPRemoteDestinationUtility::ctype_digit($remoteDestinationId) && MainWPRemoteDestinationUtility::ctype_digit($taskId)) {
            if ($wpdb->insert($this->tableName('task_remote_dest'), array('taskid' => $taskId, 'remote_dest_id' => $remoteDestinationId))) {
                return $wpdb->insert_id;
            }
        }
        return false;
    }
    public function clearRemoteDestinationsFromWebsite($websiteId)
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        if (MainWPRemoteDestinationUtility::ctype_digit($websiteId))
        {
            $wpdb->query('DELETE FROM ' . $this->tableName('wp_remote_dest') . ' WHERE wpid=' . $websiteId );
        }
    }
    public function addRemoteDestinationToWebsite($remoteDestinationId, $websiteId)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        if (MainWPRemoteDestinationUtility::ctype_digit($remoteDestinationId) && MainWPRemoteDestinationUtility::ctype_digit($websiteId)) {
            if ($wpdb->insert($this->tableName('wp_remote_dest'), array('wpid' => $websiteId, 'remote_dest_id' => $remoteDestinationId))) {
                return $wpdb->insert_id;
            }
        }
        return false;
    }

    public function getRemoteDestinationsByTaskId($task)
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        return $wpdb->get_results('SELECT remotedest.* FROM ' . $this->tableName('remote_dest') . ' remotedest, ' . $this->tableName('task_remote_dest') . ' tasklink WHERE tasklink.taskid = ' . (is_object($task) ? $task->id : (is_array($task) && isset($task['id']) ? $task['id'] : $task)) . ' AND remotedest.id = tasklink.remote_dest_id ORDER BY remotedest.type, remotedest.title', OBJECT);
    }

    public function getUsedRemoteDestinationsToCheck()
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        return $wpdb->get_results('SELECT DISTINCT remotedest.* FROM ' . $this->tableName('remote_dest') . ' remotedest, ' . $this->tableName('task_remote_dest') . ' tasklink WHERE remotedest.id = tasklink.remote_dest_id AND remotedest.dtsCheck < ' . (time() - (7 * 24 * 60)), OBJECT);
    }

    public function getRemoteDestinationsByWebsiteId($websiteId)
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        return $wpdb->get_results('SELECT remotedest.* FROM ' . $this->tableName('remote_dest') . ' remotedest, ' . $this->tableName('wp_remote_dest') . ' wplink WHERE wplink.wpid = ' . $websiteId . ' AND remotedest.id = wplink.remote_dest_id ORDER BY remotedest.type, remotedest.title', OBJECT);
    }

    public function getRemoteDestinationsForUser($userid = null)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $multiuser = apply_filters('mainwp_is_multi_user', false);
        if (($userid == null) && $multiuser)
        {
            global $current_user;
            $userid = $current_user->ID;
        }
        return $wpdb->get_results('SELECT * FROM ' . $this->tableName('remote_dest') . ($userid != null ? ' WHERE userid = ' . $userid : '') . ' ORDER BY type, title', OBJECT);
    }

    public function updateBackupTaskProgress($task_id, $wp_id, $values)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $wpdb->update($this->tableName('wp_remotebackup_progress'), $values, array('task_id' => $task_id, 'wp_id' => $wp_id));

        return $this->getBackupTaskProgress($task_id, $wp_id);
    }

    public function addBackupTaskProgress($task_id, $wp_id)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $values = array('task_id' => $task_id,
                'wp_id' => $wp_id,
                'last_run' => time(),
                'remoteDestinations' => '');

        if ($wpdb->insert($this->tableName('wp_remotebackup_progress'), $values))
        {
            return $this->getBackupTaskProgress($task_id, $wp_id);
        }

        return null;
    }

    public function getBackupTaskProgress($task_id, $wp_id)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        $progress = $wpdb->get_row('SELECT * FROM ' . $this->tableName('wp_remotebackup_progress') . ' WHERE task_id= ' . $task_id . ' AND wp_id = ' . $wp_id);

        if ($progress->remoteDestinations != '')
        {
            $progress->remoteDestinations = json_decode($progress->remoteDestinations, true);
        }

        return $progress;
    }

    protected function escape($data)
    {
        /** @var $wpdb wpdb */
        global $wpdb;

        if (function_exists('esc_sql')) return esc_sql($data);
        else return $wpdb->escape($data);
    }
}