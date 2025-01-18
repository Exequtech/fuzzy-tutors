<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^class\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                // Resolve pagination variables
                $pageSize = API_PAGE_SIZE;
                if(isset($request->pageSize))
                {
                    $val = (int)$request->pageSize;
                    if($val > API_PAGE_SIZE)
                        MessageResponse(HTTP_BAD_REQUEST, "Requested page size too big.");
                    $pageSize = $val;
                }
                $page = isset($request->page) ? (int)$request->page : 1;
                $offset = ($page - 1) * $pageSize;

                $values = [];
                $types = [];
                // Collect filters
                $conditions = [];
                if(isset($request->id))
                {
                    $conditions[] = '`ClassID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->name))
                {
                    $conditions[] = '`ClassName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }

                // Set up ordering
                $order = '';
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'id' => '`ClassID`',
                        'name' => '`Classname`',
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
                $classQuery = 'SELECT `ClassID`, `ClassName`, `RecordDate` FROM `Class`'
                        .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions))
                        . $order . " LIMIT $offset, $pageSize";
                $fullQuery = "SELECT `c`.`ClassID`, `c`.`ClassName`, `sc`.`StudentID`, `u`.`Username`, `c`.`RecordDate` FROM ($classQuery) `c`"
                        .' LEFT JOIN (SELECT `StudentID`, `ClassID` FROM `StudentClass` ORDER BY `StudentID`) `sc` ON `c`.`ClassID` = `sc`.`ClassID`'
                        .' LEFT JOIN `User` `u` ON `sc`.`StudentID` = `u`.`UserID`;';
                $results = BindedQuery($conn, $fullQuery, implode($types), $values, true, "Failed to fetch class records (toplevel class GET)");

                $classes = [];
                foreach($results as $record)
                {
                    $classID = $record['ClassID'];
                    if(!isset($classes[$classID]))
                    {
                        $obj = [];
                        $obj['id'] = $classID;
                        $obj['name'] = $record['ClassName'];
                        $obj['students'] = [];
                        $obj['recordDate'] = $record['RecordDate'];
                        $classes[$classID] = $obj;
                        if(!isset($record['StudentID']))
                            continue;
                    }
                    $classes[$classID]['students'][] = [
                        'id' => $record['StudentID'],
                        'name' => $record['Username'],
                    ];
                }
                MessageResponse(HTTP_OK, null, ['results' => array_values($classes)]);
            },
            'schema-path' => 'class/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $matches = BindedQuery($conn, "SELECT * FROM `Class` WHERE `ClassName` = ?;", 's', [$request->name], true,
                    "Failed to check for existing classname (toplevel class POST)");

                if(!empty($matches))
                    MessageResponse(HTTP_CONFLICT, "Class name already exists");

                if(!empty($request->students))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',',$request->students) . ");",
                            'i', [ROLE_STUDENT], true,
                        "Failed to fetch student records (toplevel class POST)");

                    $existing = array_map(function($user) {
                        return $user['UserID'];
                    }, $matches);
                    $diff = array_diff($request->students, $existing);

                    if(!empty($diff))
                        MessageResponse(HTTP_CONFLICT, "Some provided student ids don't exist.", ['invalid_ids' => $diff]);
                }
                $conn->begin_transaction() || InternalError("Failed to begin class creation transaction (toplevel class POST)");

                $success = BindedQuery($conn, "INSERT INTO `Class`(`ClassName`, `RecordDate`) VALUES (?, NOW());", 's', [$request->name], false);
                if(!$success)
                {
                    $conn->rollback();
                    InternalError("Failed to insert class (toplevel class POST)");
                }
                $classID = $conn->insert_id;

                if(!empty($request->students))
                {
                    $success = BindedQuery($conn, "INSERT INTO `StudentClass`(`StudentID`, `ClassID`) VALUES " 
                        .implode(',',array_map(function($id) use ($classID)
                        {
                            return "($id, $classID)";
                        }, $request->students)), '', [], false);
                    if(!$success)
                    {
                        $conn->rollback();
                        InternalError("Failed to attach students (toplevel class POST)");
                    }
                }
                $conn->commit() || InternalError("Failed to commit class creation (toplevel class POST)");

                MessageResponse(HTTP_CREATED);
            },
            'schema-path' => 'class/toplevel/POST.json'
        ]
    ]
];