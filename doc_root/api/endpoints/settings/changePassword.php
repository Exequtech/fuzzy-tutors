<?php

require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/functions/api_responses.php";

$endpoints['/^settings\/password$/'] = [
    'methods' => [
        'PUT' => [
            'callback' => function(object $request, mysqli $conn, array $regex) {
                $usr = GetUser(false);
                if($usr === null || !password_verify($request->oldPassword, $usr['Password'])) {
                    MessageResponse(HTTP_UNAUTHORIZED);
                }

                $hash = password_hash($request->newPassword, PASSWORD_BCRYPT);
                BindedQuery($conn, "UPDATE `User` SET `Password` = ? WHERE `UserID` = ?;", 'si', [$hash, $usr['UserID']], true,
                    "Failed to change usr password (password settings PUT)");

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'settings/password/PUT.json'
        ]
    ],
];
