<?php
require_once "db/init.php";

require_once "functions/api_responses.php";
require_once "functions/response_codes.php";
require_once "functions/validation.php";

define('API_ROOT', __DIR__);
const API_PAGE_SIZE = 10;
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
    if(!isset($endpoint['schema-path']) || !file_exists('schema/' . $endpoint['schema-path']))
        InternalError("API validation: Failed to resolve $key (method " . $_SERVER['REQUEST_METHOD'] . ") schema file (schema-path non-existent or undefined)");

    $schema = json_decode(file_get_contents('schema/' . $endpoint['schema-path']) , false);
    $data = null;
    if($_SERVER['REQUEST_METHOD'] !== 'GET')
    {
        $request_body = file_get_contents('php://input');
        if($request_body !== "")
        {
            $data = json_decode($request_body, false);
            if($data === null)
                MessageResponse(HTTP_BAD_REQUEST, "Malformed JSON");
        }
    }
    else
    {
        $data = new stdClass();
        foreach($_GET as $key => $value)
        {
            if($key == 'path')
                continue;
            $data->$key = $value;
        }
    }

    $valid = Validate($schema, $data);
    if($valid !== true)
    {
        $errors = [];
        foreach($valid as $err)
        {
            $errors[] = $err['property'] . ' : ' . $err['message'];
        }
        MessageResponse(HTTP_BAD_REQUEST, $errors);
    }

    $endpoint['callback']($data, $conn, $matches);
}

MessageResponse(HTTP_NOT_FOUND, 'That endpoint does not exist.');
