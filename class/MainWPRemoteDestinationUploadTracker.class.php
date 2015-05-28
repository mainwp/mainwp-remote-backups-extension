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

    public function getUploadId()
    {
        $array = get_option('mainwp_upload_progress');
        if (!is_array($array)) return null;
        if (!isset($array[$this->uniqueId])) return null;
        if (!isset($array[$this->uniqueId]['uploadid'])) return null;

        return $array[$this->uniqueId]['uploadid'];
    }

    public function getOffset()
    {
        $array = get_option('mainwp_upload_progress');
        if (!is_array($array)) return 0;
        if (!isset($array[$this->uniqueId])) return 0;
        if (!isset($array[$this->uniqueId]['offset'])) return 0;

        return $array[$this->uniqueId]['offset'];
    }

    public function getExtra()
    {
        $array = get_option('mainwp_upload_progress');
        if (!is_array($array)) return null;
        if (!isset($array[$this->uniqueId])) return null;
        if (!isset($array[$this->uniqueId]['extra'])) return null;

        return $array[$this->uniqueId]['extra'];
    }

    public function track_upload($extra, $uploadID, $offset, $useStartOffset = false, $finished = false)
    {
        if (session_id() == '') session_start();
        if ($useStartOffset && isset($this->startOffset) && ($this->startOffset != null)) $offset += $this->startOffset;

        $array = get_option('mainwp_upload_progress');
        if (!is_array($array)) $array = array();
        $array[$this->uniqueId]['uploadid'] = $uploadID;
        $array[$this->uniqueId]['extra'] = $extra;
        if ($finished)
        {
            $array[$this->uniqueId]['finished'] = true;
        }
        else if (!isset($array[$this->uniqueId]) || !isset($array[$this->uniqueId]['offset']) || ($array[$this->uniqueId]['offset'] < $offset))
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