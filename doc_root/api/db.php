<?php
require_once "config.php";

$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);

if($conn->connect_error)
{
    http_response_code(500);
    exit("Internal error.");
}