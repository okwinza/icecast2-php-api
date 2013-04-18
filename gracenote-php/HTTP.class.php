<?php
namespace Gracenote\WebAPI;

// A class to handle all external communication via HTTP(S)
class HTTP
{
    // Constants
    const GET  = 0;
    const POST = 1;

    // Members
    private $_url;                  // URL to send the request to.
    private $_timeout;              // Seconds before we give up.
    private $_headers  = array();   // Any headers to send with the request.
    private $_postData = null;      // The POST data.
    private $_ch       = null;      // cURL handle
    private $_type     = HTTP::GET; // Default is GET

    ////////////////////////////////////////////////////////////////////////////////////////////////

    // Ctor
    public function __construct($url, $timeout = 10000)
    {
        global $_CONFIG;
        $this->_url     = $url;
        $this->_timeout = $timeout;

        // Prepare the cURL handle.
        $this->_ch = curl_init();

        // Set connection options.
        curl_setopt($this->_ch, CURLOPT_URL,            $this->_url);     // API URL
        curl_setopt($this->_ch, CURLOPT_USERAGENT,      "php-gracenote"); // Set our user agent
        curl_setopt($this->_ch, CURLOPT_FAILONERROR,    true);            // Fail on error response.
        @curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, true);            // Follow any redirects
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);            // Put the response into a variable instead of printing.
        curl_setopt($this->_ch, CURLOPT_TIMEOUT_MS,     $this->_timeout); // Don't want to hang around forever.
    }

    // Dtor
    public function __destruct()
    {
        if ($this->_ch != null) { curl_close($this->_ch); }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    // Prepare the cURL handle
    private function prepare()
    {
        // Set header data
        if ($this->_headers != null)
        {
            $hdrs = array();
            foreach ($this->_headers as $header => $value)
            {
                // If specified properly (as string) use it. If name=>value, convert to name:value.
                $hdrs[] = ((strtolower(substr($value, 0, 1)) === "x")
                          && (strpos($value, ":") !== false)) ? $value : $header.":".$value;
            }
            curl_setopt($this->_ch, CURLOPT_HTTPHEADER, $hdrs);
        }

        // Add POST data if it's a POST request
        if ($this->_type == HTTP::POST)
        {
            curl_setopt($this->_ch, CURLOPT_POST,       true);
            curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $this->_postData);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    public function execute()
    {
        // Prepare the request
        $this->prepare();

        // Now try to make the call.
        $response = null;
        try
        {
            if (GN_DEBUG) { echo("http: external request ".(($this->_type == HTTP::GET) ? "GET" : "POST")." url=" . $this->_url. ", timeout=" . $this->_timeout . "\n"); }

            // Execute the request
            $response = curl_exec($this->_ch);
        }
        catch (Exception $e)
        {
            throw new GNException(GNError::HTTP_REQUEST_ERROR);
        }

        // Validate the response, or throw the proper exceptionS.
        $this->validateResponse($response);

        return $response;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    // This validates a cURL response and throws an exception if it's invalid in any way.
    public function validateResponse($response, $errno = null)
    {
        $curl_error = ($errno === null) ? curl_errno($this->_ch) : $errno;
        if ($curl_error !== CURLE_OK)
        {
            switch ($curl_error)
            {
                case CURLE_HTTP_NOT_FOUND:      throw new GNException(GNError::HTTP_RESPONSE_ERROR_CODE, $this->getResponseCode());
                case CURLE_OPERATION_TIMEOUTED: throw new GNException(GNError::HTTP_REQUEST_TIMEOUT);
            }

            throw new GNException(GNError::HTTP_RESPONSE_ERROR, $curl_error);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    public function getHandle()          { return $this->_ch; }
    public function getResponseCode()    { return curl_getinfo($this->_ch, CURLINFO_HTTP_CODE); }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    public function setPOST()            { $this->_type = HTTP::POST; }
    public function setGET()             { $this->_type = HTTP::GET; }
    public function setPOSTData($data)   { $this->_postData = $data; }
    public function setHeaders($headers) { $this->_headers = $headers; }
    public function addHeader($header)   { $this->_headers[] = $header; }
    public function setCurlOpt($o, $v)   { curl_setopt($this->_ch, $o, $v); }

    ////////////////////////////////////////////////////////////////////////////////////////////////

    // Wrappers
    public function get()
    {
        $this->setGET();
        return $this->execute();
    }

    public function post($data = null)
    {
        if ($data != null) { $this->_postData = $data; }
        $this->setPOST();
        return $this->execute();
    }
}
