<?php
class MainWPRemoteDestinationDropbox2 extends MainWPRemoteDestination
{
//    protected static $consumerKey = '5h8hccq46wag6gi';
//    protected static $consumerSecret = 'u2wb83i8gbx80i3';
    protected static $consumerKey = 'jvb3kfw2ez8kdfl';
    protected static $consumerSecret = 'drwcf6k6kp40tep';

    public function __construct($pObject = array('type' => 'dropbox2'))
    {
        parent::__construct($pObject);
    }

    public function getDir()
    {
        return empty($this->object) ? null : $this->object->field1;
    }

    public function getToken()
    {
        return empty($this->object) ? null : $this->object->field2;
    }

    public function getTokenSecret()
    {
        return empty($this->object) ? null : $this->object->field3;
    }

    public function getIdentifier()
    {
        return empty($this->object) ? null : $this->object->id;
    }

    /**
     * @param $dropbox API
     * @param $dir
     * @param $regex
     * @param $exclude
     * @param $backupFiles
     * @return array
     */
    private function listFiles($dropbox, $dir, $regex, $exclude, $backupFiles)
    {
        $files = array();

        $metaData = $dropbox->metadata($dir);
        $contents = $metaData['body']->contents;

        foreach ($contents as $content)
        {
            if ($content->is_dir == '1')
            {
                $inFiles = $this->listFiles($dropbox, $content->path, $regex, $exclude, $backupFiles);
                foreach ($inFiles as $inFile)
                {
                    $files[] = $inFile;
                }
            }
            else
            {
                $addFile = false;
                if (($exclude != basename($content->path)) && (preg_match('/' . $regex . '/', basename($content->path))))
                {
                    $addFile = true;
                }

                if (!$addFile && is_array($backupFiles))
                {
                    foreach ($backupFiles as $key => $backupFile)
                    {
                        if (!is_array($backupFile))
                        {
                            //Legacy code.. We did not yet save an array with the revision!
                            if ($backupFile == basename($content->path))
                            {
                                $addFile = true;
                                break;
                            }
                        }
                        else
                        {
                            $revision = $backupFile[0];
                            $file = $backupFile[1];

                            if (($file == basename($content->path)) && ($revision == $content->revision))
                            {
                                $addFile = true;
                                break;
                            }
                        }
                    }
                }

                if ($addFile)
                {
                    $files[] = array('m' => $content->modified, 'p' => $content->path, 'rev' => $content->revision);
                }
            }
        }

        return $files;
    }

