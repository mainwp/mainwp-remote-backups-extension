<?php

require_once(mainwp_remote_backup_extension_dir() . 'OAuth.php');

class CopyAPI
{
    protected static $USERAPI = 'https://api.copy.com/rest/user';
    protected static $FILEAPI = 'https://api.copy.com/rest/files/';
    protected static $METAPI = 'https://api.copy.com/rest/meta/copy/';

    protected $consumerKey;
    protected $consumerSecret;

    protected $oauth_token;
    protected $oauth_token_secret;

    protected $signature_method;
    protected $consumer;
    protected $token;

    protected $uploadTracker;

    public function __construct($pConsumerKey, $pConsumerSecret, $pOauthToken, $pOauthTokenSecret)
    {
        $this->consumerKey = $pConsumerKey;
        $this->consumerSecret = $pConsumerSecret;
        $this->oauth_token = $pOauthToken;
        $this->oauth_token_secret = $pOauthTokenSecret;


        $this->signature_method = new SN_OAuthSignatureMethod_HMAC_SHA1();
        $this->consumer = new SN_OAuthConsumer($this->consumerKey, $this->consumerSecret, NULL);
        $this->token = new SN_OAuthToken($this->oauth_token, $this->oauth_token_secret);

        $this->uploadTracker = null;
    }

    public function getUserInfo()
    {
        $oauth_req = SN_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'GET', self::$USERAPI);
        $oauth_req->sign_request($this->signature_method, $this->consumer, $this->token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$USERAPI);

        $headers = array($oauth_req->to_header());
        $headers[] = 'X-Api-Version: 1';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200)
        {
            $return = json_decode($return, 1);
            return $return;
        }
        else if ($http_code == 400)
        {
            $return = json_decode($return, 1);
            if (isset($return['error']))
            {
                if ($return['message'] == 'oauth_problem=token_rejected') throw new Exception('Our secure connection has been rejected, please re-authenticate.');

                throw new Exception('An error occured, please re-authenticate. [code=' . $return['error'] . '][message=' . $return['message'] . ']');
            }
        }

        return null;
    }

    public function setUploadTracker($pUploadTracker)
    {
        $this->uploadTracker = $pUploadTracker;
    }

    public function uploadFile($pFile, $pRemoteDir, $pRemoteFilename)
    {
        $url = self::$FILEAPI . $pRemoteDir;

        $oauth_req = SN_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'POST', $url);
        $oauth_req->sign_request($this->signature_method, $this->consumer, $this->token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $headers = array($oauth_req->to_header());
        $headers[] = 'X-Api-Version: 1';

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $postFields = array('file' => "@" . $pFile . ';filename='.$pRemoteFilename);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        if ($this->uploadTracker != null)
        {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array(&$this, '__progressCallback'));
        }

        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    public function getMeta($pFile)
    {
        $url = self::$METAPI . trim($pFile, '/');

        $oauth_req = SN_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'GET', $url);
        $oauth_req->sign_request($this->signature_method, $this->consumer, $this->token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $headers = array($oauth_req->to_header());
        $headers[] = 'X-Api-Version: 1';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) return json_decode($return, 1);

        return null;
    }

    public function delete($pFile)
    {
        $url = self::$FILEAPI . trim($pFile, '/');

        $oauth_req = SN_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'DELETE', $url);
        $oauth_req->sign_request($this->signature_method, $this->consumer, $this->token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $headers = array($oauth_req->to_header());
        $headers[] = 'X-Api-Version: 1';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $return = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) return json_decode($return, 1);

        return null;
    }

    function __progressCallback($param1 = null, $param2 = null, $param3 = null, $param4 = null, $param5 = null)
    {
        if (is_resource($param1))
        {
            $download_size = $param2;
            $downloaded = $param3;
            $upload_size = $param4;
            $uploaded = $param5;
        }
        else
        {
            $download_size = $param1;
            $downloaded = $param2;
            $upload_size = $param3;
            $uploaded = $param4;
        }


        $this->uploadTracker->track_upload(null, null, $uploaded, true);
    }
}
