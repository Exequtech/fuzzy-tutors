<?php

require_once __DIR__ . "/response_codes.php";

function InternalError($error, $exit = true): never
{
    error_log($error);
    if($exit)
        MessageResponse(HTTP_INTERNAL_ERROR);
    exit;
}
function MessageResponse($code, $detail = null, $overrides = []): never
{
    $body = [
        'status' => $code,
        'message' => GetStatusMessage($code), 
        ...$overrides
    ];
    if(isset($detail))
        $body['detail'] = $detail;
    DetailedResponse($code, $body);
    exit;
}

function DetailedResponse($code, $body): never
{
    header("Content-Type: application/json", true, $code);
    echo json_encode($body);
    global $conn;
    $conn->close();
    exit;
}