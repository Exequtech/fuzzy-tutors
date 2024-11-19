<?php
require_once "db/init.php";

require_once "functions/api_responses.php";
require_once "functions/response_codes.php";
require_once "functions/validation.php";

define('API_ROOT', __DIR__);
$endpoints = [];

require_once "endpoints/include.php";

foreach($endpoints as $key => $value)
{
    $matches;
    if(!preg_match($key, $_GET['path'], $matches))
        continue;
    if(!isset($value['methods'][$_SERVER['REQUEST_METHOD']]))
        MessageResponse(HTTP_NOT_IMPLEMENTED);
    $endpoint = $value['methods'][$_SERVER['REQUEST_METHOD']];
    $schema = json_decode(file_get_contents( 'schema/' . $endpoint['schema-path']) , false);
    $data = json_decode(file_get_contents('php://input'), false);

    if($data === null)
        MessageResponse(HTTP_BAD_REQUEST, "Malformed JSON");

    $valid = Validate($schema, $data);
    if($valid === true)
    {
        $endpoint['callback']($data, $conn, $matches);
    }
    else
    {
        $errors = [];
        foreach($valid as $err)
        {
            $errors[] = $err['property'] . ' : ' . $err['message'];
        }
        MessageResponse(HTTP_BAD_REQUEST, $errors);
    }
}

MessageResponse(HTTP_NOT_FOUND);