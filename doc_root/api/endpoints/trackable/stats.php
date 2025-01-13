<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^stats\/trackables\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                if(isset($request->startDate) && !ValidateDate($request->startDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Bad start date");
                if(isset($request->endDate) && !ValidateDate($request->endDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Bad end date");
                    
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                if(isset($request->subjects) && empty($request->subjects))
                    MessageResponse(HTTP_OK, null, ['results' => []]);
                if(isset($request->students) && empty($request->students))
                    MessageResponse(HTTP_OK, null, ['results' => []]);

                $conn->begin_transaction() || InternalError("Failed to start read transaction (stats trackable GET)");

                if(isset($request->trackables) && !empty($request->trackables)) {
                    $query = "SELECT `TrackableName` FROM `Trackable` WHERE `TrackableName` IN ("
                        .implode(',',array_fill(0, count($request->trackables), '?')) . ") FOR SHARE;";
                    $types = str_repeat('s', count($request->trackables));
                    $values = $request->trackables;

                    $matches = BindedQuery($conn, $query, $types, $values, true,
                        "Failed to fetch trackables (stats trackable GET)");
                    if(count($matches) !== count($request->trackables)) {
                        $existingNames = array_column($matches, 'TrackableName');
                        $diffs = array_diff($request->trackables, $existingNames);
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some trackables don't exist", ['invalid_strings' => $diffs]);
                    }
                }
                $students = [];
                $names = [];
                if(isset($request->students))
                {
                    $ids = array_map('intval', $request->students);

                    $query = "SELECT `Username`, `UserID` FROM `User` WHERE `UserID` IN (" . implode(',', $ids) . ") AND UserType = ? FOR SHARE;";
                    $matches = BindedQuery($conn, $query, 'i', [ROLE_STUDENT], true,
                        "Failed to fetch student records (stats trackable GET)");

                    if(count($matches) !== count($ids)) {
                        $existingIDs = array_column($matches, 'UserID');
                        $diffs = array_diff($ids, $existingIDs);

                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some student IDS do not exist", ['invalid_ids' => $diffs]);
                    }
                    foreach($matches as $record)
                        $names[$record['UserID']] = $record['Username'];
                    $students = $ids;
                } else if(isset($request->classId)) {
                    $classId = intval($request->classId);

                    // Check existence and lock for synchronization
                    $matches = BindedQuery($conn, "SELECT 1 from `Class` WHERE `ClassID` = ? FOR SHARE;", 'i', [$classId], true,
                        "Failed to fetch class record (stats trackable GET)");
                    if(empty($matches)) {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Non-existent class id");
                    }

                    $query = "SELECT `StudentID`, `Username` FROM `Class` LEFT JOIN `StudentClass` ON `Class`.`ClassID` = `StudentClass`.`ClassID` INNER JOIN `User` ON `User`.`UserID` = `StudentID` WHERE `Class`.`ClassID` = ?;";
                    $matches = BindedQuery($conn, $query, 'i', [$classId], true,
                        "Failed to fetch studentclass records (stats trackable GET)");

                    $students = array_column($matches, 'StudentID');
                    foreach($matches as $record)
                        $names[$record['StudentID']] = $record['Username'];
                }
                if(empty($students)) {
                    $conn->rollback();
                    MessageResponse(HTTP_OK, null, ['results' => []]);
                }

                $conditions = [];
                $values = [];
                $types = '';
                if(isset($request->startDate)) {
                    $conditions[] = '(`LessonStart` >= ? OR `LessonEnd` >= ?)';
                    $values[] = $request->startDate;
                    $values[] = $request->startDate;
                    $types .= 'ss';
                }
                if(isset($request->endDate)) {
                    $conditions[] = '(`LessonStart` <= ? OR `LessonEnd` <= ?)';
                    $values[] = $request->endDate;
                    $values[] = $request->endDate;
                    $types .= 'ss';
                }
                if(isset($request->subjects)) {
                    $ids = array_map('intval', $request->subjects);
                    $query = "SELECT `TopicID`, `SubjectID` FROM `Topic` WHERE `TopicID` IN (" . implode(',', $ids) . ") FOR SHARE;";
                    $matches = BindedQuery($conn, $query, '', [], true,
                        "Failed to fetch subjects (stats trackable GET)");
                    $topics = array_filter($matches, function($match){
                        return isset($match['SubjectID']);
                    });
                    if(!empty($topics)) {
                        $topics = array_column($topics, 'TopicID');
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some provided subject ids are topics", ['invalid_ids' => $topics]);
                    }
                    if(count($matches) !== count($ids)) {
                        $existingIDs = array_column($matches, 'TopicID');
                        $diffs = array_diff($ids, $existingIDs);
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some provided subject ids don't exist", ['invalid_ids' => $diffs]);
                    }

                    $conditions[] = "`SubjectID` IN (" . implode(',', $ids) . ")";
                }

                $query = "SELECT `LessonID` FROM `Lesson` "
                    .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions)) . " FOR SHARE;";
                $matches = BindedQuery($conn, $query, $types, $values, true,
                    "Failed to fetch lesson records (stats trackable GET)");

                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_OK, null, ['results' => []]);
                }
                $lessonIDs = array_column($matches, 'LessonID');

                // Trackables
                $conditions = ["`LessonID` IN (" . implode(',', $lessonIDs) . ")"];
                $types = '';
                $values = [];
                if(isset($request->trackables)) {
                    if(empty($request->trackables)) {
                        $conditions[] = 'FALSE';
                    } else {
                        $conditions[] = "`TrackableName` IN (" . implode(',', array_fill(0, count($request->trackables), '?')) . ")";
                        $types .= str_repeat('s', count($request->trackables));
                        $values = [...$values, ...$request->trackables];
                    }

                }

                $query = "SELECT `StudentID`, `TrackableName`, SUM(`Value`) `Truths`, COUNT(*) `Total` FROM `Attendance` "
                    ."LEFT JOIN `TrackableValue` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID` "
                    ."WHERE " . implode(' AND ', $conditions)
                    ." GROUP BY `StudentID`, `TrackableName`;";
                //$query = "SELECT `StudentID`, `TrackableName`, SUM(`TrackableValue`.`Value`) `Truths`, COUNT(*) `Total` FROM `TrackableValue` RIGHT JOIN `Attendance` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID` WHERE `LessonID` IN (" . implode(',', $lessonIDs) . ") AND `StudentID` IN (" . implode(',', $students) . ") GROUP BY `StudentID`, `TrackableName`;";
                $matches = BindedQuery($conn, $query, $types, $values, true,
                       "Failed to fetch trackable records (stats trackable GET)");
                
                $results = [];
                // Set students
                foreach($students as $id) {
                    $studentName = $names[$id];
                    $results[$studentName] = [
                        'id' => $id,
                        'trackables' => [],
                    ];
                }
                foreach($matches as $record) {
                    $studentName = $names[$record['StudentID']];
                    if(!isset($results[$studentName]))
                        $trackables[$studentName] = [];
                    if(!isset($record['TrackableName']))
                        continue;
                    if(isset($request->trackables) && !in_array($record['TrackableName'], $request->trackables))
                        continue;
                    $results[$studentName]['trackables'][$record['TrackableName']] = [
                        'truths' => intval($record['Truths']),
                        'total' => $record['Total']
                    ];
                }

                // Set unassigned trackables to zeros
                if(isset($request->trackables)) {
                    foreach($results as $key => $value) {
                        foreach($request->trackables as $name) {
                            if(!isset($value['trackables'][$name])) {
                                $trackables[$key]['trackables'][$name] = [
                                    'truths' => 0,
                                    'total' => 0,
                                ];
                            }
                        }
                    }
                }
                
                $conn->commit();
                MessageResponse(HTTP_OK, null, ['results' => $results]);
            },
            'schema-path' => 'trackable/stats/GET.json'
        ]
    ]
];


