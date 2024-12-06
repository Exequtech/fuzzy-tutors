<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/db/escaping.php";

$endpoints['/^student\/?$/'] = [
    'methods' => [
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);
                DBValidateNewUser($request, $conn);

                $user = CreateUser($conn, $request->username, $request->email, ROLE_STUDENT, $request->authorized, $request->password ?? null);
                if($user)
                    MessageResponse(HTTP_CREATED);
                else
                    MessageResponse(HTTP_INTERNAL_ERROR);
            },
            'schema-path' => 'student/toplevel/POST.json',
        ],
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                // Resolve pagination variables
                $pagesize = API_PAGE_SIZE;
                if(isset($request->pageSize))
                {
                    $val = (int)$request->pageSize;
                    if($val > API_PAGE_SIZE)
                        MessageResponse(HTTP_BAD_REQUEST, "Requested page size too big.");
                    $pagesize = $val;
                }
                $page = isset($request->page) ? (int)$request->page : 1;
                $offset = ($page - 1) * $pagesize;

                $values = [ROLE_STUDENT];
                $types = ['i'];
                // Collect filters
                $conditions = ['`UserType` = ?'];
                if(isset($request->id))
                {
                    $conditions[] = '`UserID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->username))
                {
                    $conditions[] = '`Username` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->username) . '%';
                }
                if(isset($request->email))
                {
                    $conditions[] = '`Email` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->email) . '%';
                }
                if(isset($request->authorized))
                {
                    $conditions[] = '`Authorized` = ?';
                    $types[] = 'i';
                    $values[] = $request->authorized == 'true' ? 1 : 0;
                }

                // Set up ordering
                $order = '';
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'username' => '`Username`',
                        'email' => '`Email`',
                        'id' => '`UserID`',
                        'authorized' => '`Authorized`',
                    };
                    if(isset($request->order))
                    {
                        $order .= match($request->order)
                        {
                            'asc' => ' ASC',
                            'desc' => ' DESC',
                        };
                    }
                }

                $query = 'SELECT `UserID`, `Username`, `Email`, `Authorized`, `RecordDate` FROM `User` WHERE ' . implode(' AND ', $conditions) . $order . " LIMIT $offset, $pagesize;";
                $matches = BindedQuery($conn, $query, implode('', $types), $values);
                if($matches === false)
                    InternalError("Failed to fetch students (GET)");
                MessageResponse(HTTP_OK, null, [
                    'results' => array_map(function($user)
                    {
                        $ret = [];
                        $ret['id'] = $user['UserID'];
                        $ret['username'] = $user['Username'];
                        $ret['email'] = $user['Email'];
                        $ret['authorized'] = $user['Authorized'] ? true : false;
                        $ret['recordDate'] = $user['RecordDate'];
                        return $ret;
                    }, $matches)
                ]);
            },
            'schema-path' => 'student/toplevel/GET.json'
        ]
    ]
];