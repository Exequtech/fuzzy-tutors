<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^topic\/?$/'] = [
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
                    $conditions[] = '`TopicID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->subjectId))
                {
                    if($request->subjectId === "null")
                    {
                        $conditions[] = '`SubjectID` IS NULL';
                    }
                    else
                    {
                        $conditions[] = '`SubjectID` = ?';
                        $types[] = 'i';
                        $values[] = (int)$request->subjectId;
                    }
                }
                if(isset($request->name))
                {
                    $conditions[] = '`TopicName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }
                if(isset($request->description))
                {
                    $conditions[] = '`Description` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->description) . '%';
                }

                $order = '';
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'id' => '`TopicID`',
                        'subjectId' => '`SubjectID`',
                        'name' => '`TopicName`',
                        'description' => '`Description`',
                    };
                    if(isset($request->order))
                        $order .= match($request->order)
                        {
                            'asc' => ' ASC',
                            'desc' => ' DESC',
                        };
                }

                $query = "SELECT `TopicID`, `SubjectID`, `TopicName`, `Description`, `RecordDate` FROM `Topic`"
                    .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions))
                    . $order . " LIMIT $offset, $pageSize;";
                $matches = BindedQuery($conn, $query, implode($types), $values, true,
                    "Failed to fetch topics (toplevel topic GET)");
                
                MessageResponse(HTTP_OK, null, ['results' => array_map(function($topic)
                {
                    $ret = [];
                    $ret['id'] = $topic['TopicID'];
                    $ret['subjectId'] = $topic['SubjectID'];
                    $ret['name'] = $topic['TopicName'];
                    $ret['description'] = $topic['Description'];
                    $ret['recordDate'] = $topic['RecordDate'];
                    return $ret;
                }, $matches)]);
            },
            'schema-path' => 'topic/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $conn->begin_transaction() || InternalError("Failed to begin topic creation transaction (toplevel topic POST)");
                $types = ['s'];
                $values = [$request->name];
                // Collect optional properties provided
                $properties = [];
                if(isset($request->subjectId))
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Topic` WHERE `TopicID` = ? AND `SubjectID` IS NULL;", 'i', [$request->subjectId], false);
                    if($matches === false)
                    {
                        $conn->rollback();
                        InternalError("Failed to check for subject existence (toplevel topic POST)");
                    }
                    if(empty($matches))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Subject ID does not exist / is not a subject");
                    }
                    $properties[] = '`SubjectID`';
                    $types[] = 'i';
                    $values[] = $request->subjectId;
                }
                if(isset($request->description))
                {
                    $properties[] = '`Description`';
                    $types[] = 's';
                    $values[] = $request->description;
                }

                $query = "INSERT INTO `Topic`(`TopicName`" .(empty($properties) ? '' : ',' . implode(',',$properties)) . ')'
                    . ' VALUES (?' . str_repeat(',?', count($properties)) . ');';
                $success = BindedQuery($conn, $query, implode($types), $values, false);

                if(!$success)
                {
                    $conn->rollback();
                    InternalError("Failed to insert topic record (toplevel topic POST)");
                }

                $conn->commit() || InternalError("Failed to commit topic creation transaction (toplevel topic POST)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'topic/toplevel/POST.json'
        ]
    ]
];