<?php
class MainWPRemoteDestinationFTP extends MainWPRemoteDestination
{
    public static function getInstance($ftpHost, $ftpPort, $ftpUsername, $ftpPassword, $ftpPath = '')
    {
        return new MainWPRemoteDestinationFTP(array('type' => 'ftp', 'field1' => $ftpHost, 'field2' => $ftpUsername, 'field3' => MainWPRemoteDestinationUtility::encrypt($ftpPassword, MainWPRemoteDestination::$ENCRYPT), 'field5' => $ftpPort, 'field4' => $ftpPath));
    }

    public function __construct($pObject = array('type' => 'ftp'))
    {
        parent::__construct($pObject);
    }

    public function getAddress()
    {
        return $this->object->field1;
    }

    public function getUsername()
    {
        return $this->object->field2;
    }

    public function getPassword()
    {
        return MainWPRemoteDestinationUtility::decrypt($this->object->field3, MainWPRemoteDestination::$ENCRYPT);
    }

    public function getPath()
    {
        return $this->object->field4;
    }

    public function getPort()
    {
        return $this->object->field5;
    }

    public function getSSL()
    {
        return $this->object->field6;
    }

    public function getActive()
    {
        return $this->object->field7;
    }


    public function getIdentifier()
    {
        return $this->getUsername() . '||' . $this->getAddress() . '||' . $this->getPort();
    }

