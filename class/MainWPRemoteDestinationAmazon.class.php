<?php
use Aws\S3\S3Client;

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

    public function getIdentifier()
    {
        return $this->object->id;
    }

    /**
     * @param $amazon S3
     * @param $pLocalbackupfile
     * @param $pRegexFile
     * @param $backupFiles
     * @param null $dir
     * @return mixed
     */
    public function limitFiles($s3Client, $pLocalbackupfile, $pRegexFile, &$backupFiles, $dir = null)
    {
        $maxBackups = get_option('mainwp_backupOnExternalSources');
        if ($maxBackups === false) $maxBackups = 1;

        if ($maxBackups == 0) return $backupFiles;
        $maxBackups--;

        $filesToRemove = array();
        try
        {
            $result = $s3Client->listObjects(array('Bucket' => $this->getBucket()));
            foreach ($result->get('Contents') as $content)
            {
                $file = $content['Key'];
                if ((basename($pLocalbackupfile) != basename($file)) &&
                        (preg_match('/' . $pRegexFile . '/', basename($file)) || in_array(basename($file), $backupFiles))
                )
                {
                    $filesToRemove[] = array('file' => $file, 'dts' => $content['LastModified']);
                }
            }
        }
        catch (Exception $e)
        {

        }

        if (count($filesToRemove) <= $maxBackups) return $backupFiles;

        $filesToRemove = MainWPRemoteDestinationUtility::sortmulti($filesToRemove, 'dts', 'desc');

        for ($i = $maxBackups; $i < count($filesToRemove); $i++)
        {
            $s3Client->deleteObject(array('Bucket' => $this->getBucket(), 'Key' => $filesToRemove[$i]['file']));

            if (($key = array_search(basename($filesToRemove[$i]['file']), $backupFiles)) !== false)
            {
                unset($backupFiles[$key]);
            }
        }

        return $backupFiles;
    }

    public function upload($pLocalbackupfile, $pType, $pSubfolder, $pRegexFile, $pSiteId = null, $pTaskId = null, $pUnique = null, $pTryResume = false)
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


        $s3Client = S3Client::factory(array(
            'key' => $this->getAccess(),
            'secret' => $this->getSecret()
        ));

        $bucketLocation = null;
        try
        {
            $bucketLocation = $s3Client->getBucketLocation(array('Bucket' => urlencode($this->getBucket())));
        }
        catch (Exception $e)
        {

        }

        if ($bucketLocation == null)
        {
            $s3Client->createBucket(array('Bucket' => urlencode($this->getBucket())));
            $bucketLocation = $s3Client->getBucketLocation(array('Bucket' => urlencode($this->getBucket())));
        }

        $s3Client = S3Client::factory(array(
        		'key' => $this->getAccess(),
        		'secret' => $this->getSecret(),
                'signature' => 'v4',
                'region' => $bucketLocation->get('Location')
        	));
        $s3Client->setSslVerification(false, false, 0);

//        $uploader = new S3($this->getAccess(), $this->getSecret(), false);
//        $uploader->setExceptions(true);

        if ($pUnique != null)
        {
            //todo: uploadTracker?
            $uploadTracker = new MainWPRemoteDestinationUploadTracker($pUnique);
//            $uploader->setUploadTracker($uploadTracker);
        }
        try
        {
            if ($pLocalbackupfile != null)
            {
                $metadata = array('creator' => 'MainWP');
                if ($pSiteId != null) $metadata['mainwp-siteid'] = $pSiteId;
                if ($pTaskId != null) $metadata['mainwp-taskid'] = $pTaskId;

                if (filesize($pLocalbackupfile) > (6000000 * 2))
                {
                    $uploadId = null;
                    if (!empty($uploadTracker)) $uploadId = $uploadTracker->getUploadId();

                    if (empty($uploadId))
                    {
                        // 1. Create multipart
                        $response = $s3Client->createMultipartUpload(array(
                            'Bucket' => $this->getBucket(),
                            'Key'    => $amazon_uri,
                            'Metadata' => $metadata
                        ));
                        $uploadId = $response['UploadId'];
                        if (!empty($uploadTracker)) $uploadTracker->track_upload(null, $uploadId, 0, false, false);
                    }


                    // 2. Upload the data in parts.
                    $file = @fopen($pLocalbackupfile, 'rb');

                    $extra = null;
                    if (!empty($uploadTracker)) $extra = $uploadTracker->getExtra();
                    if (empty($uploadTracker) || empty($extra) || !isset($extra['partNumber']))
                    {
                        $parts = array();
                        $partNumber = 1;
                        $offset = 0;
                        $chunkSize = ceil(filesize($pLocalbackupfile) / floor(filesize($pLocalbackupfile) / 6000000));
                    }
                    else
                    {
                        $parts = $extra['parts'];
                        $partNumber = $extra['partNumber'];
                        $offset = $uploadTracker->getOffset();
                        $chunkSize = $extra['chunkSize'];
                        @fseek($file, $offset);
                    }
                    while (!feof($file)) {
                        $data = fread($file, $chunkSize);
                        if (strlen($data) == 0) break;
                        $result = $s3Client->uploadPart(array(
                            'Bucket'     => $this->getBucket(),
                            'Key'        => $amazon_uri,
                            'UploadId'   => $uploadId,
                            'Metadata' => $metadata,
                            'PartNumber' => $partNumber,
                            'Body'       => $data,
                        ));
                        $offset += strlen($data);

                        $parts[] = array(
                            'PartNumber' => $partNumber++,
                            'ETag'       => $result['ETag'],
                        );

                        if (!empty($uploadTracker)) $uploadTracker->track_upload(array('partNumber' => $partNumber, 'parts' => $parts, 'chunkSize' => $chunkSize), $uploadId, $offset, false, false);
                    }

                    // 3. Complete multipart upload.
                    $result = $s3Client->completeMultipartUpload(array(
                        'Bucket'   => $this->getBucket(),
                        'Key'      => $amazon_uri,
                        'UploadId' => $uploadId,
                        'Metadata' => $metadata,
                        'Parts'    => $parts,
                    ));

                    if (!empty($uploadTracker)) $uploadTracker->track_upload(null, $uploadId, $offset, false, true);
                    $url = $result['Location'];
                }
                else
                {
                    $result = $s3Client->upload(urlencode($this->getBucket()), $amazon_uri, @fopen($pLocalbackupfile, 'rb'), 'private', array('Metadata' => $metadata));
                }

                if (!$result)
                {
                    throw new Exception('Upload failed');
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

                $backupsTaken = $this->limitFiles($s3Client, $pLocalbackupfile, $pRegexFile, $backupsTaken);

                array_push($backupsTaken, basename($pLocalbackupfile));
                $backups[$pType] = $backupsTaken;

                MainWPRemoteBackupDB::Instance()->updateRemoteBackups($pSiteId, $this->getType(), $this->getIdentifier(), $backups);
            }
            return true;
        }
        catch (S3Exception $e)
        {
            throw new Exception($e->getMessage());
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

        $s3Client = S3Client::factory(array(
            'key' => $key_id,
            'secret' => $key_secret)
        );

        try
        {
            $bucketLocation = null;
            try
            {
                $bucketLocation = $s3Client->getBucketLocation(array('Bucket' => urlencode($bucket_name)));
            }
            catch (Exception $e)
            {

            }

            if ($bucketLocation == null) $s3Client->createBucket(array('Bucket' => urlencode($bucket_name)));
        }
        catch (S3Exception $e)
        {
            throw new Exception($e->getMessage());
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