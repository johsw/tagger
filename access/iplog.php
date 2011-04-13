<?php
/**
 * This is super lo-fi security, but try to disallow unwanted calls to the
 * webservice.
 */
global $conf;
if (!isset($_SERVER['HTTP_REFERER']) && !empty($conf['service_allow_referer'])) {
  RestUtils::sendResponse(403);
}
$referer = parse_url($_SERVER['HTTP_REFERER']);
if (!empty($conf['service_allow_referer']) && !in_array($referer['host'], $conf['service_allow_referer'])) {
  RestUtils::sendResponse(403);
}
?>