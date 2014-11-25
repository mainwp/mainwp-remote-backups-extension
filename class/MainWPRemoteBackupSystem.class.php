<?php

class MainWPRemoteBackupSystem
{
    public $security_nonces;

    public function init()
    {
        add_action('mainwp_postprocess_backup_site', array(&$this, 'mainwp_postprocess_backup_site'), 10, 7);
        add_filter('mainwp_postprocess_backup_sites_feedback', array(&$this, 'mainwp_postprocess_backup_sites_feedback'), 10, 2);
        add_filter('mainwp_backuptask_column_destination', array(&$this, 'mainwp_backuptask_column_destination'), 10, 2);
        add_filter('mainwp_backuptask_remotedestinations', array(&$this, 'mainwp_backuptask_remotedestinations'), 10, 2);
        add_action('mainwp_remote_backup_extension_backup_upload_file', array(&$this,'mainwp_backup_upload_file'));
        add_action('mainwp_update_backuptask', array(&$this,'mainwp_update_backuptask'));
        add_action('mainwp_add_backuptask', array(&$this,'mainwp_add_backuptask'));
        add_action('mainwp_update_site', array(&$this,'mainwp_update_site'));

        $this->init_ajax();
    }

    public function init_ajax()
    {
        $this->addAction('mainwp_remote_dest_test', array(&$this, 'mainwp_remote_dest_test'));
        $this->addAction('mainwp_remote_dest_save', array(&$this, 'mainwp_remote_dest_save'));
        $this->addAction('mainwp_remote_dest_delete', array(&$this, 'mainwp_remote_dest_delete'));
    }

    function mainwp_backuptask_remotedestinations($remoteDestinations, $taskId)
    {
        if (!is_array($remoteDestinations)) $remoteDestinations = array();

        $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByTaskId($taskId);
        $remoteDestinations = array();
        foreach ($remote_destinations as $remote_destination)
        {
            $remoteDestinations[] = (object)array('id' => $remote_destination->id, 'title' => htmlentities($remote_destination->title), 'type' => self::getRemoteDestinationName($remote_destination->type));
        }

        return $remoteDestinations;
    }

    function mainwp_update_site($websiteId)
    {
        //Update backup information!
        MainWPRemoteBackupDB::Instance()->clearRemoteDestinationsFromWebsite($websiteId);
        if (isset($_POST['remote_destinations']) && is_array($_POST['remote_destinations']))
        {
            foreach ($_POST['remote_destinations'] as $remoteDestinationId)
            {
                MainWPRemoteBackupDB::Instance()->addRemoteDestinationToWebsite($remoteDestinationId, $websiteId);
            }
        }
    }

    function mainwp_add_backuptask($taskId)
    {
        if (isset($_POST['remote_destinations']) && is_array($_POST['remote_destinations']))
        {
            foreach ($_POST['remote_destinations'] as $remoteDestinationId)
            {
                MainWPRemoteBackupDB::Instance()->addRemoteDestinationToTask($remoteDestinationId, $taskId);
            }
        }
    }

    function mainwp_update_backuptask($taskId)
    {
        MainWPRemoteBackupDB::Instance()->clearRemoteDestinationsFromTask($taskId);
        if (isset($_POST['remote_destinations']) && is_array($_POST['remote_destinations']))
        {
            foreach ($_POST['remote_destinations'] as $remoteDestinationId)
            {
                MainWPRemoteBackupDB::Instance()->addRemoteDestinationToTask($remoteDestinationId, $taskId);
            }
        }
    }

