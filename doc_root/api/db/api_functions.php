<?php
require_once __DIR__ . "/../functions/api_responses.php";

function BindedQuery($conn, $query, $types, $values, $exitOnfailure=true)
{
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$values);

    if(!$stmt->execute())
    {
        if($exitOnfailure)
            InternalError($stmt->error);
        else
            return false;
    }

    $result = $stmt->get_result();
    if($result == false)
    {
        if(($stmt->errno !== 0) && $exitOnfailure)
            InternalError($stmt->error);
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