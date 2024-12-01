<?php
require_once __DIR__ . "/../config.php";

if(defined('DB_INITIATED'))
    return;
$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);

if($conn->connect_error)
{
    http_response_code(500);
    exit();
}

const DB_INITIATED = true;