    function mainwp_backuptask_column_destination($output, $taskId)
    {
        $remoteDestinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByTaskId($taskId);

        $ftpEnabled = false;
        $ftpNames = '';
        $dropboxEnabled = false;
        $dropboxNames = '';
        $amazonEnabled = false;
        $amazonNames = '';
        $copyEnabled = false;
        $copyNames = '';
        if (empty($output)) $output = '';
        foreach ($remoteDestinations as $remoteDestination)
        {
            if ($remoteDestination->type == 'ftp')
            {
                $ftpEnabled = true;
                if ($ftpNames != '') $ftpNames .= ', ';
                $ftpNames .= $remoteDestination->title;
            }
            else if ($remoteDestination->type == 'amazon')
            {
                $amazonEnabled = true;
                if ($amazonNames != '') $amazonNames .= ', ';
                $amazonNames .= $remoteDestination->title;
            }
            else if ($remoteDestination->type == 'dropbox' || $remoteDestination->type == 'dropbox2')
            {
                $dropboxEnabled = true;
                if ($dropboxNames != '') $dropboxNames .= ', ';
                $dropboxNames .= $remoteDestination->title;
            }
            else if ($remoteDestination->type == 'copy')
            {
                $copyEnabled = true;
                if ($copyNames != '') $copyNames .= ', ';
                $copyNames .= $remoteDestination->title;
            }
        }

        $output .= ($ftpEnabled ? '<br/>FTP: '. $ftpNames : '');
        $output .= ($amazonEnabled ? '<br/>AMAZON: '. $amazonNames : '');
        $output .= ($dropboxEnabled ? '<br/>DROPBOX: '. $dropboxNames : '');
        $output .= ($copyEnabled ? '<br/>COPY: '. $copyNames : '');

        return $output;
    }

    protected function addAction($action, $callback)
    {
        add_action('wp_ajax_' . $action, $callback);
        $this->addSecurityNonce($action);
    }

    protected function addSecurityNonce($action)
    {
        if (!is_array($this->security_nonces)) $this->security_nonces = array();

        if (!function_exists('wp_create_nonce')) include_once(ABSPATH . WPINC . '/pluggable.php');
        $this->security_nonces[$action] = wp_create_nonce($action);
    }

    function secure_request($action, $query_arg = 'security')
    {
        if (!$this->check_security($action, $query_arg)) die(json_encode(array('error' => 'Invalid request')));
    }

    function check_security($action = -1, $query_arg = 'security')
    {
        if ($action == -1) return false;

        $adminurl = strtolower(admin_url());
        $referer = strtolower(wp_get_referer());
        $result = isset($_REQUEST[$query_arg]) ? wp_verify_nonce($_REQUEST[$query_arg], $action) : false;
        if (!$result && !(-1 == $action && strpos($referer, $adminurl) === 0))
        {
            return false;
        }

        return true;
    }

    //Normal flow
    static function mainwp_remote_backup_extension_cronremotedestinationcheck_action()
    {
        //Get used remote destinations that require a check
        $remoteDestinationsToCheck = MainWPRemoteBackupDB::Instance()->getUsedRemoteDestinationsToCheck();
        if (count($remoteDestinationsToCheck) == 0) return;

        $errors = null;
        foreach ($remoteDestinationsToCheck as $remoteDestinationToCheck)
        {
            $remoteDestination = MainWPRemoteDestination::buildRemoteDestination($remoteDestinationToCheck);
            try
            {
                if (!$remoteDestination->test())
                {
                    throw new Exception('Connection test failed.');
                }
            }
            catch (Exception $e)
            {
                if ($errors == null) $errors = array();
                if (!isset($errors[$remoteDestination->getUserID()])) $errors[$remoteDestination->getUserID()] = array();
                $errors[$remoteDestination->getUserID()][] = $remoteDestination->getType() . ' - ' . $remoteDestination->getTitle() . ' - Error: '. $e->getMessage();
            }
        }

        if (count($errors) > 0)
        {
            foreach ($errors as $userId => $userErrors)
            {
                $output = '';
                foreach ($userErrors as $userError)
                {
                    $output .= $userError . '<br />';
                }

                do_action('mainwp_notify_user', $userId, 'MainWP Alert! ' . count($userErrors) . ' remote destination' . (count($userErrors) > 1 ? 's' : '') . ' failed the connection test.', $output);
            }
        }
    }

