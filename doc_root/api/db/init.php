<?php
require_once __DIR__ . "/../config.php";

if(defined('DB_INITIATED'))
    return;
$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);
// mysqli_report(MYSQLI_REPORT_OFF);

if($conn->connect_error)
{
    InternalError("Failed to connect to DB: $conn->connect_error");
}

const DB_INITIATED = true;