<?php
/**
 * Simple GitHub API client class.
 * Inspired by https://github.com/tan-tan-kanarek/github-php-client/
 *
 * Coded for exploratory and experimental purposes.
 **/

include('defines.inc');

class ghclient
{
  protected $debug = true;
  protected $rateLimit = 0;
  protected $rateLimitRemaining = 0;
  protected $rateLimitReset = 0;
  protected $page = null;
  protected $pageSize = 100;
  protected $pageData = array();

  protected $lastUrl = null;
  protected $lastMethod = null;
  protected $lastExpectedHttpCode = null;
  protected $lastData = null;

  protected $username = null;
  protected $pwd = null;

  public function __construct($username, $pwd, $authType = 'basic')
  {
    $this->username = $username;
    if ($authType == 'basic')
      $this->pwd = 'x-oauth-basic';
  }

  public function setCredentials($username, $pwd, $authType = 'basic')
  {
    $this->username = $username;
    if ($authType == 'basic')
      $this->pwd = 'x-oauth-basic';
  }

  public function setDebug($debug)
  {
    $this->debug = $debug;
  }

  public function getRateLimit()
  {
    return $this->rateLimit;
  }

  public function getRateLimitRemaining()
  {
    return $this->rateLimitRemaining;
  }

  public function getRateLimitResetTime()
  {
    date_default_timezone_set('America/Los_Angeles');
    return date('r', $this->rateLimitReset);
  }

  public function setPage($page = 1)
  {
    $this->page = $page;
  }

  public function setPageSize($pageSize)
  {
    $this->pageSize = $pageSize;
  }

  public function resetPage()
  {
    $this->lastPage = $this->page;
    $this->page = null;
  }

  public function getFirstPage()
  {
    if (isset($this->pageData['first']))
    {
      if (isset($this->pageData['first']['page']))
        $this->page = $this->pageData['first']['page'];

      return $this->requestLast($this->pageData['first']);
    }

    $this->page = 1;
    return $this->requestLast($this>lastData);
  }

  public function getLastPage()
  {
    if (!isset($this->pageData['last']))
    {
      echo "Last page not defined!\n";
      return;
    }

    if (isset($this->pageData['last']['page']))
      $this->page = $this->pageData['last']['page'];

    return $this->requestLast($this->pageData['last']);
  }

  public function getNextPage()
  {
    if (is_null($this->page))
    {
      echo "Next page not defined!\n";
      return;
    }

    if (isset($this->pageData['next']))
    {
      if (isset($this->pageData['next']['page']))
        $this->page = $this->pageData['next']['page'];

      return $this->requestLast($this->pageData['next']);
    }

    $this->page = $this->lastPage + 1;
    return $this->requestLast($this->lastData);
  }

  public function getPreviousPage()
  {
    if (is_null($this->page))
    {
      echo "Previous page not defined!\n";
      return;
    }

    if (isset($this->pageData['prev']))
    {
      if (isset($this->pageData['prev']['page']))
        $this->page = $this->pageData['prev']['page'];

      return $this->requestLast($this->pageData['prev']);
    }

    $this->page = $this->lastPage - 1;
    return $this->requestLast($this->lastData);
  }

