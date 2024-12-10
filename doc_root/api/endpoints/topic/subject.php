<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^subject\/?$/'] = [
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
                $conditions = ['`SubjectID` IS NULL'];
                if(isset($request->id))
                {
                    $conditions[] = '`TopicID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->name))
                {
                    $conditions[] = '`TopicName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }

                $order = '';
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'id' => '`TopicID`',
                        'name' => '`TopicName`',
                        'description' => '`Description`'
                    };
                    if(isset($request->order))
                        $order .= match($request->order)
                        {
                            'asc' => ' ASC',
                            'desc' => ' DESC'
                        };
                }

                $subjectQuery = "SELECT `TopicID`, `TopicName`, `Description`, `RecordDate` FROM `Topic`"
                    .' WHERE ' . implode(' AND ', $conditions)
                    . $order . " LIMIT $offset, $pageSize";
                $fullQuery = "SELECT `s`.`TopicID` SubjectID, `s`.`TopicName` SubjectName, `s`.`Description` SubjectDescription, `s`.`RecordDate` SubjectDate,"
                            ."`t`.`TopicID`, `t`.`TopicName`, `t`.`Description` TopicDescription, `t`.`RecordDate` TopicDate "
                            ."FROM ($subjectQuery) `s` LEFT JOIN `Topic` `t` ON `s`.`TopicID` = `t`.`SubjectID`;";

                // echo $fullQuery;
                $results = BindedQuery($conn, $fullQuery, implode($types), $values, true,
                    "Failed to fetch topics (subject topic GET)");

                $subjects = [];
                foreach($results as $record)
                {
                    $subjectID = $record['SubjectID'];
                    $obj = $subjects[$subjectID] ?? [];
                    if(!isset($subjects[$subjectID]))
                    {
                        $obj['id'] = $subjectID;
                        $obj['name'] = $record['SubjectName'];
                        $obj['topics'] = [];
                        $obj['recordDate'] = $record['SubjectDate'];
                    }
                    if(isset($record['TopicID']))
                    {
                        $topic = [];
                        $topic['id'] = $record['TopicID'];
                        $topic['name'] = $record['TopicName'];
                        $topic['description'] = $record['TopicDescription'];
                        $topic['recordDate'] = $record['TopicDate'];
                        $obj['topics'][] = $topic;
                    }
                    $subjects[$subjectID] = $obj;
                }
                MessageResponse(HTTP_OK, null, ['results' => array_values($subjects)]);
            },
            'schema-path' => 'topic/subject/GET.json'
        ]
    ]
];