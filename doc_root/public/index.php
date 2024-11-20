<?php
require_once "../functions/mime.php";

$path = $_GET['path'];
$file_contents = file_get_contents($path);
if(gettype($file_contents) !== 'string')
{
    http_response_code(500);
    exit;
}


$etag = md5($file_contents);
header("Cache-Control: no-cache");
header("ETag: \"$etag\"");
header("Content-Type: " . GetMimeType($path));
header("X-Custom-Response: true");

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
    http_response_code(304);
    exit;
}

echo $file_contents;