    function mainwp_remote_dest_test()
    {
        $this->secure_request('mainwp_remote_dest_test');

        $output = array();

        try
        {
            if (!isset($_POST['fields'])) throw new Exception('Please fill in all the fields');
            $taskType = $_POST['taskType'];
            $remoteDestinationFromDB = null;
            if (isset($_POST['taskId']) && MainWPRemoteDestinationUtility::ctype_digit($_POST['taskId']))
            {
                $remoteDestinationFromDB = MainWPRemoteBackupDB::Instance()->getRemoteDestinationById($_POST['taskId']);
            }

            $remoteDestination = null;
            if ($remoteDestinationFromDB == null)
            {
                //Testing a new task!
                $remoteDestination = MainWPRemoteDestination::buildRemoteDestination((object)array('type' => $taskType));
            }
            else
            {
                MainWPRemoteDestinationUtility::can_edit_remotedestination($remoteDestinationFromDB);
                $remoteDestination = MainWPRemoteDestination::buildRemoteDestination($remoteDestinationFromDB);
            }

            if ($remoteDestination->test($_POST['fields']))
            {
                $output['information'] = self::getRemoteDestinationName($remoteDestination->getType()) . __(': Connection test was successful.','mainwp');
            }
        }
        catch (Exception $e)
        {
            $output['error'] = __('Error: ','mainwp') . $e->getMessage();
        }

        die(json_encode($output));
    }


    function mainwp_remote_dest_save()
    {
        $this->secure_request('mainwp_remote_dest_save');
        $output = array();

        try
        {
            $taskType = $_POST['taskType'];

            if (!isset($_POST['fields'])) throw new Exception('Invalid request');
            $remoteDestinationFromDB = null;
            if (isset($_POST['taskId']) && MainWPRemoteDestinationUtility::ctype_digit($_POST['taskId']))
            {
                $remoteDestinationFromDB = MainWPRemoteBackupDB::Instance()->getRemoteDestinationById($_POST['taskId']);
            }

            $remoteDestination = null;
            if ($remoteDestinationFromDB == null && isset($_POST['taskId']))
            {
                //Testing a new task!
                throw new Exception('Remote destination not found.');
            }
            else if ($remoteDestinationFromDB == null)
            {
                $remoteDestination = MainWPRemoteDestination::buildRemoteDestination(array('type' => $taskType));
            }
            else
            {
                MainWPRemoteDestinationUtility::can_edit_remotedestination($remoteDestinationFromDB);
                $remoteDestination = MainWPRemoteDestination::buildRemoteDestination($remoteDestinationFromDB);
            }

            $remoteDestInfo = $remoteDestination->save($_POST['fields']);
            $output['information'] = self::getRemoteDestinationName($remoteDestination->getType()) . ': Saved successfully.';
            if (is_object($remoteDestInfo))
            {
                $remoteDestObj = MainWPRemoteDestination::buildRemoteDestination($remoteDestInfo);

                ob_start();
                ?>
                <div class="backup_destination_cont settings">
                     <input type="hidden" name="remote_destinationstemplate[]" class="remote_destination_id" value="<?php echo $remoteDestInfo->id; ?>" title="<?php echo htmlentities($remoteDestInfo->title); ?>" destination_type="<?php echo self::getRemoteDestinationName($remoteDestInfo->type); ?>"/>
                     <input type="hidden" name="remote_destination_type[]" class="remote_destination_type" value="<?php echo $remoteDestInfo->type; ?>"/>
                     <div class="backup_destination_type" style="background-image: url('<?php echo plugins_url('images/'.$remoteDestInfo->type.'.png', dirname(__FILE__)) ?>')"><?php echo self::getRemoteDestinationName($remoteDestInfo->type); ?></div>
                     <div class="backup_destination_title"><?php echo $remoteDestInfo->title; ?></div>
                     <div class="backup_destination_settings"><?php do_action('mainwp_renderImage', 'images/icons/mainwp-settings.png', '', 'backup_destination_settings_open', 22); ?></div>
                     <div class="backup_destination_settings_panel">
                         <div class="clear"></div>
                         <?php $remoteDestObj->buildUpdateForm(); ?>
                         <br />
                         <a href="#" class="button backup_destination_test"><span class="text"><?php _e('Test Settings','mainwp'); ?></span> <span class="loading"><?php do_action('mainwp_renderImage', 'images/loading.gif', 'Loading', ''); ?></span></a> <input type="button" class="button-primary backup_destination_save" value="<?php _e('Save Settings','mainwp'); ?>" /> <input type="button" class="button backup_destination_delete" value="<?php _e('Delete Destination','mainwp'); ?>" />
                         <div class="clear"></div>
                     </div>
                 </div>
                <?php
                $output['newEl'] = ob_get_contents();
                ob_end_clean();
            }

        }
        catch (Exception $e)
        {
            $output['error'] = 'Error: ' . $e->getMessage();
        }

        die(json_encode($output));
    }

