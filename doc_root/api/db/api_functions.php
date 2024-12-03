<?php
require_once __DIR__ . "/../functions/api_responses.php";

function BindedQuery(mysqli $conn, string $query, string $types, array $values, bool $exitOnfailure=true): array|bool
{
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$values);

    if(!$stmt->execute())
    {
        InternalError($stmt->error, $exitOnfailure);
        return false;
    }

    $result = $stmt->get_result();
    if($result == false)
    {
        if($stmt->errno !== 0)
            InternalError($stmt->error, $exitOnfailure);
        return $stmt->errno == 0;
    }

    $rows = [];
    while(($row = $result->fetch_assoc()) !== null)
    {
        $rows[] = $row;
    }

    $result->free();
    return $rows;
}

function CreateUser(mysqli $conn, string $username, string $email, int $userType, bool $authorized = false, string $password = null, bool $exitOnFailure = true): array|bool
{
    $success = isset($password) ?
        BindedQuery($conn, "INSERT INTO `User`(`Username`, `Email`, `UserType`, `Authorized`, `Password`, `RecordDate`) VALUES (?, ?, ?, ?, ?, NOW());", 'ssiis', [
            $username,
            $email,
            $userType,
            $authorized,
            password_hash($password, PASSWORD_BCRYPT)
        ], $exitOnFailure) :
        BindedQuery($conn, "INSERT INTO `User`(`Username`, `Email`, `UserType`, `Authorized`, `RecordDate`) VALUES (?, ?, ?, ?, NOW());", "ssii", [
            $username,
            $email,
            $userType,
            $authorized
        ], $exitOnFailure);
    if(!$success)
    {
        InternalError("Failed to create user.", $exitOnFailure);
        return false;
    }
    else
    {
        $matches = BindedQuery($conn, "SELECT * FROM `User` WHERE Username = ?;", 's', [$username], $exitOnFailure);
        if(!$matches || count($matches) === 0)
        {
            InternalError("User disappeared during record creation", $exitOnFailure);
            return false;
        }
        return $matches[0];
    }
}