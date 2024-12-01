<?php
require_once "../doc_root/api/config.php";

$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);

$args = $argv;
array_shift($args);
$username = $args[0];
$email = $args[1];
$password = $args[2];
$usertype = (int)$args[3];

$stmt = $conn->prepare("INSERT INTO `User`(`Username`,`Email`,`Password`,`UserType`,`Authorized`,`RecordDate`) VALUES (?, ?, ?, ?, 1, NOW());");

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt->bind_param('sssi', $username, $email, $hash, $usertype);
$stmt->execute();

$conn->close();