    /**
     * @param $dbxClient \Dropbox\Client
     * @param $pRemoteFilename
     * @param $pRegexFile
     * @param $backupFiles
     * @param null $dir
     * @return array
     */
    public function limitFiles($dbxClient, $pRemoteFilename, $pRegexFile, &$backupFiles, $dir = null)
    {
        $maxBackups = get_option('mainwp_backupOnExternalSources');
        if ($maxBackups === false) $maxBackups = 1;

        if ($maxBackups == 0) return $backupFiles;
        $maxBackups--;

        $filesToRemove = array();
        if (is_array($backupFiles))
        {
            $newBackupFiles = $backupFiles;
            foreach ($backupFiles as $key => $backupFile)
            {
                try
                {
                    $added = false;
                    $resp = $dbxClient->searchFileNames('/', $backupFile[1]); //'full-blog.mainwp.com-01-21-2014-1390263582.zip');

                    foreach ($resp as $result)
                    {
                        if ($result['revision'] == $backupFile[0])
                        {
                            $added = true;
                            $filesToRemove[] = array('m' => $result['modified'], 'p' => $result['path'], 'rev' => $result['revision']);
                        }
                    }

                    if (!$added)
                    {
                        unset($newBackupFiles[$key]);
                    }
                }
                catch (Exception $e)
                {

                }
            }
            $backupFiles = $newBackupFiles;
        }

//        $filesToRemove = $this->listFiles($dropbox, $this->getDir(), $pRegexFile, $pRemoteFilename, $backupFiles);
        if (count($filesToRemove) <= $maxBackups) return $backupFiles;

        $filesToRemove = MainWPRemoteDestinationUtility::sortmulti($filesToRemove, 'm', 'desc');
        for ($i = $maxBackups; $i < count($filesToRemove); $i++)
        {
            $dbxClient->delete($filesToRemove[$i]['p']);

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

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pTaskId = null, $pUnique = null, $pTryResume = false)
    {
        $this->checkOAuth2Upgrade();

        $dbxClient = new \Dropbox\Client($this->getToken(), "PHP-Example/1.0");
//        $dbxClient->uploadFileChunked($path, \Dropbox\WriteMode::add(), fopen($realfile, 'rb'));

        $dir = $this->getDir();
        if ($pSubfolder !=  '')
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
        if (substr($dir, -1) != '/') $dir .= '/';

//            $dropbox->putFile($pLocalbackupfile, basename($pLocalbackupfile), $dir, true);
        //read file with chunks & upload..

        //First we check if the file exists, if it does, we add a (1) to the end.
        $pathInfo = pathinfo(basename($pLocalbackupfile));
        $ext = '.' . $pathInfo['extension'];
        $file = $pathInfo['filename'];
        $remoteFilename = $file . $ext;
        $i = null;
        try
        {
            $testDir = $dir . $remoteFilename;
            if (substr($testDir, 0, 1) != '/') $testDir = '/' . $testDir;
            $fileExists = $dbxClient->getMetadata($testDir);

            if (!$pTryResume)
            {
                if (!isset($fileExists['bytes'])) throw new Exception('Not found');

                $i = 1;
                while ($fileExists)
                {
                    $remoteFilename = $file . ' (' . $i . ')' . $ext;
                    $testDir = $dir . $remoteFilename;
                    if (substr($testDir, 0, 1) != '/') $testDir = '/' . $testDir;
                    $fileExists = $dbxClient->getMetadata($testDir);
                    if (!isset($fileExists['bytes'])) throw new Exception('Not found');

                    $i++;
                }
            }
        }
        catch (Exception $e)
        {
            //File not found, so we are good to go!
        }

        $offset = 0;
        if ($pTryResume && isset($fileExists['bytes']))
        {
            $offset = $fileExists['bytes'];
        }


        if ($pUnique != null)
        {
            $uploadTracker = new MainWPRemoteDestinationUploadTracker($pUnique);
            $uploadTracker->setStartOffset($offset);
            $dbxClient->setTracker($uploadTracker);
        }

        MainWPRemoteDestinationUtility::endSession();
        @set_time_limit(0);
        $mem =  '512M';
        @ini_set('memory_limit', $mem);
        @ini_set('max_execution_time', 0);
        $newDir = $dir . $remoteFilename;
        if (substr($newDir, 0, 1) != '/') $newDir = '/' . $newDir;

        $inStream = fopen($pLocalbackupfile, 'rb');
        //todo: work with offset?
        $result = $dbxClient->uploadFileChunked($newDir, \Dropbox\WriteMode::add(), $inStream, null, 2097152);

        //$result = $dropbox->chunkedUpload($pLocalbackupfile, $remoteFilename, $dir, true, $offset);

        $newFile = array($result['revision'], $remoteFilename);

        $backupsTaken = array();

        if ($pSiteId != null)
        {
            $backups = MainWPRemoteBackupDB::Instance()->getRemoteBackups($pSiteId, $this->getType(), $this->getIdentifier());
            $backups = is_object($backups) ? json_decode($backups->backups, true) : null;

            if (!is_array($backups)) $backups = array();

            if (isset($backups[$pType]) && is_array($backups[$pType]))
            {
                $backupsTaken = $backups[$pType];
            }

            $backupsTaken = $this->limitFiles($dbxClient, $remoteFilename, $pRegexFile, $backupsTaken);

            array_push($backupsTaken, $newFile);
            $backups[$pType] = $backupsTaken;

            MainWPRemoteBackupDB::Instance()->updateRemoteBackups($pSiteId, $this->getType(), $this->getIdentifier(), $backups);
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
                        <a href="#" target="_blank" style="display: none;" class="remote_destination_connect_to_dropbox_link"></a>
                        <div class="mainwp_info-box"><?php _e('The Dropbox connections opens a new window, if you do not see it be sure to turn off your popup blocker.','mainwp'); ?></div>
                        <input type="button" class="button-primary remote_destination_reconnect_to_dropbox" value="<?php _e('Re-authenticate Dropbox','mainwp'); ?>" />
                        <input type="button" class="button-primary remote_destination_reconnect_to_dropbox_authorized" value="<?php _e('Yes I have authorized MainWP to Dropbox','mainwp'); ?>" style="display: none;" />
                    </td>
                </tr>
            </table>
            <?php
    }

    public function buildCreateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="<?php _e('New Dropbox Destination','mainwp'); ?>" /></td></tr>
                <tr><td><?php _e('Directory:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="directory" value="" /></td></tr>
                <tr>
                    <td colspan="2">
                        <input class="remote_destination_update_field" type="hidden" name="token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="token_secret" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_token" value="" />
                        <input class="remote_destination_update_field" type="hidden" name="tmp_token_secret" value="" />
                        <a href="#" target="_blank" style="display: none;" class="remote_destination_connect_to_dropbox_link"></a>
                        <div class="mainwp_info-box"><?php _e('The Dropbox connections opens a new window, if you do not see it be sure to turn off your popup blocker.','mainwp'); ?></div>
                        <input type="button" class="button-primary remote_destination_connect_to_dropbox" value="<?php _e('Connect to Dropbox','mainwp'); ?>" />
                        <input type="button" class="button-primary remote_destination_connect_to_dropbox_authorized" value="<?php _e('Yes I have authorized MainWP to Dropbox','mainwp'); ?>" style="display: none;" />
                    </td>
                </tr>
            </table>
            <?php
    }

    private function checkOAuth2Upgrade($token = null, $tokenSecret = null, $saveNewToken = true)
    {
        if ($token == null) $token = $this->getToken();
        if ($tokenSecret == null) $tokenSecret = $this->getTokenSecret();

        if (strlen($tokenSecret) > 0)
        {
            $appInfo = \Dropbox\AppInfo::loadFromJson(array('key' => self::$consumerKey, 'secret' => self::$consumerSecret));
            $upgrader = new \Dropbox\OAuth1Upgrader($appInfo, "mainwp-oauth-upgrade", "en");

            // Get an OAuth 2 access token from the existing OAuth 1 access token.
            $oauth1AccessToken = new \Dropbox\OAuth1AccessToken($token, $tokenSecret);
            $oauth2AccessToken = $upgrader->createOAuth2AccessToken($oauth1AccessToken);

            if ($saveNewToken)
            {
                $fields = array('title' => $this->getTitle(),
                    'directory' => $this->getDir(),
                    'token' => $oauth2AccessToken,
                    'token_secret' => '');

                $this->save($fields);
                $upgrader->disableOAuth1AccessToken($oauth1AccessToken);
                $this->object->field2 = $oauth2AccessToken;
                $this->object->field3 = '';
            }

            return $oauth2AccessToken;
        }

        return $token;
    }

    public function test($fields = null)
    {
        $token = null;

        if ($fields != null)
        {
            if (($fields['token'] == $this->getToken()) && (isset($fields['token_secret'])) && ($fields['token_secret'] === $this->getTokenSecret()))
            {
                $fields = null;
            }
            else if (isset($fields['token_secret']) && ($fields['token_secret'] != '') && ('' === $this->getTokenSecret()))
            {
                $fields = null;
            }
        }

        if ($fields == null)
        {
            $this->checkOAuth2Upgrade();
            $token = $this->getToken();
        }
        else if (isset($fields['new_token']) && $fields['new_token'] != '')
        {
            $token = $fields['new_token'];
        }
        else if (isset($fields['token']) && $fields['token'] != '')
        {
            $token = $fields['token'];
        }

        try
        {
            if (isset($fields['token_secret']))
            {
                $token = $this->checkOAuth2Upgrade($fields['token'], $fields['token_secret'], false);
            }
            else if (isset($fields['new_token_secret']))
            {
                $token = $this->checkOAuth2Upgrade($fields['new_token'], $fields['new_token_secret'], false);
            }
        }
        catch (Exception $e)
        {
            $token = null;
        }

        if (($token == null) || ($token == ''))  throw new Exception('Tokens not set, please re-authenticate.');
        $dbxClient = new \Dropbox\Client($token, "PHP-Example/1.0");
        $accountInfo = $dbxClient->getAccountInfo();

        if (is_array($accountInfo) && isset($accountInfo['uid'])) return true;

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
        add_action('wp_ajax_mainwp_remotedestination_dropbox_connect', array(__CLASS__, 'mainwp_remotedestination_dropbox_connect'));
        add_action('wp_ajax_mainwp_remotedestination_dropbox_authorize', array(__CLASS__, 'mainwp_remotedestination_dropbox_authorize'));
    }

    public static function mainwp_remotedestination_dropbox_connect()
    {
//        $appInfo = \Dropbox\AppInfo::loadFromJson(array('key' => self::$consumerKey, 'secret' => self::$consumerSecret));
//        $uri = '';
//        $tokenStore = new \Dropbox\ArrayEntryStore($_SESSION, 'dropbox-auth-csrf-token');
//        $webAuth = new \Dropbox\WebAuth($appInfo, "mainwp-remote-backup/1.0", $uri, $tokenStore, "en");
//        $authorizeUrl = $webAuth->start();

        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/OAuth/Consumer/ConsumerAbstract.php';
        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/OAuth/Consumer/Curl.php';
        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/API.php';
        $oauth = new OAuth_Consumer_Curl(self::$consumerKey, self::$consumerSecret);
        $reqToken = $oauth->getRequestToken();
        $oauth->setToken($reqToken);
        $url = $oauth->getAuthoriseUrl();
        die(json_encode(array('requestToken' => $reqToken, 'authorizeUrl' => $url)));
    }

    public static function mainwp_remotedestination_dropbox_authorize()
    {
        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/OAuth/Consumer/ConsumerAbstract.php';
        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/OAuth/Consumer/Curl.php';
        include_once mainwp_remote_backup_extension_dir() . 'Dropbox/API.php';
        $oauth = new OAuth_Consumer_Curl(self::$consumerKey, self::$consumerSecret);
        $objtoken = new stdClass;
        $objtoken->oauth_token = $_POST['token'];
        $objtoken->oauth_token_secret = $_POST['token_secret'];
        $oauth->setToken($objtoken);

        try
        {
            die(json_encode(array('accessToken' => $oauth->getAccessToken())));
        }
        catch (Exception $e)
        {
            die(json_encode(array('error' => $e->getMessage())));
        }
    }
}