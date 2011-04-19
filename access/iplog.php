<?php
/**
 * This is super lo-fi security, but try to disallow unwanted calls to the
 * webservice.
 */

global $conf;

// If the allowed referers array is empty, we can skip the hostname check
if (!empty($conf['service_allow_referer'])) {
  // Get the hostname of the referer...
  $host = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $host = $referer['host'];
  }

  // ...and bail out if it's not in the list of allowed hostnames
  if (!in_array($host, $conf['service_allow_referer'])) {
    RestUtils::sendResponse(403);
  }
}
