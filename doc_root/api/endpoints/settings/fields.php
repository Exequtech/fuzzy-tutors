<?php

require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/functions/api_responses.php";

$endpoints['/^settings\/fields$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object $request, mysqli $conn, array $regex) {
                $usr = GetUser(false);
                if($usr === null) {
                    MessageResponse(HTTP_UNAUTHORIZED);
                } else {
                    MessageResponse(HTTP_OK, null, ['result' => [
                        'id' => $usr['UserID'],
                        'role' => UserTypeIntToStr($usr['UserType']),
                        'name' => $usr['Username'],
                        'email' => $usr['Email'],
                        'joinDate' => $usr['RecordDate'],
                    ]]);
                }
            },
            'schema-path' => 'settings/fields/GET.json'
        ],
        'PATCH' => [
            'callback' => function(object $request, mysqli $conn, array $regex) {
                $usr = GetUser(false);
                if($usr === null) {
                    MessageResponse(HTTP_UNAUTHORIZED);
                }

                if(empty(get_object_vars($request))) {
                    MessageResponse(HTTP_OK);
                }

                $changes = [];
                $types = '';
                $values = [];

                if(isset($request->name) && $request->name !== $usr['Username']) {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `User` WHERE `Username` = ?;", 's', [$request->name], true,
                        "Failed to fetch user by name (fields settings PATCH)");

                    if(!empty($matches)) {
                        MessageResponse(HTTP_CONFLICT, "Username is already in use.");
                    }

                    $changes[] = '`Username` = ?';
                    $types .= 's';
                    $values[] = $request->name;
                }

                if(isset($request->email) && $request->email !== $usr['Email']) {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `User` WHERE `Email` = ?;", 's', [$request->email], true,
                        "Failed to fetch user by email (fields settings PATCH)");
                    if(!empty($matches)) {
                        MessageResponse(HTTP_CONFLICT, "Email is already in use.");
                    }

                    $changes[] = '`Email` = ?';
                    $types .= 's';
                    $values[] = $request->email;
                }

                if(empty($changes))
                    MessageResponse(HTTP_OK);

                $query = "UPDATE `User` SET " . implode(', ', $changes) . " WHERE `UserID` = ?";
                BindedQuery($conn, $query, $types . 'i', [...$values, $usr['UserID']], true,
                    "Failed to update user (fields settings PATCH)");

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'settings/fields/PATCH.json'
        ]
    ]
];
