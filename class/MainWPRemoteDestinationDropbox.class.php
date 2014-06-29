<?php
class MainWPRemoteDestinationDropbox extends MainWPRemoteDestination
{
    public function __construct($pObject = array('type' => 'dropbox'))
    {
        parent::__construct($pObject);
    }

    public function getUsername()
    {
        return $this->object->field1;
    }

    public function getPassword()
    {
        return MainWPRemoteDestinationUtility::decrypt($this->object->field2, MainWPRemoteDestination::$ENCRYPT);
    }

    public function getDir()
    {
        return $this->object->field3;
    }

    public function getIdentifier()
    {
        return $this->getUsername();
    }

    public function limitFiles($ftp, $pLocalbackupfile, $pRegexFile, &$backupFiles, $dir = null)
    {
        return true;
    }

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pUnique = null)
    {
        $uploader = new DropboxUploader($this->getUsername(), $this->getPassword());
        $connected = $uploader->testConnection();
        if (!$connected) {
            throw new Exception('Unable to connect');
        }
        if ($pLocalbackupfile != null) {
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
            $uploader->upload($pLocalbackupfile, $dir);
        }

        return true;
    }

    public function buildUpdateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="<?php echo $this->object->title; ?>" /></td></tr>
                <tr><td><?php _e('Username:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="username" value="<?php echo $this->getUsername(); ?>" /></td></tr>
                <tr><td><?php _e('Password:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="password" name="password" value="<?php echo $this->getPassword(); ?>" /></td></tr>
                <tr><td><?php _e('Directory:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="directory" value="<?php echo $this->getDir(); ?>" /></td></tr>
            </table>
            <?php
    }

    public function buildCreateForm()
    {
      ?>
            <table>
                <tr><td width="150px"><?php _e('Title:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="title" value="New Dropbox Destination" /></td></tr>
                <tr><td><?php _e('Username:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="username" value="" /></td></tr>
                <tr><td><?php _e('Password:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="password" name="password" value="" /></td></tr>
                <tr><td><?php _e('Directory:','mainwp'); ?></td><td><input class="remote_destination_update_field" type="text" name="directory" value="" /></td></tr>
            </table>
            <?php
    }

    public function test($fields = null)
    {
        $username = $fields == null ? $this->getUsername() : (!isset($fields['username']) ? null : $fields['username']);
        $password = $fields == null || !isset($fields['password']) ? $this->getPassword() : (!isset($fields['password']) ? null : $fields['password']);
        if (($username == null) || ($username == '') || ($password == null) || ($password == ''))  throw new Exception('Please fill in all the fields');

        $uploader = new DropboxUploader($username, $password);
        $connected = $uploader->testConnection();
        if (!$connected)
        {
            throw new Exception('Unable to connect');
        }

        return true;
    }

    public function save($fields = array())
    {
        $values = array('title' => $fields['title'],
                        'field1' => $fields['username'],
                        'field2' => MainWPRemoteDestinationUtility::encrypt($fields['password'], MainWPRemoteDestination::$ENCRYPT),
                        'field3' => $fields['directory']);

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