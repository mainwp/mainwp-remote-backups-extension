<?php
class MainWPRemoteDestinationCopy extends MainWPRemoteDestination
{
    public static $consumerKey = 'enOFeoRVQyEyBJFASkO0S63r0pa2zVop';
    public static $consumerSecret = 'jMYtKVEm1NBSyt6pGrp4LC3topfDnT1XgneMimgfsS8eDjpA';

    public function __construct($pObject = array('type' => 'copy'))
    {
        parent::__construct($pObject);
    }

    public function getDir()
    {
        return $this->object->field1;
    }

    public function getToken()
    {
        return $this->object->field2;
    }

    public function getTokenSecret()
    {
        return $this->object->field3;
    }

    /**
     * @param $copyApi CopyAPI
     * @param $dir
     * @param $regex
     * @param $exclude
     * @param $backupFiles
     * @return array
     */
    private function listFiles($copyApi, $dir, $regex, $exclude, $backupFiles)
    {
        $files = array();

        $metaData = $copyApi->getMeta($dir);
        if ($metaData == null) return $files;
        if (($metaData['type'] != 'copy') && ($metaData['type'] != 'dir')) return $files;

        $contents = $metaData['children'];
        foreach ($contents as $content)
        {
            if (($content['type'] == 'copy') || ($content['type'] == 'dir'))
            {
                $inFiles = $this->listFiles($copyApi, $content['path'], $regex, $exclude, $backupFiles);
                foreach ($inFiles as $inFile)
                {
                    $files[] = $inFile;
                }
            }
            else
            {
                $addFile = false;

                if (!$addFile && is_array($backupFiles))
                {
                    foreach ($backupFiles as $key => $backupFile)
                    {
                        $revision = $backupFile[0];
                        $file = $backupFile[1];

                        if (($file == $content['name']) && ($revision == $content['revision']))
                        {
                            $addFile = true;
                            break;
                        }
                    }
                }

                if ($addFile)
                {
                    $files[] = array('m' => $content['date_last_synced'], 'p' => $content['path'], 'rev' => $content['revision']);
                }
            }
        }

        return $files;
    }

