<?php

require_once __DIR__ . "/../functions/api_responses.php";
require_once __DIR__ . "/../functions/response_codes.php";

function DBValidateNewUser(object|null $request, mysqli $conn)
{
    if($request == null)
        MessageResponse(HTTP_BAD_REQUEST, "No body.");

    if(count(BindedQuery($conn, "SELECT * FROM `User` WHERE `Username` = ?;", 's', [$request->username])) !== 0)
        MessageResponse(HTTP_CONFLICT, "Username already exists.");

    if(count(BindedQuery($conn, "SELECT * FROM `User` WHERE `Email` = ?;", 's', [$request->email])) !== 0)
        MessageResponse(HTTP_CONFLICT, "Email already exists.");
}