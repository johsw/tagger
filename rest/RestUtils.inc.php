<?php
require_once 'RestResponse.inc.php';

class RestUtils {
  public static function processRequest() {
    // get our verb
    $request_method = strtolower($_SERVER['REQUEST_METHOD']);
    $response = new RestResponse();
    // we'll store our data here
    $data = array();

    switch ($request_method) {
      // gets are easy...
      case 'get':
        $data = $_GET;
        break;
      // so are posts
      case 'post':
        $data = $_POST;
        break;
      // here's the tricky bit...
      case 'put':
        // basically, we read a string from PHP's special input location,
        // and then parse it out into an array via parse_str... per the PHP docs:
        // Parses str  as if it were the query string passed via a URL and sets
        // variables in the current scope.
        parse_str(file_get_contents('php://input'), $put_vars);
        $data = $put_vars;
        break;
      default:
        // TODO. Hvis vi når hertil bør vi nok råbe av.
        break;
    }
    if (isset($_GET['q'])) {
      $data['q'] = $_GET['q'];
    }

    // store the method
    $response->setMethod($request_method);

    // set the raw data, so we can access it if needed (there may be
    // other pieces to your requests)
    $response->setRequestVars($data);

    if(isset($data['data']))
    {
      // TODO
      // translate the JSON to an Object for use however you want
      $response->setData(json_decode($data['data']));
    }

    $path = $response->getRequestVars();
    if (!isset($path['q'])) {
      die(RestUtils::sendResponse(404));
    }

    $path_array = explode('/', $path['q']);
    if (!isset($path_array[0])) {
      die(RestUtils::sendResponse(404));
    }

    // Set the controller.
    $controller = RestUtils::getController($response, $path_array);
    $controller->process();

    // TODO. Right now we only return json.
    $body = json_encode($controller->getProcessedResponse());
    RestUtils::sendResponse(200, $body, 'application/json');
  }


  public static function sendResponse($status = 200, $body = '', $content_type = 'text/html') {
    $status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
    // set the status
    header($status_header);
    // set the content type
    header('Content-type: ' . $content_type . '; charset=utf-8');

    // pages with body are easy
    if($body != '') {
      // send the body
      echo $body;
      exit;
    }
    // we need to create the body if none is passed
    else {
      // create some body messages
      $message = '';

      // this is purely optional, but makes the pages a little nicer to read
      // for your users.  Since you won't likely send a lot of different status codes,
      // this also shouldn't be too ponderous to maintain
      switch($status) {
        case 401:
          $message = 'You must be authorized to view this page.';
          break;
        case 404:
          $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
          break;
        case 500:
          $message = 'The server encountered an error processing your request.';
          break;
        case 501:
          $message = 'The requested method is not implemented.';
          break;
      }

      // servers don't always have a signature turned on (this is an apache directive "ServerSignature On")
      $signature = 'Tagger at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'];

      // this should be templatized in a real-world solution
      $body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
                <title>' . $status . ' ' . RestUtils::getStatusCodeMessage($status) . '</title>
              </head>
              <body>
                <h1>' . RestUtils::getStatusCodeMessage($status) . '</h1>
                <p>' . $message . '</p>
                <hr />
                <address>' . $signature . '</address>
              </body>
            </html>';

      echo $body;
      exit;
    }
  }


  public static function getStatusCodeMessage($status) {
    // these could be stored in a .ini file and loaded
    // via parse_ini_file()... however, this will suffice
    // for an example
    $codes = Array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );

    return (isset($codes[$status])) ? $codes[$status] : '';
  }

  private static function getController(RestResponse $response, $path_array) {
    // Check versions of the api.
    if ('v1' != $path_array[0]) {
      die(RestUtils::sendResponse(501));
    }
    try {
      if ('tag' == $path_array[1]) {
        include 'controllers/TagController.inc.php';
        return new TagController(
            $response->getRequestVars('text'),
            $response->getRequestVars('ner'),
            $response->getRequestVars('disambiguate'),
            $response->getRequestVars('uris'),
            $response->getRequestVars('unmatched'),
            $response->getRequestVars('markup'),
            $response->getRequestVars('nl2br')
            );
      }
    }
    catch (Exception $e) {
      die(RestUtils::sendResponse(400));
    }
    die(RestUtils::sendResponse(404));
  }

  // TODO.. denne gør ikke noget endnu. den skal lige kaldes ;)
  private static function authenticate() {
    // figure out if we need to challenge the user
    if(empty($_SERVER['PHP_AUTH_DIGEST']))
    {
      header('HTTP/1.1 401 Unauthorized');
      header('WWW-Authenticate: Digest realm="' . AUTH_REALM . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5(AUTH_REALM) . '"');

      // show the error if they hit cancel
      die(RestControllerLib::error(401, true));
    }

    // now, analayze the PHP_AUTH_DIGEST var
    if(!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) || $auth_username != $data['username'])
    {
      // show the error due to bad auth
      die(RestUtils::sendResponse(401));
    }

    // so far, everything's good, let's now check the response a bit more...
    $A1 = md5($data['username'] . ':' . AUTH_REALM . ':' . $auth_pass);
    $A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
    $valid_response = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);

    // last check..
    if($data['response'] != $valid_response)
    {
      die(RestUtils::sendResponse(401));
    }
  }
}
?>