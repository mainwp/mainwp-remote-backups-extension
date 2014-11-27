<?php

class MainWPRemoteDestinationUploadTracker
{
    protected $uniqueId;
    protected $startOffset;

    public function __construct($pUniqueId, $pStartOffset = null)
    {
        $this->uniqueId = $pUniqueId;
        $this->startOffset = $pStartOffset;
    }

    public function setStartOffset($pOffset)
    {
        $this->startOffset = $pOffset;
    }

    public function track_upload($file, $uploadID, $offset, $useStartOffset = false, $finished = false)
    {
        if (session_id() == '') session_start();
        if ($useStartOffset && isset($this->startOffset) && ($this->startOffset != null)) $offset += $this->startOffset;

        $array = get_option('mainwp_upload_progress');
        if (!is_array($array)) $array = array();
        if ($finished)
        {
            $array[$this->uniqueId]['finished'] = true;
        }
        else if (!isset($array[$this->uniqueId]) || ($array[$this->uniqueId]['offset'] < $offset))
        {
            $array[$this->uniqueId]['offset'] = $offset;
            $array[$this->uniqueId]['dts'] = time();
        }

        foreach($array as $key => $val)
        {
            if (time() - $key > (60 * 60 * 2)) unset($array[$key]);
        }
        update_option('mainwp_upload_progress', $array);
        MainWPRemoteDestinationUtility::endSession();
    }
}