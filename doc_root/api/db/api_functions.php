<?php
require_once __DIR__ . "/../functions/api_responses.php";

function MySQLiErrResponse(int $errCode, string $errStr, string $failContext = null): never
{
    $response = match($errCode)
    {
        1062 => ['status' => HTTP_CONFLICT, 'detail' => 'Unique constraint violation'],
        1452 => ['status' => HTTP_CONFLICT, 'detail' => 'A referenced record does not exist'],
        3819 => ['status' => HTTP_BAD_REQUEST, 'detail' => 'Failed db row validation'],
        default => null
    };
    if($response)
        MessageResponse($response['status'], $response['detail']);
    else
        InternalError($failContext ? "$failContext:\n$errStr" : $errStr);
    exit;
}
function BindedQuery(mysqli $conn, string $query, string $types, array $values, bool $exitOnfailure=true, string $failContext = null): array|int|false
{
    $stmt = $conn->prepare($query);
    if(!$stmt)
    {
        InternalError($failContext ? "$failContext:\n$conn->error" : $conn->error, $exitOnfailure);
        return false;
    }
    if(!empty($values) && !$stmt->bind_param($types, ...$values))
    {
        InternalError($failContext ? "$failContext:\n$conn->error" : $conn->error, $exitOnfailure);
        return false;
    }

    if(!$stmt->execute())
    {
        if($exitOnfailure)
            MySQLiErrResponse($stmt->errno, $stmt->error, $failContext);
        else
            InternalError($failContext ? "$failContext:\n$stmt->error" : $stmt->error, false);
        return false;
    }

    $result = $stmt->get_result();
    if($result == false)
    {
        if($stmt->errno !== 0)
        {
            echo " I am here\n";
            if($exitOnfailure)
            {
                $errCode = $stmt->errno;
                $errStr = $stmt->error;
                $stmt->close();
                MySQLiErrResponse($errCode, $errStr, $failContext);
            }
            $stmt->close();
            InternalError($failContext ? "$failContext:\n$stmt->error" : $stmt->error, $exitOnfailure);
            return false;
        }
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $result->free();
    $stmt->close();
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