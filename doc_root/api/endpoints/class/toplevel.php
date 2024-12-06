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
                $fullQuery = "SELECT `c`.`ClassID`, `c`.`ClassName`, `sc`.`StudentID`, `c`.`RecordDate` FROM ($classQuery) `c`"
                        .' LEFT JOIN (SELECT `StudentID`, `ClassID` FROM `StudentClass` ORDER BY `StudentID`) `sc` ON `c`.`ClassID` = `sc`.`ClassID`;';
                $results = BindedQuery($conn, $fullQuery, implode($types), $values);
                if($results === false)
                    InternalError("Failed to query for classes (GET)");

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
                        if(isset($record['StudentID']))
                            $obj['students'][] = $record['StudentID'];
                        $obj['recordDate'] = $record['RecordDate'];
                        $classes[$classID] = $obj;
                    }
                    else
                        $classes[$classID]['students'][] = $record['StudentID'];
                }
                MessageResponse(HTTP_OK, null, ['results' => array_values($classes)]);
            },
            'schema-path' => 'class/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $matches = BindedQuery($conn, "SELECT * FROM `Class` WHERE `ClassName` = ?;", 's', [$request->name], false);
                if($matches === false)
                    InternalError("Failed to check if classname already exists in class creation (POST)");
                if(!empty($matches))
                    MessageResponse(HTTP_CONFLICT, "Class name already exists");

                if(!empty($request->students))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',',$request->students) . ");",
                            'i', [ROLE_STUDENT], false);
                    if($matches === false)
                        InternalError("Failed to db-validate students to add in class creation (POST)");

                    $existing = array_map(function($user) {
                        return $user['UserID'];
                    }, $matches);
                    $diff = array_diff($request->students, $existing);

                    if(!empty($diff))
                        MessageResponse(HTTP_CONFLICT, "Some student ids don't exist.", ['invalid_ids' => $diff]);
                }
                $conn->begin_transaction();

                $success = BindedQuery($conn, "INSERT INTO `Class`(`ClassName`, `RecordDate`) VALUES (?, NOW());", 's', [$request->name]);
                if(!$success)
                {
                    $conn->rollback();
                    InternalError("Failed to insert class (POST)");
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
                        InternalError("Failed to attach students (POST)");
                    }
                }
                if(!$conn->commit())
                {
                    InternalError("Failed to commit class creation (POST)");
                }
                MessageResponse(HTTP_CREATED);
            },
            'schema-path' => 'class/toplevel/POST.json'
        ]
    ]
];