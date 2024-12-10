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
                $matches = BindedQuery($conn, "SELECT `ClassName`, `RecordDate` FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], true,
                    "Failed to fetch class (specific class GET)");
                
                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $students = BindedQuery($conn, "SELECT `StudentID` FROM `StudentClass` WHERE `ClassID` = ? ORDER BY `StudentID`;", 'i', [$classID], true,
                    "Failed to fetch students (specific class GET)");

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
                $changes = BindedQuery($conn, "DELETE FROM `Class` WHERE `ClassID` = ?;", 'i', [$classID], true,
                    "Failed to delete class (specific class DELETE)");

                if(!$changes)
                    MessageResponse(HTTP_NOT_FOUND);
                
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'class/specific/DELETE.json'
        ],
        'PATCH' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $conn->begin_transaction() || InternalError("Failed to begin class edit transaction (specific class PATCH)");
                $classID = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `ClassName` FROM `Class` WHERE `ClassID` = ? FOR UPDATE;", 'i', [$classID], false);
                if($matches === false)
                {
                    $conn->rollback();
                    InternalError("Failed to check for class existence (specific class PATCH)");
                }

                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_NOT_FOUND);
                }


                if(isset($request->name) && $matches[0]['ClassName'] !== $request->name)
                {
                    $success = BindedQuery($conn, "UPDATE `Class` SET `ClassName` = ? WHERE `ClassID` = ?;", 'si', [$request->name, $classID], false);
                    if($success === false)
                    {
                        $conn->rollback();
                        InternalError("Failed to update classname in specific class endpoint (PATCH):\n $conn->error");
                    }
                }
                if(!isset($request->students))
                {
                    $conn->commit() || InternalError("Failed to commit name change transaction (specific class PATCH)");
                    MessageResponse(HTTP_OK);
                }

                $matches = empty($request->students) ? [] : BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',', $request->students) . ');',
                    'i', [ROLE_STUDENT], false);

                if($matches === false)
                {
                    $conn->rollback();
                    InternalError("Failed to fetch student records (specific class PATCH)");
                }
                
                $existing = array_map(function($user){return $user['UserID'];},$matches);
                $existDiffs = array_diff($request->students, $existing);
                if(!empty($existDiffs))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_CONFLICT, "Some provided student ids don't exist.", ['invalid_ids' => $existDiffs]);
                }

                $matches = BindedQuery($conn, "SELECT `StudentID` FROM `StudentClass` WHERE `ClassID` = ?;", 'i', [$classID], false);

                if($matches === false)
                {
                    $conn->rollback();
                    InternalError("Failed to fetch StudentClass relations (specific class PATCH)");
                }

                $linkedIDs = array_map(function($record){return $record['StudentID'];}, $matches);

                $createDiffs = array_diff($request->students, $linkedIDs);
                $deleteDiffs = array_diff($linkedIDs, $request->students);

                if(!empty($createDiffs))
                {
                    $query = 'INSERT INTO `StudentClass`(`ClassID`, `StudentID`) VALUES ' 
                        .implode(',', array_map(function($id)use($classID){return "($classID,$id)";},$createDiffs)) . ';';
                    $success = BindedQuery($conn, $query, '', [], false);

                    if(!$success)
                    {
                        $conn->rollback();
                        InternalError("Failed to add StudentClass records (specific class PATCH)");
                    }
                }
                if(!empty($deleteDiffs))
                {
                    $query = 'DELETE FROM `StudentClass` WHERE `StudentID` IN (' . implode(',',$deleteDiffs) . ');';
                    $success = BindedQuery($conn, $query, '', [], false);

                    if(!$success)
                    {
                        $conn->rollback();
                        InternalError("Failed to remove StudentClass records (specific class PATCH)");
                    }
                }
                $conn->commit() || InternalError("Failed to commit class changes (specific class PATCH)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'class/specific/PATCH.json'
        ]
    ]
];