    /**
     * @param $copyApi CopyAPI
     * @param $pRemoteFilename
     * @param $pRegexFile
     * @param $backupFiles
     * @param null $dir
     * @return mixed
     */
    public function limitFiles($copyApi, $pRemoteFilename, $pRegexFile, &$backupFiles, $dir = null)
    {
        $maxBackups = get_option('mainwp_backupOnExternalSources');
        if ($maxBackups === false) $maxBackups = 1;

        if ($maxBackups == 0) return $backupFiles;
        $maxBackups--;

        $filesToRemove = $this->listFiles($copyApi, $this->getDir(), $pRegexFile, $pRemoteFilename, $backupFiles);
        if (count($filesToRemove) <= $maxBackups) return $backupFiles;

        $filesToRemove = MainWPRemoteDestinationUtility::sortmulti($filesToRemove, 'm', 'desc');
        for ($i = $maxBackups; $i < count($filesToRemove); $i++)
        {
            $copyApi->delete($filesToRemove[$i]['p']);

            foreach ($backupFiles as $key => $backupFile)
            {
                if (!is_array($backupFile))
                {
                    //Legacy code..
                    if ($backupFile == basename($filesToRemove[$i]['p'])) unset($backupFiles[$key]);
                }
                else
                {
                    if (($backupFile[0] == $filesToRemove[$i]['rev']) && ($backupFile[1] == basename($filesToRemove[$i]['p'])))
                    {
                        unset($backupFiles[$key]);
                    }
                }
            }
        }

        return $backupFiles;
    }

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pUnique = null)
    {
        $dir = $this->getDir();
        if ($pSubfolder != '')
        {
            if ($dir == '')
            {
                $dir = $pSubfolder;
            }
            else
            {
                if (substr($dir, -1) != '/') $dir .= '/';
                $dir .= $pSubfolder;
            }
        }

        $dir = trim($dir, '/');


        include_once mainwp_remote_backup_extension_dir() . 'Copy/CopyAPI.php';

        $copyAPI = new CopyAPI(self::$consumerKey, self::$consumerSecret, $this->getToken(), $this->getTokenSecret());


        //First we check if the file exists, if it does, we add a (1) to the end.
        $pathInfo = pathinfo(basename($pLocalbackupfile));
        $ext = '.' . $pathInfo['extension'];
        $file = $pathInfo['filename'];
        $remoteFilename = $file . $ext;
        $i = null;
        try
        {
            $fileExists = $copyAPI->getMeta($dir . '/' . $remoteFilename);

            $i = 1;
            while ($fileExists != null)
            {
                $remoteFilename = $file . ' (' . $i . ')' . $ext;
                $fileExists = $copyAPI->getMeta($dir . '/' . $remoteFilename);

                $i++;
            }
        }
        catch (Exception $e)
        {
        }


        $uploadTracker = null;
        if ($pUnique != null) $uploadTracker = new MainWPRemoteDestinationUploadTracker($pUnique);

        MainWPRemoteDestinationUtility::endSession();
        $copyAPI->setUploadTracker($uploadTracker);
        $copyAPI->uploadFile($pLocalbackupfile, $dir, $remoteFilename);

        $metaData = $copyAPI->getMeta($dir . '/' . $remoteFilename);

        $newFile = array($metaData['revision'], $metaData['name']);
        $backupsTaken = array();

        if ($pSiteId != null)
        {
            $backups = MainWPRemoteBackupDB::Instance()->getRemoteBackups($pSiteId, $this->getType());
            $backups = is_object($backups) ? json_decode($backups->backups, true) : null;

            if (!is_array($backups)) $backups = array();

            if (isset($backups[$pType]) && is_array($backups[$pType]))
            {
                $backupsTaken = $backups[$pType];
            }

            $backupsTaken = $this->limitFiles($copyAPI, $remoteFilename, $pRegexFile, $backupsTaken);

            array_push($backupsTaken, $newFile);
            $backups[$pType] = $backupsTaken;

            MainWPRemoteBackupDB::Instance()->updateRemoteBackups($pSiteId, $this->getType(), $backups);
        }

        return true;
    }

    public function buildUpdateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="<?php echo $this->object->title; ?>" /></td></tr>
                <tr><td><?php _e('Directory:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="directory" value="<?php echo $this->getDir(); ?>" /></td></tr>
                <tr>
                    <td colspan="2">
                        <input class="remote_destination_update_field" type="hidden" name="token" value="<?php echo $this->getToken(); ?>" />
                        <input class="remote_destination_update_field" type="hidden" name="token_secret" value="<?php echo $this->getTokenSecret(); ?>" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_new_token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_new_token_secret" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="new_token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="new_token_secret" value="" />
                        <a href="#" target="_blank" style="display: none;" class="remote_destination_connect_to_copy_link"></a>
                        <div class="mainwp_info-box"><?php _e('The Copy.com connections opens a new window, if you do not see it be sure to turn off your popup blocker.','mainwp'); ?></div>
                        <input type="button" class="button-primary remote_destination_reconnect_to_copy" value="<?php _e('Re-authenticate Copy.com','mainwp'); ?>" />
                        <input type="button" class="button-primary remote_destination_reconnect_to_copy_authorized" value="<?php _e('Yes I have authorized MainWP to Copy.com','mainwp'); ?>" style="display: none;" />
                    </td>
                </tr>
            </table>
            <?php
    }

    public function buildCreateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="<?php _e('New Copy.com Destination','mainwp'); ?>" /></td></tr>
                <tr><td><?php _e('Directory:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="directory" value="" /></td></tr>
                <tr>
                    <td colspan="2">
                        <input class="remote_destination_update_field" type="hidden" name="token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="token_secret" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_token_secret" value="" />
                        <a href="#" target="_blank" style="display: none;" class="remote_destination_connect_to_copy_link"></a>
                        <div class="mainwp_info-box"><?php _e('The Copy.com connections opens a new window, if you do not see it be sure to turn off your popup blocker.','mainwp'); ?></div>
                        <input type="button" class="button-primary remote_destination_connect_to_copy" value="<?php _e('Connect to Copy.com','mainwp'); ?>" />
                        <input type="button" class="button-primary remote_destination_connect_to_copy_authorized" value="<?php _e('Yes I have authorized MainWP to Copy.com','mainwp'); ?>" style="display: none;" />
                    </td>
                </tr>
            </table>
            <?php
    }

    public function test($fields = null)
    {
        $token = null;
        $tokenSecret = null;
        if ($fields == null)
        {
            $token = $this->getToken();
            $tokenSecret = $this->getTokenSecret();
        }
        else if (isset($fields['new_token']) && $fields['new_token'] != '')
        {
            $token = $fields['new_token'];
            $tokenSecret = $fields['new_token_secret'];
        }
        else if (isset($fields['token']) && $fields['token'] != '')
        {
            $token = $fields['token'];
            $tokenSecret = $fields['token_secret'];
        }

        if (($token == null) || ($token == '') || ($tokenSecret == null) || ($tokenSecret == ''))  throw new Exception('Tokens not set, please re-authenticate.');

        include_once mainwp_remote_backup_extension_dir() . 'Copy/CopyAPI.php';
        $copyApi = new CopyAPI(self::$consumerKey, self::$consumerSecret, $token, $tokenSecret);
        $userInfo = $copyApi->getUserInfo();

        if (is_array($userInfo) && isset($userInfo['id'])) return true;

        throw new Exception('An undefined error occured, please re-authenticate.');
    }

    public function save($fields = array())
    {
        $values = array('title' => $fields['title'],
                        'field1' => $fields['directory'],
                        'field2' => (isset($fields['new_token']) && ($fields['new_token'] != '')) ? $fields['new_token'] : $fields['token'],
                        'field3' => (isset($fields['new_token_secret']) && ($fields['new_token_secret'] != '')) ? $fields['new_token_secret'] : $fields['token_secret']);

        if (isset($this->object->id))
        {
            return MainWPRemoteBackupDB::Instance()->updateRemoteDestination($this->object->id, $values);
        }
        else
        {
            return MainWPRemoteBackupDB::Instance()->addRemoteDestinationWithValues($this->object->type, $values);
        }
    }

    public function showTestButton()
    {
        return false;
    }

    public function showSaveButton()
    {
        return false;
    }

    public static function init()
    {
        add_action('wp_ajax_mainwp_remotedestination_copy_connect', array(__CLASS__, 'mainwp_remotedestination_copy_connect'));
        add_action('wp_ajax_mainwp_remotedestination_copy_authorize', array(__CLASS__, 'mainwp_remotedestination_copy_authorize'));
    }

    public static function mainwp_remotedestination_copy_connect()
    {
        require_once(mainwp_remote_backup_extension_dir() . 'OAuth.php');

        $signature_method = new SN_OAuthSignatureMethod_HMAC_SHA1();
        $params = array();
        $params['oauth_callback'] = admin_url('admin.php?hideall=1&page=MainWPRemoteDestination&');

        $consumer = new SN_OAuthConsumer(self::$consumerKey, self::$consumerSecret, NULL);

        //Request a token from google
        $req_req = SN_OAuthRequest::from_consumer_and_token($consumer, NULL, 'GET', 'https://api.copy.com/oauth/request', $params);
        $req_req->sign_request($signature_method, $consumer, NULL);

        // Set up curl and have it get the token to use for the authenication call
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $req_req->to_url());

        // This tells curl to return the response as one string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Run curl and grab the output and the return code
        // This is the execution of step 1
        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200)
        {
            // If the call was good parse out the response parameters into an array
            $access_params = array();
            $param_pairs = explode('&', $return);
            foreach ($param_pairs as $param_pair)
            {
                if (trim($param_pair) == '')
                {
                    continue;
                }
                list($key, $value) = explode('=', $param_pair);
                $access_params[$key] = urldecode($value);
            }

            die(json_encode(array('requestToken' => $access_params, 'authorizeUrl' => 'https://www.copy.com/applications/authorize?oauth_token=' . urlencode($access_params['oauth_token']))));
        }
        else
        {
            die(json_encode(array('error' => 'Invalid response. ' . $return)));
        }
    }

    public static function mainwp_remotedestination_copy_authorize()
    {
        if (session_id() == '') session_start();
        require_once(mainwp_remote_backup_extension_dir() . 'OAuth.php');

        $oauth_token = $_POST['token'];
        $oauth_token_secret = $_POST['token_secret'];
        $oauth_verifier = $_SESSION[$_POST['token']];

        $signature_method = new SN_OAuthSignatureMethod_HMAC_SHA1();
        $params = array();
        $params['oauth_verifier'] = $oauth_verifier;
        $consumer = new SN_OAuthConsumer(self::$consumerKey, self::$consumerSecret, NULL);
        $final_consumer = new SN_OAuthConsumer($oauth_token, $oauth_token_secret);
        $acc_req = SN_OAuthRequest::from_consumer_and_token($consumer, $final_consumer, 'GET', 'https://api.copy.com/oauth/access', $params);
        $acc_req->sign_request($signature_method, $consumer, $final_consumer);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $acc_req->to_url());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200)
        {
            // If the call was good parse out the response parameters into an array
            $access_params = array();
            $param_pairs = explode('&', $return);
            foreach ($param_pairs as $param_pair)
            {
                if (trim($param_pair) == '')
                {
                    continue;
                }
                list($key, $value) = explode('=', $param_pair);
                $access_params[$key] = urldecode($value);
            }
            die(json_encode(array('accessToken' => $access_params)));
        }
        else
        {
            die(json_encode(array('error' => 'Invalid response. ' . $return)));
        }
    }
}