    function mainwp_remote_dest_delete()
    {
        $this->secure_request('mainwp_remote_dest_delete');
        $output = array();

        try
        {
            $remoteDestinationFromDB = null;
            if (isset($_POST['taskId']) && MainWPRemoteDestinationUtility::ctype_digit($_POST['taskId']))
            {
                $remoteDestinationFromDB = MainWPRemoteBackupDB::Instance()->getRemoteDestinationById($_POST['taskId']);
            }

            if ($remoteDestinationFromDB == null)
            {
                //Testing a new task!
                throw new Exception('Remote destination not found.');
            }
            MainWPRemoteDestinationUtility::can_edit_remotedestination($remoteDestinationFromDB);

            MainWPRemoteBackupDB::Instance()->removeRemoteDestination($remoteDestinationFromDB);
            $output['information'] = self::getRemoteDestinationName($remoteDestinationFromDB->type) . __(': Removed successfully.','mainwp');
        }
        catch (Exception $e)
        {
            $output['error'] = __('Error: ','mainwp') . $e->getMessage();
        }

        die(json_encode($output));
    }

    function mainwp_backup_upload_file()
    {
        @ignore_user_abort(true);
        @set_time_limit(0);
        $mem =  '512M';
        @ini_set('memory_limit', $mem);
        @ini_set('max_execution_time', 0);

        $pFile = $_POST['file'];
        $pSubfolder = $_POST['subfolder'];
        $pType = $_POST['type'];
        $pRemoteDestination = $_POST['remote_destination'];
        $pRegexFile = $_POST['regexfile'];
        $pSiteId = (isset($_POST['siteId']) ? $_POST['siteId'] : null);
        $pUnique = $_POST['unique'];

        $pRemoteDestination = MainWPRemoteBackupDB::Instance()->getRemoteDestinationById($pRemoteDestination);

        $result = array();
        try
        {
            $remoteDestination = MainWPRemoteDestination::buildRemoteDestination($pRemoteDestination);
            session_write_close();
            $result['type'] = $remoteDestination->getType();
            $result['title'] = $remoteDestination->getTitle();

            $website = ($pSiteId != null ? $pSiteId : null);

            if ($remoteDestination->upload($pFile, $pType, $pSubfolder, $pRegexFile, $website, $pUnique))
            {
                $result['result'] = 'success';
            }
        }
        catch (Exception $e)
        {
            $result['error'] = 'Error: ' . $e->getMessage();
        }

        die(json_encode(array('result' => $result)));
    }

