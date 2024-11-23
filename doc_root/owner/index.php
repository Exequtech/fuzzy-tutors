<?php
require_once "../functions/mime.php";
require_once "../api/functions/authentication.php";
require_once "../api/functions/user_types.php";

$authenticated = FullAuthenticate(false);
if(!$authenticated)
{
    http_response_code(401);
    exit;
}
if(!in_array(GetUser()['UserType'], [ROLE_OWNER]))
{
    http_response_code(403);
    exit;
}

$path = $_GET['path'];
$file_contents = file_get_contents($path);
if(gettype($file_contents) !== 'string')
{
    http_response_code(500);
    exit;
}


$etag = md5($file_contents);
header("Cache-Control: no-cache");
header("ETag: $etag");
header("Content-Type: " . GetMimeType($path));

$headers = apache_request_headers();
if(isset($headers['If-None-Match']) && $headers['If-None-Match'] == $etag)
{
    http_response_code(304);
    exit;
}

echo $file_contents;