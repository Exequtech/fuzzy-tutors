<?php

require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^topic\/([\d]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $topicID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `TopicName`, `SubjectID`, `Description`, `RecordDate` FROM `Topic` WHERE `TopicID` = ?;", 'i', [$topicID], true,
                    "Failed to fetch topic (specific topic GET)");
                
                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $ret = [];
                $ret['subjectId'] = $matches[0]['SubjectID'];
                $ret['name'] = $matches[0]['TopicName'];
                $ret['description'] = $matches[0]['Description'];
                $ret['recordDate'] = $matches[0]['RecordDate'];

                MessageResponse(HTTP_OK, null, ['result' => $ret]);
            },
            'schema-path' => 'topic/specific/GET.json'
        ],
        'DELETE' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $topicID = (int)$regex[1];
                $result = BindedQuery($conn, "DELETE FROM `Topic` WHERE `TopicID` = ?;", 'i', [$topicID], true,
                    "Failed to delete topic record (specific topic DELETE)");

                if(!$result)
                    MessageResponse(HTTP_NOT_FOUND);

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'topic/specific/DELETE.json'
        ],
        'PATCH' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $topicID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `TopicName`, `SubjectID`, `Description`, `RecordDate` FROM `Topic` WHERE `TopicID` = ?;", 'i', [$topicID], true,
                    "Failed to fetch topic (specific topic PATCH)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $topic = $matches[0];

                $sets = [];
                $types = [];
                $values = [];
                // Collect property changes
                if(property_exists($request, 'subjectId') && ($request->subjectId !== $topic['SubjectID']))
                {
                    if(isset($request->subjectId) && !isset($topic['SubjectID']))
                    {
                        $references = BindedQuery($conn, "SELECT `TopicID` FROM `Topic` WHERE `SubjectID` = ?;", 'i', [$topicID], true,
                            "Failed to fetch subject references (specific topic PATCH)");

                        if(!empty($references))
                            MessageResponse(HTTP_CONFLICT, "Can not change to 'topic' type while topics reference item",
                                ['references' => array_map(function($record){return $record['TopicID'];}, $references)]
                            );
                    }
                    $sets[] = '`SubjectID` = ?';
                    $types[] = 'i';
                    $values[] = $request->subjectId;
                }
                if(isset($request->name) && $request->name !== $topic['TopicName'])
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Topic` WHERE `TopicName` = ?;", 's', [$request->name], true,
                        "Failed to fetch topic record (specific topic PATCH)");
                    if(!empty($matches))
                        MessageResponse(HTTP_CONFLICT, "That name already exists.");

                    $sets[] = '`TopicName` = ?';
                    $types[] = 's';
                    $values[] = $request->name;
                }
                if(isset($request->description) && $request->description !== $topic['Description'])
                {
                    $sets[] = '`Description` = ?';
                    $types[] = 's';
                    $values[] = $request->description;
                }

                if(empty($sets))
                    MessageResponse(HTTP_OK);

                $query = 'UPDATE `Topic` SET ' . implode(',',$sets) . ' WHERE `TopicID` = ?;';
                $rowsAffected = BindedQuery($conn, $query, implode($types) . 'i', [...$values, $topicID], true,
                    "Failed to update topic record (specific topic PATCH)");
                    
                if(!$rowsAffected)
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Topic` WHERE `TopicID` = ?;", 'i', [$topicID], true,
                        "Failed to check for topic existence (specific topic PATCH)");
                    if(empty($matches))
                        MessageResponse(HTTP_NOT_FOUND);
                }
                
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'topic/specific/PATCH.json'
        ]
    ]
];