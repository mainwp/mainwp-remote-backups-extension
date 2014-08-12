<?php

/**
* OAuth consumer using PHP cURL
* @author Ben Tadiar <ben@handcraftedbyben.co.uk>
* @link https://github.com/benthedesigner/dropbox
* @package Dropbox\OAuth
* @subpackage Consumer
*/

class OAuth_Consumer_Curl extends OAuth_Consumer_ConsumerAbstract
{
    // offset for current transfer!
    protected $currentOffset = 0;

    /**
     * Default cURL options
     * @var array
     */
    protected $defaultOptions = array(
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE        => true,
        CURLOPT_HEADER         => true,
        CURLINFO_HEADER_OUT    => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
    );

    /**
     * Store the last response form the API
     * @var mixed
     */
    protected $lastResponse = null;

    protected $uploadTracker = null;

   /**
     * Set properties and begin authentication
     * @param string $key
     * @param string $secret
     */
    public function __construct($key, $secret)
    {
        // Check the cURL extension is loaded
        if (!extension_loaded('curl')) {
            throw new Exception('The cURL OAuth consumer requires the cURL extension');
        }

        $this->consumerKey = $key;
        $this->consumerSecret = $secret;
    }

    public function setUploadTracker(&$pUploadTracker)
    {
        $this->uploadTracker = $pUploadTracker;
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

    protected function stream_file($curl, $fileData, $fileSize)
    {
        fseek($fileData, $this->currentOffset);
        $len = fread($fileData, $fileSize);
        $this->currentOffset += strlen($len);
        return $len;
    }

    /**
     * Execute an API call
     * @todo Improve error handling
     * @param string $method The HTTP method
     * @param string $url The API endpoint
     * @param string $call The API method to call
     * @param array $additional Additional parameters
     * @return string|object stdClass
     */
    public function fetch($method, $url, $call, $additional = array())
    {
        $bytes = null;
        if (isset($additional['bytes']))
        {
            $bytes = $additional['bytes'];
            $this->currentOffset = $additional['offset'];
            unset($additional['bytes']);
        }
        // Get the signed request URL
        $request = $this->getSignedRequest($method, $url, $call, $additional);

        // Initialise and execute a cURL request
        $handle = curl_init($request['url']);

        // Get the default options array
        $options = $this->defaultOptions;
        $options[CURLOPT_CAINFO] = dirname(__FILE__) . '/ca-bundle.pem';

        if ($method == 'GET' && $this->outFile) { // GET
            $options[CURLOPT_RETURNTRANSFER] = false;
            $options[CURLOPT_HEADER] = false;
            $options[CURLOPT_FILE] = $this->outFile;
            $options[CURLOPT_BINARYTRANSFER] = true;
            $this->outFile = null;
        } elseif ($method == 'POST') { // POST
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $request['postfields'];
        } elseif ($method == 'PUT' && $this->inFile) { // PUT
            $options[CURLOPT_PUT] = true;
            $options[CURLOPT_INFILE] = $this->inFile;
            // @todo Update so the data is not loaded into memory to get its size
            if ($bytes == null)
            {
                $options[CURLOPT_INFILESIZE] = strlen(stream_get_contents($this->inFile));
                fseek($this->inFile, 0);
            }
            else
            {
                $options[CURLOPT_INFILESIZE] = $bytes;
                $options[CURLOPT_READFUNCTION] = array(&$this, 'stream_file');
            }
            $this->inFile = null;
        }

        // Set the cURL options at once
        curl_setopt_array($handle, $options);

        $timeout = 20 * 60 * 60; //20 minutes
        @curl_setopt($handle, CURLOPT_TIMEOUT, $timeout); //20minutes
        if (!ini_get('safe_mode')) @set_time_limit($timeout); //20minutes
        @ini_set('max_execution_time', $timeout);


        if ($this->uploadTracker != null)
        {
            curl_setopt($handle, CURLOPT_NOPROGRESS, false);
            curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, array(&$this, '__progressCallback'));
            //curl_setopt($handle, CURLOPT_BUFFERSIZE, 256);
        }

        // Execute and parse the response
        $response = curl_exec($handle);

        //Check if a curl error has occured
        if ($response === false)
            throw new Exception("Error Processing Request: " . curl_error($handle));

        curl_close($handle);

        // Parse the response if it is a string
        if (is_string($response)) {
            $response = $this->parse($response);
        }

        // Set the last response
        $this->lastResponse = $response;

        // Check if an error occurred and throw an Exception
        if (!empty($response['body']->error)) {
        	// Dropbox returns error messages inconsistently...
        	if ($response['body']->error instanceof stdClass) {
        		$array = array_values((array) $response['body']->error);
        		$response['body']->error = $array[0];
        	}

        	// Throw an Exception with the appropriate with the appropriate code
            throw new Exception($response['body']->error, $response['code']);
        }

        return $response;
    }

    /**
     * Parse a cURL response
     * @param string $response
     * @return array
     */
    private function parse($response)
    {
        // cURL automatically handles Proxy rewrites, remove the "HTTP/1.0 200 Connection established" string
        if (stripos($response, "HTTP/1.0 200 Connection established\r\n\r\n") !== false) {
            $response = str_ireplace("HTTP/1.0 200 Connection established\r\n\r\n", '', $response);
        }

        // Explode the response into headers and body parts (separated by double EOL)
        list($headers, $response) = explode("\r\n\r\n", $response, 2);

        // Explode response headers
        $lines = explode("\r\n", $headers);

        // If the status code is 100, the API server must send a final response
        // We need to explode the response again to get the actual response
        if (preg_match('#^HTTP/1.1 100#', $lines[0])) {
            list($headers, $response) = explode("\r\n\r\n", $response, 2);
            $lines = explode("\r\n", $headers);
        }

        // Get the HTTP response code from the first line
        $first = array_shift($lines);
        $pattern = '#^HTTP/1.1 ([0-9]{3})#';
        preg_match($pattern, $first, $matches);
        $code = $matches[1];

        // Parse the remaining headers into an associative array
        $headers = array();
        foreach ($lines as $line) {
            list($k, $v) = explode(': ', $line, 2);
            $headers[strtolower($k)] = $v;
        }

        // If the response body is not a JSON encoded string
        // we'll return the entire response body
        if (!$body = json_decode($response)) {
            $body = $response;
        }

        return array('code' => $code, 'body' => $body, 'headers' => $headers);
    }

    /**
     * Return the response for the last API request
     * @return mixed
     */
    public function getlastResponse()
    {
    	return $this->lastResponse;
    }
}