    function mainwp_postprocess_backup_sites_feedback($output, $unique)
    {
        if (session_id() == '') session_start();
        $newOutput = $_SESSION['mainwp_remotebackup_extension_' . $unique];
        unset($_SESSION['mainwp_remotebackup_extension_' . $unique]);

        if (!is_array($output)) $output = array();

        if (is_array($newOutput))
        {
            foreach ($newOutput as $key => $value)
            {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    function mainwp_postprocess_backup_site($localBackupFile, $what, $subfolder, $regexBackupFile, $website, $taskId, $unique)
    {
        //todo: RS: add check based on task in combination with a unique identifier, indentifying this run - task last run timestamp ?
        $remote_destinations = MainWPRemoteBackupDB::Instance()->getRemoteDestinationsByTaskId($taskId);
        if (!is_array($remote_destinations)) $remote_destinations = array();

        foreach ($remote_destinations as $idx => $remote_destination)
        {
            if (is_object($remote_destination)) continue;

            $remote_destinations[$idx] = MainWPRemoteBackupDB::Instance()->getRemoteDestinationById($remote_destination);
        }

        $backupTaskProgress = MainWPRemoteBackupDB::Instance()->getBackupTaskProgress($taskId, $website->id);
        if (empty($backupTaskProgress))
        {
            $backupTaskProgress = MainWPRemoteBackupDB::Instance()->addBackupTaskProgress($taskId, $website->id);
        }
        else
        {
            if ($backupTaskProgress->last_run < $unique)
            {
                $backupTaskProgress = MainWPRemoteBackupDB::Instance()->updateBackupTaskProgress($taskId, $website->id, array('last_run' => time(), 'remoteDestinations' => json_encode(array())));
            }
        }

        $backup_result = isset($_SESSION['mainwp_remotebackup_extension_' . $unique]) && is_array($_SESSION['mainwp_remotebackup_extension_' . $unique]) ? $_SESSION['mainwp_remotebackup_extension_' . $unique] : array();
        foreach ($remote_destinations as $remote_destination_from_db)
        {
            $remoteDestination = null;
            try
            {
                $remoteDestination = MainWPRemoteDestination::buildRemoteDestination($remote_destination_from_db);

                $remoteDestinations = array();
                if (!empty($backupTaskProgress))
                {
                    if ($backupTaskProgress->last_run >= $unique)
                    {
                        $remoteDestinations = $backupTaskProgress->remoteDestinations;
                    }
                }

                try
                {
                    if (isset($remoteDestinations[$remote_destination_from_db->id]) && $remoteDestinations[$remote_destination_from_db->id] === true || $remoteDestinations[$remote_destination_from_db->id] > 3) continue; //already uploaded


                    if (isset($remoteDestinations[$remote_destination_from_db->id])) $remoteDestinations[$remote_destination_from_db->id]++;
                    else $remoteDestinations[$remote_destination_from_db->id] = 1;
                    $backupTaskProgress = MainWPRemoteBackupDB::Instance()->updateBackupTaskProgress($taskId, $website->id, array('remoteDestinations' => json_encode($remoteDestinations)));

                    session_write_close();
                    if ($remoteDestination->upload($localBackupFile, $what, $subfolder, $regexBackupFile, $website->id, null, ($remoteDestinations[$remote_destination_from_db->id] != 1)))
                    {
                        $backup_result[$remoteDestination->getType()] = 'success';
                    }
                }
                catch (Exception $e)
                {
                    $backup_result[$remote_destination_from_db->type] = 'Error: ' . $e->getMessage();
                }

                $remoteDestinations[$remote_destination_from_db->id] = true;
                $backupTaskProgress = MainWPRemoteBackupDB::Instance()->updateBackupTaskProgress($taskId, $website->id, array('remoteDestinations' => json_encode($remoteDestinations)));
            }
            catch (Exception $e)
            {
                $backup_result[$remote_destination_from_db->type] = 'Error: ' . $e->getMessage();
            }

            if (session_id() == '') session_start();
            $_SESSION['mainwp_remotebackup_extension_' . $unique] = $backup_result;
        }
    }

    public static function getRemoteDestinationName($type)
    {
        if ($type == 'amazon') return 'Amazon';
        else if ($type == 'ftp') return 'FTP';
        else if ($type == 'dropbox') return 'Dropbox';
        else if ($type == 'dropbox2') return 'Dropbox';
        else if ($type == 'copy') return 'Copy.com';

        return 'Unknown';
    }
}