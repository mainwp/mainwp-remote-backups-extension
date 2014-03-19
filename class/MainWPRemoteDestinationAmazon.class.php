<?php
class MainWPRemoteDestinationAmazon extends MainWPRemoteDestination
{
    public function __construct($pObject = array('type' => 'amazon'))
    {
        parent::__construct($pObject);
    }

    public function getAccess()
    {
        return $this->object->field1;
    }

    public function getSecret()
    {
        return MainWPRemoteDestinationUtility::decrypt($this->object->field2, MainWPRemoteDestination::$ENCRYPT);
    }

    public function getBucket()
    {
        return $this->object->field3;
    }

    public function getDir()
    {
        return $this->object->field4;
    }

    /**
     * @param $amazon S3
     * @param $pLocalbackupfile
     * @param $pRegexFile
     * @param $backupFiles
     * @param null $dir
     * @return mixed
     */
    public function limitFiles($amazon, $pLocalbackupfile, $pRegexFile, &$backupFiles, $dir = null)
    {
        $maxBackups = get_option('mainwp_backupOnExternalSources');
        if ($maxBackups === false) $maxBackups = 1;

        if ($maxBackups == 0) return $backupFiles;
        $maxBackups--;

        $filesToRemove = $amazon->listObjects($this->getBucket(), $pRegexFile, basename($pLocalbackupfile), $backupFiles);
        if (count($filesToRemove) <= $maxBackups) return $backupFiles;

        $filesToRemove = MainWPRemoteDestinationUtility::sortmulti($filesToRemove, 'dts', 'desc');

        for ($i = $maxBackups; $i < count($filesToRemove); $i++)
        {
            $amazon->deleteObject($this->getBucket(), $filesToRemove[$i]['file']);

            if (($key = array_search(basename($filesToRemove[$i]['file']), $backupFiles)) !== false)
            {
                unset($backupFiles[$key]);
            }
        }

        return $backupFiles;
    }

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pUnique = null)
    {
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

        $amazon_uri = (($dir != '') ? $dir . '/' : '') . basename($pLocalbackupfile);
        $uploader = new S3($this->getAccess(), $this->getSecret(), false);
        $uploader->setExceptions(true);

        if ($pUnique != null)
        {
            $uploadTracker = new MainWPRemoteDestinationUploadTracker($pUnique);
            $uploader->setUploadTracker($uploadTracker);
        }
        try
        {
            $bucketLocation = null;
            try
            {
                $bucketLocation = $uploader->getBucketLocation(urlencode($this->getBucket()));
            }
            catch (Exception $e)
            {

            }

            if ($bucketLocation == null) $uploader->putBucket(urlencode($this->getBucket()));


            if ($pLocalbackupfile != null)
            {
                $uploaded = $uploader->putObjectFile($pLocalbackupfile, urlencode($this->getBucket()), $amazon_uri);
                if (!$uploaded)
                {
                    throw new Exception('Upload failed');
                }
            }

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

                $backupsTaken = $this->limitFiles($uploader, $pLocalbackupfile, $pRegexFile, $backupsTaken);

                array_push($backupsTaken, basename($pLocalbackupfile));
                $backups[$pType] = $backupsTaken;

                MainWPRemoteBackupDB::Instance()->updateRemoteBackups($pSiteId, $this->getType(), $backups);
            }
            return true;
        }
        catch (S3Exception $e)
        {
            throw new Exception($e->getMessage() . $e->getReadableException() . (stristr($this->getBucket(), ' ') && stristr($e->getReadableException(), 'InvalidBucketName') ? ' - Bucket may not contain spaces.' : ''));
        }
    }

    public function buildUpdateForm()
    {
        ?>
    <table>
        <tr>
            <td width="150px"><?php _e('Title:','mainwp'); ?></td>
            <td><input class="remote_destination_update_field" type="text" name="title"
                       value="<?php echo $this->object->title; ?>"/></td>
        </tr>
        <tr>
            <td><?php _e('Access Key ID:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="access"
                       value="<?php echo $this->getAccess(); ?>"/></td>
        </tr>
        <tr>
            <td><?php _e('Secret Key:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="secret"
                       value="<?php echo $this->getSecret(); ?>"/></td>
        </tr>
        <tr>
            <td><?php _e('Bucket:','mainwp') ?> <?php do_action('mainwp_renderToolTip', __("The bucket name you choose must be unique across all existing bucket names in Amazon S3. One way to help ensure uniqueness is to prefix your bucket names with the name of your organization.")); ?></td>
            <td><input class="remote_destination_update_field" type="text" name="bucket"
                       value="<?php echo $this->getBucket(); ?>"/></td>
        </tr>
        <tr>
            <td><?php _e('Sub-Directory:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="directory"
                       value="<?php echo $this->getDir(); ?>"/></td>
        </tr>
    </table>
    <?php
    }

    public function buildCreateForm()
    {
        ?>
    <table>
        <tr>
            <td width="150px"><?php _e('Title:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="title" value="<?php _e('New Amazon Destination','mainwp') ?>"/>
            </td>
        </tr>
        <tr>
            <td><?php _e('Access Key ID:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="access" value=""/></td>
        </tr>
        <tr>
            <td><?php _e('Secret Key:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="secret" value=""/></td>
        </tr>
        <tr>
            <td><?php _e('Bucket:','mainwp') ?> <?php do_action('mainwp_renderToolTip', __("The bucket name you choose must be unique across all existing bucket names in Amazon S3. One way to help ensure uniqueness is to prefix your bucket names with the name of your organization.")); ?></td>
            <td><input class="remote_destination_update_field" type="text" name="bucket" value=""/></td>
        </tr>
        <tr>
            <td><?php _e('Sub-Directory:','mainwp') ?></td>
            <td><input class="remote_destination_update_field" type="text" name="directory" value=""/></td>
        </tr>
    </table>
    <?php
    }

    public function test($fields = null)
    {
        $key_id = $fields == null ? $this->getAccess() : (!isset($fields['access']) ? null : $fields['access']);
        $key_secret = $fields == null ? $this->getSecret() : (!isset($fields['secret']) ? null : $fields['secret']);
        $bucket_name = $fields == null ? $this->getBucket() : (!isset($fields['bucket']) ? null : $fields['bucket']);
        if (($key_id == null) || ($key_id == '') || ($key_secret == null) || ($key_secret == '') || ($bucket_name == null) || ($bucket_name == '')) throw new Exception('Please fill in all the fields');
        $tester = new S3($key_id, $key_secret, false);
        $tester->setExceptions(true);

        try
        {
            $bucketLocation = null;
            try
            {
                $bucketLocation = $tester->getBucketLocation(urlencode($bucket_name));
            }
            catch (Exception $e)
            {

            }

            if ($bucketLocation == null) $tester->putBucket(urlencode($bucket_name));
        }
        catch (S3Exception $e)
        {
            throw new Exception($e->getReadableException() . (stristr($bucket_name, ' ') && stristr($e->getReadableException(), 'InvalidBucketName') ? ' - Bucket may not contain spaces.' : ''));
        }

        return true;
    }

    public function save($fields = array())
    {
        $values = array('title' => $fields['title'],
            'field1' => $fields['access'],
            'field2' => MainWPRemoteDestinationUtility::encrypt($fields['secret'], MainWPRemoteDestination::$ENCRYPT),
            'field3' => $fields['bucket'],
            'field4' => $fields['directory']);

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