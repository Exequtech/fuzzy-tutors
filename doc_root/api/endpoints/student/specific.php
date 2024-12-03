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
                $user = GetUser(false);
                if(!$user)
                    MessageResponse(HTTP_UNAUTHORIZED);
                if(!in_array($user['UserType'], [ROLE_OWNER, ROLE_TUTOR]))
                    MessageResponse(HTTP_FORBIDDEN, "Insufficient role.");

                $matches = BindedQuery($conn, "SELECT `UserID`, `Username`, `Email`, `Authorized`, `RecordDate` FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [(int)$regex[1], ROLE_STUDENT]);
                if(!$matches)
                    InternalError("Failed to lookup user at specific student endpoint (GET)");

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
                $user = GetUser();
                if(!$user)
                    MessageResponse(HTTP_UNAUTHORIZED);
                if(!in_array($user['UserType'], [ROLE_OWNER, ROLE_TUTOR]))
                    MessageResponse(HTTP_FORBIDDEN, "Insufficient role.");

                $id = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT * FROM `User` WHERE `UserID` = ? AND `UserType` = ?;", 'ii', [$id, ROLE_STUDENT]);

                if(!$matches)
                    InternalError("Failed to lookup user at specific student endpoint (PATCH)");

                if(count($matches) !== 1)
                    MessageResponse(HTTP_NOT_FOUND);

                $sets = [];
                $types = [];
                $values = [];

                if(isset($request->username))
                {
                    $sets[] = ' `Username` = ?';
                    $types[] = 's';
                    $values[] = $request->username;
                }
                if(isset($request->email))
                {
                    $sets[] = ' `Email` = ?';
                    $types[] = 's';
                    $values[] = $request->email;
                }
                if(isset($request->password))
                {
                    $sets[] = ' `Password` = ?';
                    $types[] = 's';
                    $values[] = password_hash($request->password, PASSWORD_BCRYPT);
                }
                if(isset($request->authorized))
                {
                    $sets[] = ' `Authorized` = ?';
                    $types[] = 'i';
                    $values[] = $request->authorized ? 1 : 0;
                }

                if(count($sets) === 0)
                    MessageResponse(HTTP_OK);

                $query = "UPDATE `User` SET" . implode(',', $sets) . " WHERE `UserID` = ?;";
                $success = BindedQuery($conn, $query, implode('', $types) . 'i', [...$values, $id]);
                if(!$success)
                    InternalError("Student updated failed in specific student endpoint (PATCH)");
                else
                    MessageResponse(HTTP_OK);
            },
            'schema-path' => 'student/specific/PATCH.json'
        ]
    ]
];