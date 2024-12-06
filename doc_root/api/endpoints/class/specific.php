<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^class\/([\d]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $classID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `ClassName`, `RecordDate` FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if($matches === false)
                    InternalError("Failed to lookup class in specific endpoint (GET)");
                
                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $students = BindedQuery($conn, "SELECT `StudentID` FROM `StudentClass` WHERE `ClassID` = ? ORDER BY `StudentID`;", 'i', [$classID], false);
                if($students === false)
                    InternalError("Failed to lookup students in specific class endpoint (GET)");

                $ret = [];
                $ret['id'] = $classID;
                $ret['name'] = $matches[0]['ClassName'];
                $ret['students'] = array_map(function($record){return $record['StudentID'];}, $students);
                $ret['recordDate'] = $matches[0]['RecordDate'];

                MessageResponse(HTTP_OK, null, ["result" => $ret]);
            },
            'schema-path' => 'class/specific/GET.json'
        ],
        'DELETE' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $classID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT 1 FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if($matches === false)
                    InternalError("Failed to lookup class in specific class endpoint (DELETE)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $conn->begin_transaction();
                
                $success = BindedQuery($conn, "DELETE FROM `StudentClass` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if(!$success)
                {
                    $conn->rollback();
                    InternalError("Failed to detach students in specific class endpoint (DELETE)");
                }

                $success = BindedQuery($conn, "DELETE FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if(!$success)
                {
                    $conn->rollback();
                    InternalError("Failed to delete class in specific class endpoint (DELETE)");
                }

                if(!$conn->commit())
                    InternalError("Failed to commit in specific class endpoint (DELETE)");
                
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'class/specific/DELETE.json'
        ],
        'PATCH' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $classID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT 1 FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if($matches === false)
                    InternalError("Failed to check for class existence in specific class endpoint (PATCH)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                if(!$conn->begin_transaction())
                    InternalError("Failed to begin transaction in specific class endpoint (PATCH)");

                if(isset($request->name))
                {
                    $success = BindedQuery($conn, "UPDATE `Class` SET `ClassName` = ? WHERE `ClassID` = ?;", 'si', [$request->name, $classID], false);
                    if(!$success)
                    {
                        $conn->rollback();
                        InternalError("Failed to update classname in specific class endpoint (PATCH)");
                    }
                }
                if(!isset($request->students))
                {
                    if(!$conn->commit())
                        InternalError("Failed to commit name change on specific class endpoint (PATCH)");
                    MessageResponse(HTTP_OK);
                }

                $matches = BindedQuery($conn, "SELECT `StudentID` FROM `StudentClass` WHERE `ClassID` = ?;", 'i', [$classID], false);
                if($matches === false)
                    InternalError("Failed to fetch related students in specific class endpoint (PATCH)");

                $linkedIDs = array_map(function($record){return $record['StudentID'];}, $matches);

                $createDiffs = array_diff($request->students, $linkedIDs);
                $deleteDiffs = array_diff($linkedIDs, $request->students);

                if(!empty($createDiffs))
                {
                    $query = 'INSERT INTO `StudentClass`(`ClassID`, `StudentID`) VALUES ' 
                        .implode(',', array_map(function($id)use($classID){return "($classID,$id)";},$createDiffs)) . ';';
                    $success = BindedQuery($conn, $query, '', [], false);

                    if(!$success)
                        InternalError("Failed to attach students in specific class endpoint (PATCH)");
                }
                if(!empty($deleteDiffs))
                {
                    $query = 'DELETE FROM `StudentClass` WHERE `StudentID` IN (' . implode(',',$deleteDiffs) . ');';
                    $success = BindedQuery($conn, $query, '', [], false);

                    if(!$success)
                        InternalError("Failed to detach students in specific class endpoint (PATCH)");
                }
                if(!$conn->commit())
                    InternalError("Failed to commit class changes in specific class endpoint (PATCH)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'class/specific/PATCH.json'
        ]
    ]
];