  /**
   * doRequest - helper function using CURL for actual request.
   *
   * $contentType - used when method = FILE; currently not supported
   */
  public function doRequest($url, $method, $data, $contentType = null)
  {

    $c = curl_init();
    // curl_setopt($c, CURLOPT_VERBOSE, $this->debug);

    $headers = array('Accept: application/vnd.github.v3+json');
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);

    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_USERAGENT, "mromaine.github-api-play");
    curl_setopt($c, CURLOPT_HEADER, true);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

    curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($c, CURLOPT_USERPWD, "$this->username:$this->pwd");

    if (is_array($data))
    {
      foreach ($data as $key => $value)
      {
        if (is_bool($value))
        {
          $data[$key] = $value ? 'true' : 'false';
        }
      }
    }

    switch ($method)
    {
      case 'GET':
        curl_setopt($c, CURLOPT_HTTPGET, true);
        if (count($data))
        {
          $url .= '?' . http_build_query($data);
        }
        break;

      case 'POST':
        curl_setopt($c, CURLOPT_POST, true);
        if (count($data))
        {
          curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        }
        break;

      case 'PUT':
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($c, CURLOPT_PUT, true);
        
        $headers = array(
          'X-HTTP-Method-Override: PUT', 
          'Content-type: application/x-www-form-urlencoded'
        );
        
        if (count($data))
        {
          $content = json_encode($data, JSON_FORCE_OBJECT);
        
          $fileName = tempnam(sys_get_temp_dir(), 'gitPut');
          file_put_contents($fileName, $content);
   
          $f = fopen($fileName, 'rb');
          curl_setopt($c, CURLOPT_INFILE, $f);
          curl_setopt($c, CURLOPT_INFILESIZE, strlen($content));
        }
        curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
        break; 

      case 'PATCH':
      case 'DELETE':
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);
        if (count($data))
        {
          curl_setopt($c, CURLOPT_POST, true);
          curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        }
        break;
    }

    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);

    if ($this->debug)
    {
      echo "url: $url\nmethod: $method\ndata: ";
      var_dump($data);
    }


    $response = curl_exec($c);

    curl_close($c);

    if ($this->debug)
    {
      echo "Response:\n$response\n";
    }

    return $response;
  }

  /**
   * To support paginated requests, helper function to re-do previous call with updated data
   **/
  public function requestPage(array $data)
  {
    return $this->request($this->lastUrl, $this->lastMethod, $data, $this->lastExpectedHttpCode);
  }

  /**
   * Do the API call. We store relevant data to simplify paginated requests if necessary
   **/
  public function request($url, $method = 'GET', $data = array(), $expectedHttpCode = 200)
  {

    $this->lastUrl = $url;
    $this->lastMethod = $method;
    $this->lastData = $data;
    $this->lastExpectedHttpCode = $expectedHttpCode;

    if (is_array($data) && !is_null($this->page))
    {
      if (!is_numeric($this->page) || $this->page <= 0)
      {
        echo "Page must be a positive value\n";
        $this->resetPage();
        return;
      }

      if (!is_numeric($this->pageSize) || $this->pageSize <= 0 || $this->pageSize > 100)
      {
        echo "Page size must be a positive value, and less than 100 (inclusive)\n";
        $this->resetPage();
        return;
      }

      $data['page'] = $this->page;
      $data['per_page'] = $this->pageSize;

      $this->resetPage();
    }

    $response = $this->doRequest($url, $method, $data);

    return $this->parseResponse($response, $expectedHttpCode);
  }

  /**
   * returns json_decoded array of content
   **/
  public function parseResponse($response, $expectedHttpCode)
  {

    $header = false;
    $content = array();
    $status = 200; // default

    foreach (explode("\r\n", $response) as $line)
    {
      if (strpos($line, 'HTTP/1.1') === 0)
      {
        $lineParts = explode(' ', $line);
        $status = intval($lineParts[1]);
        $header = true;
      }
      elseif ($line == '')
      {
        $header = false;
      }
      elseif ($header)
      {
        $line = explode(': ', $line);
        switch ($line[0])
        {
          case 'Status':
            $status = intval(substr($line[1], 0, 3));
            break;

          case 'X-RateLimit-Limit':
            $this->rateLimit = intval($line[1]);
            break;

          case 'X-RateLimit-Remaining':
            $this->rateLimitRemaining = intval($line[1]);
            break;

          case 'X-RateLimit-Reset':
            $this->rateLimitReset = intval($line[1]);
            break;

          case 'Link':
            $matches = null;
            if (preg_match_all('/<https:\/\/api\.github\.com\/[^?]+\?([^>]+)>; rel="([^"]+)"/', $line[1], $matches))
            {
              foreach ($matches[2] as $index => $page)
              {
                $this->pageData[$page] = array();
                $requestParts = explode('&', $matches[1][$index]);
                foreach ($requestParts as $part)
                {
                  list($field, $value) = explode('=', $requestPart, 2);
                  $this->pageData[$page][$field] = $value;
                }
              }
            }
            break;
        }
      }
      else
      {
        $content[] = $line;
      }
    }

    if ($status !== $expectedHttpCode)
    {
      echo "Expected status [$expectedHttpCode], actual status [$status], URL [{$this->lastUrl}]";
      return null;
    }

    return json_decode(implode("\n", $content), true);
  } // function parseResponse

}

?>
