<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^student\/([\d]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $matches = BindedQuery($conn, "SELECT `UserID`, `Username`, `Email`, `Authorized`, `RecordDate` FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [(int)$regex[1], ROLE_STUDENT], true,
                    "Failed to fetch user (specific student GET)");

                if(count($matches) !== 1)
                    MessageResponse(HTTP_NOT_FOUND);

                $ret = [];
                $ret['id'] = $matches[0]['UserID'];
                $ret['username'] = $matches[0]['Username'];
                $ret['email'] = $matches[0]['Email'];
                $ret['authorized'] = $matches[0]['Authorized'] ? true : false;
                $ret['recordDate'] = $matches[0]['RecordDate'];
                MessageResponse(HTTP_OK, null, [
                    'result' => $ret
                ]);
            },
            'schema-path' => 'student/specific/GET.json'
        ],
        'PATCH' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $id = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `Username`, `Email`, `Authorized` FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [$id, ROLE_STUDENT], true,
                    "Failed to fetch student (specific student PATCH)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $student = $matches[0];

                $sets = [];
                $types = [];
                $values = [];

                if(isset($request->username) && $request->username !== $student['Username'])
                {
                    $sets[] = ' `Username` = ?';
                    $types[] = 's';
                    $values[] = $request->username;
                }
                if(isset($request->email) && $request->email !== $student['Email'])
                {
                    $sets[] = ' `Email` = ?';
                    $types[] = 's';
                    $values[] = $request->email;
                }
                if(isset($request->authorized) && $request->authorized === ($student['Authorized'] === 1))
                {
                    $sets[] = ' `Authorized` = ?';
                    $types[] = 'i';
                    $values[] = $request->authorized ? 1 : 0;
                }

                if(empty($sets))
                    MessageResponse(HTTP_OK);

                $query = "UPDATE `User` SET" . implode(',', $sets) . " WHERE `UserID` = ?;";
                $success = BindedQuery($conn, $query, implode('', $types) . 'i', [...$values, $id], true,
                    "Failed to update student (specific student PATCH)");

                if(!$success)
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [$id, ROLE_STUDENT], true,
                        "Failed to check for student existence (student specific PATCH)");
                    if(empty($matches))
                        MessageResponse(HTTP_NOT_FOUND);
                }

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'student/specific/PATCH.json'
        ],
        'DELETE' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $id = (int)$regex[1];
                $success = BindedQuery($conn, "DELETE FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [$id, ROLE_STUDENT], true,
                    "Failed to delete student (specific student DELETE)");
                if(!$success)
                    MessageResponse(HTTP_NOT_FOUND);

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'student/specific/DELETE.json'
        ]
    ]
];