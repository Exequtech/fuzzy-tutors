<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";

$endpoints['/^auth\/forgotpassword$/'] = [
    'methods' => [
        'POST' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                $conn->begin_transaction() || InternalError("Failed to begin forgotpassword POST transaction");
                $userID = null;
                if(isset($request->email))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `Email` = ? AND `Authorized` = 1 FOR SHARE;", 's', [$request->email], true,
                        "Failed to fetch user (auth forgotpassword POST)");
                    if(empty($matches))
                        MessageResponse(HTTP_OK);
                    $userID = $matches[0]['UserID'];
                }
                else if(isset($request->username))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `Username` = ? AND `Authorized` = 1 FOR SHARE;", 's', [$request->username], true,
                        "Failed to fetch user (auth forgotpassword POST)");
                    if(empty($matches))
                        MessageResponse(HTTP_OK);
                    $userID = $matches[0]['UserID'];
                } else InternalError("Impossible (forgotpassword)");

                // Generate a token
                $token = null;
                $hash = null;
                do
                {
                    $bytes = random_bytes(32);
                    $token = bin2hex($bytes);
                    $hash = hash('sha256', $bytes);
                } while(!empty(BindedQuery($conn, "SELECT 1 FROM `PassToken` WHERE `Token` = ?;", 's', [$hash], true, "Failed to check for unique token (auth forgotpassword POST)")));


                BindedQuery($conn, "INSERT INTO `PassToken`(`Token`, `UserID`) VALUES (?,?);", 'ss', [$hash, $userID], true,
                    "Failed to create passtoken (auth forgotpassword POST)");
                $conn->commit() || InternalError("Failed to commit forgotpassword POST transaction");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'auth/forgotpassword/POST.json'
        ],
        'PATCH' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                $conn->begin_transaction() || InternalError("Failed to begin password change transaction (auth forgotpassword PATCH)");

                $query = "SELECT `u`.`UserID`, `u`.`Password` FROM `User` `u` INNER JOIN `PassToken` `t` ON `t`.`UserID` = `u`.`UserID` WHERE `t`.`Token` = ? FOR UPDATE;";
                $hash = hash('sha256', hex2bin($request->token));
                $matches = BindedQuery($conn, $query, 's', [$hash], true,
                    "Failed to fetch user by token (auth forgotpassword PATCH)");
                
                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_OK);
                }
                if(isset($matches[0]['Password']) && password_verify($request->password, $matches[0]['Password']))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_CONFLICT, "Already the account's password");
                }

                BindedQuery($conn, "UPDATE `User` SET `Password` = ? WHERE `UserID` = ?;", 'si', [password_hash($request->password, PASSWORD_BCRYPT), $matches[0]['UserID']], true,
                    "Failed to update password (auth forgotpassword PATCH)");

                // Cleanup & invalidation of token
                BindedQuery($conn, "DELETE FROM `PassToken` WHERE `UserID` = ? OR `RecordDate` < NOW() - INTERVAL 20 MINUTE;", 'i', [$matches[0]['UserID']], true,
                    "Failed to delete token (auth forgotpassword PATCH)");

                $conn->commit() || InternalError("Failed to commit password change transaction (auth forgotpassword PATCH)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'auth/forgotpassword/PATCH.json'
        ]
    ]
];