    public function limitFiles($ftp, $pLocalbackupfile, $pRegexFile, &$backupFiles, $excludeDir = null)
    {
        $maxBackups = get_option('mainwp_backupOnExternalSources');
        if ($maxBackups === false) $maxBackups = 1;

        if ($maxBackups == 0) return $backupFiles;
        $maxBackups--;

        $filesToRemove = $this->listFiles($ftp, $this->getPath(), $pRegexFile, basename($pLocalbackupfile), $backupFiles, $excludeDir);
        if (count($filesToRemove) <= $maxBackups) return $backupFiles;

        for ($i = $maxBackups; $i < count($filesToRemove); $i++)
        {
            $ftp->delete($filesToRemove[$i]);

            if (($key = array_search(basename($filesToRemove[$i]), $backupFiles)) !== false)
            {
                unset($backupFiles[$key]);
            }
        }

        return $backupFiles;
    }

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pUnique = null)
    {
        $ftp = new Ftp();
        if ($this->getSSL() == '1') {
            $ftp->sslconnect($this->getAddress(), $this->getPort());
        } else {
            $ftp->connect($this->getAddress(), $this->getPort());
        }
        $ftp->login($this->getUsername(), $this->getPassword());
        $ftp->pasv(!$this->getActive());

        $dir = $this->getPath();
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
        if ($dir != '')
        {
            $ftp->mkDirRecursive($dir);
            $ftp->chdir($dir);
        }

        $uploadTracker = null;
        if ($pUnique != null)
        {
            $uploadTracker = new MainWPRemoteDestinationUploadTracker($pUnique);
        }

        if ($pLocalbackupfile != null) {
            $handle = fopen($pLocalbackupfile, 'r');
            $ret = $ftp->nb_fput(basename($pLocalbackupfile), $handle, ($pType == 'full' ? FTP_BINARY : FTP_ASCII));
            while ($ret == FTP_MOREDATA) {
                if ($uploadTracker != null) $uploadTracker->track_upload($pLocalbackupfile, null, ftell($handle));
                //Just continue uploading..
                $ret = $ftp->nb_continue();
            }
            fclose($handle);
            if ($ret != FTP_FINISHED) {
                throw new Exception('Unknown error');
            }
        }

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

            $backupsTaken = $this->limitFiles($ftp, $pLocalbackupfile, $pRegexFile, $backupsTaken, $dir);

            array_push($backupsTaken, basename($pLocalbackupfile));
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
                <tr><td><?php _e('Server address:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="address" value="<?php echo $this->getAddress(); ?>" /></td></tr>
                <tr><td><?php _e('Server port:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="port" value="<?php echo $this->getPort(); ?>" /></td></tr>
                <tr><td><?php _e('Username:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="username" value="<?php echo $this->getUsername(); ?>" /></td></tr>
                <tr><td><?php _e('Password:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="password" name="password" value="<?php echo $this->getPassword(); ?>" /></td></tr>
                <tr><td><?php _e('Remote path:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="path" value="<?php echo $this->getPath(); ?>" /></td></tr>
                <tr><td><?php _e('Use SSL:','mainwp'); ?></td><td><input type="checkbox" name="ssl" <?php echo ($this->getSSL() == '' ? 'checked' : ''); ?> /></td></tr>
                <tr><td><?php _e('Use Active mode:','mainwp'); ?></td><td><input type="checkbox" name="active" <?php echo ($this->getActive() == '1' ? 'checked' : ''); ?> /></td></tr>
            </table>
            <?php
    }

    public function buildCreateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="<?php _e('New FTP Destination','mainwp'); ?>" /></td></tr>
                <tr><td><?php _e('Server address:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="address" value="" /></td></tr>
                <tr><td><?php _e('Server port:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="port" value="21" /></td></tr>
                <tr><td><?php _e('Username:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="username" value="" /></td></tr>
                <tr><td><?php _e('Password:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="password" name="password" value="" /></td></tr>
                <tr><td><?php _e('Remote path:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="path" value="" /></td></tr>
                <tr><td><?php _e('Use SSL:','mainwp'); ?></td><td><input type="checkbox" name="ssl" /></td></tr>
                <tr><td><?php _e('Use Active mode:','mainwp'); ?></td><td><input type="checkbox" name="active" /></td></tr>
            </table>
            <?php
    }

    //todo: Add sort on last modified!
    private function listFiles($ftp, $dir, $regex, $exclude, &$backupFiles, $excludeDir)
    {
        $files = array();
        if ($ftp->isDir($dir))
        {
            $oldDir = null;
            if (stristr($dir, ' '))
            {
                $oldDir = $ftp->pwd();
                $ftp->chdir($dir);
            }
            $filesInDir = $ftp->nlist('-t ' . ($oldDir == null ? $dir : '.'));
            if ($oldDir != null)
            {
                $ftp->chdir($oldDir);
            }
            $inFiles = array();
            foreach($filesInDir as $file)
            {
                if (MainWPRemoteDestinationUtility::startsWith($file, (substr($dir, -1) != '/' ? $dir . '/' : $dir))) $file = str_replace((substr($dir, -1) != '/' ? $dir . '/' : $dir), '', $file);

                if ($file == '.' || $file == '..') continue;

                $inFiles[] = $this->listFiles($ftp, (substr($dir, -1) != '/' ? $dir . '/' : $dir) . $file, $regex, $exclude, $backupFiles, $excludeDir);
            }

            foreach ($inFiles as $inFile)
            {
                foreach ($inFile as $file)
                {
                    $files[] = $file;
                }
            }
        }
        else
        {
            if ($excludeDir != null)
            {
                if (!(stristr($dir, $excludeDir) && ($exclude == basename($dir))) &&
                                    (preg_match('/' . $regex . '/', basename($dir)) || in_array(basename($dir), $backupFiles))) $files[] = $dir;
            }
            else if (($exclude != basename($dir)) &&
                    (preg_match('/' . $regex . '/', basename($dir)) || in_array(basename($dir), $backupFiles))) $files[] = $dir;
        }

        return $files;
    }

    public function test($fields = null)
    {
        $address = ($fields == null ? $this->getAddress() : $fields['address']);
        $username = ($fields == null ? $this->getUsername() : $fields['username']);
        $password = ($fields == null ? $this->getPassword() : $fields['password']);
        $port = ($fields == null ? $this->getPort() : $fields['port']);
        if (($address == null) || ($address == '') || ($username == null) || ($username == '') || ($password == null) || ($password == '') || ($port == null) || ($port == ''))  throw new Exception('Please fill in all the fields');
        $ssl = ($fields == null ? false : (isset($fields['ssl']) && $fields['ssl'] == '1' ? true : false));
        $active = ($fields == null ? false : (isset($fields['active']) && $fields['active'] == '1' ? true : false));
        $path = ($fields == null ? $this->getPath() : $fields['path']);
        $ftp = new Ftp();
        if ($ssl)
        {
            $ftp->sslconnect($address, $port);
        }
        else
        {
            $ftp->connect($address, $port);
        }
        $ftp->login($username, $password);

        if (!$ftp->pasv(!$active))
        {
            throw new Exception('Passive mode not supported, use active mode.');
        }

        if ($path != '')
        {
            $ftp->mkDirRecursive($path);
            $ftp->chdir($path);
        }

        $file = tmpfile();
        fwrite($file, 'uploadtest');
        fseek($file, 0);

        try
        {
            $ret = $ftp->nb_fput('mainwp_upload_test.txt', $file, FTP_ASCII);
            while ($ret == FTP_MOREDATA)
            {
               // Continue upload...
               $ret = $ftp->nb_continue();
            }
            if ($ret != FTP_FINISHED)
            {
               throw new Exception('Unknown error');
            }
            fclose($file);
            $ftp->delete('mainwp_upload_test.txt');
        }
        catch (Exception $e)
        {
            fclose($file);
            if ($e->getMessage() != null && $e->getMessage() != '')
            {
                throw new Exception('Error uploading test file: '.$e->getMessage());
            }
            throw new Exception('Error uploading test file. Try ' . ($active ? 'disabling' : 'enabling') . ' the Active mode.');
        }

        return true;
    }

    public function save($fields = array())
    {
        $values =  array('title' => $fields['title'],
                         'field1' => $fields['address'],
                         'field2' => $fields['username'],
                         'field3' => MainWPRemoteDestinationUtility::encrypt($fields['password'], MainWPRemoteDestination::$ENCRYPT),
                         'field4' => $fields['path'],
                         'field5' => $fields['port'],
                         'field6' => $fields['ssl'],
                         'field7' => $fields['active']);

        if (isset($this->object->id))
        {
            return MainWPRemoteBackupDB::Instance()->updateRemoteDestination($this->object->id, $values);
        }
        else
        {
            return MainWPRemoteBackupDB::Instance()->addRemoteDestinationWithValues($this->object->type, $values);
        }
    }
}