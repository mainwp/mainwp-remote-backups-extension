<?php
class MainWPRemoteDestinationUtility
{
    static function ctype_digit($str)
    {
        return (is_string($str) || is_int($str) || is_float($str)) && preg_match('/^\d+\z/', $str);
    }

    public static function encrypt($str, $pass)
    {
        $pass = str_split(str_pad('', strlen($str), $pass, STR_PAD_RIGHT));
        $stra = str_split($str);
        foreach ($stra as $k => $v)
        {
            $tmp = ord($v) + ord($pass[$k]);
            $stra[$k] = chr($tmp > 255 ? ($tmp - 256) : $tmp);
        }
        return base64_encode(join('', $stra));
    }

    public static function decrypt($str, $pass)
    {
        $str = base64_decode($str);
        $pass = str_split(str_pad('', strlen($str), $pass, STR_PAD_RIGHT));
        $stra = str_split($str);
        foreach ($stra as $k => $v)
        {
            $tmp = ord($v) - ord($pass[$k]);
            $stra[$k] = chr($tmp < 0 ? ($tmp + 256) : $tmp);
        }
        return join('', $stra);
    }

    public static function sortmulti($array, $index, $order, $natsort = FALSE, $case_sensitive = FALSE)
    {
        $sorted = array();
        if (is_array($array) && count($array) > 0) {
            foreach (array_keys($array) as $key)
                $temp[$key] = $array[$key][$index];
            if (!$natsort) {
                if ($order == 'asc')
                    asort($temp);
                else
                    arsort($temp);
            }
            else
            {
                if ($case_sensitive === true)
                    natsort($temp);
                else
                    natcasesort($temp);
                if ($order != 'asc')
                    $temp = array_reverse($temp, TRUE);
            }
            foreach (array_keys($temp) as $key)
                if (is_numeric($key))
                    $sorted[] = $array[$key];
                else
                    $sorted[$key] = $array[$key];
            return $sorted;
        }
        return $sorted;
    }

    public static function endSession()
    {
        session_write_close();
        if (ob_get_length() > 0) ob_end_flush();
    }

    public static function startsWith($haystack, $needle)
    {
        return !strncmp($haystack, $needle, strlen($needle));
    }

    public static function can_edit_remotedestination(&$remoteDestinationFromDB)
    {
        $multiUser = apply_filters('mainwp_is_multi_user', false);
        if (!$multiUser) return true;

        global $current_user;
        if ($remoteDestinationFromDB->userid != $current_user->ID) throw new Exception('You are not allowed to change this remote destination');

        return true;
    }
}