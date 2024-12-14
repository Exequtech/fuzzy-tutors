<?php

require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^lesson\/([\d]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $conn->begin_transaction() || InternalError("Failed to begin read transaction (specific lesson GET)");

                $id = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `LessonID`, `LocationID`, `SubjectID`, `LessonStart`, `LessonEnd`, `Notes` FROM `Lesson` WHERE `LessonID` = ? FOR SHARE;", 'i', [$id], true,
                    "Failed to fetch lesson (specific lesson GET)");
                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_NOT_FOUND);
                }

                $lesson = [
                    'id' => $matches[0]['LessonID'],
                    'locationId' => $matches[0]['LocationID'],
                    'locationName' => isset($matches[0]['LocationID']) ? BindedQuery($conn, "SELECT `LocationName` FROM `Location` WHERE `LocationID` = ?;", 'i', [$matches[0]['LocationID']], true,
                                        "Failed to fetch location (specific lesson GET)")[0]['LocationName'] : null,
                    'subjectId' => $matches[0]['SubjectID'],
                    'subjectName' => isset($matches[0]['SubjectID']) ? BindedQuery($conn, "SELECT `TopicName` FROM `Topic` WHERE `TopicID` = ?;", 'i', [$matches[0]['SubjectID']], true,
                                        "Failed to fetch topic (specific lesson GET)")[0]['TopicName'] : null,
                    'startDate' => $matches[0]['LessonStart'],
                    'endDate' => $matches[0]['LessonEnd'],
                    'notes' => $matches[0]['Notes'],
                    'students' => [],
                    'topics' => [],
                    'trackables' => []
                ];

                $studentQuery = "SELECT `StudentID`, `Attended`, `TrackableName`, `Value` FROM `Attendance` LEFT JOIN `TrackableValue` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID`"
                            ." WHERE `LessonID` = ?;";
                $students = BindedQuery($conn, $studentQuery, 'i', [$id], true,
                    "Failed to fetch students (specific lesson GET)");
                foreach($students as $record)
                {
                    if(!isset($lesson['students'][$record['StudentID']]))
                    {
                        $obj = [];
                        $obj['id'] = $record['StudentID'];
                        $obj['attended'] = $record['Attended'] == 1;
                        $obj['trackables'] = [];
                        $lesson['students'][$record['StudentID']] = $obj;
                        if(!isset($record['TrackableName']))
                            continue;
                    }
                    $lesson['students'][$record['StudentID']]['trackables'][$record['TrackableName']] = $record['Value'] === 1;
                }
                $lesson['students'] = array_values($lesson['students']);

                $trackables = BindedQuery($conn, "SELECT `TrackableName` FROM `LessonTrackable` WHERE `LessonID` = ?;", 'i', [$id], true,
                    "Failed to fetch lesson trackables (specific lesson GET)");
                $lesson['trackables'] = array_map(function($record){return $record['TrackableName'];}, $trackables);

                $topics = BindedQuery($conn, "SELECT `TopicID` FROM `LessonTopic` WHERE `LessonID` = ?;", 'i', [$id], true,
                    "Failed to fetch lesson topics (specific lesson GET)");
                $lesson['topics'] = array_map(function($record){return $record['TopicID'];}, $topics);

                $conn->commit();
                MessageResponse(HTTP_OK, null, ['result' => $lesson]);
            },
            'schema-path' => 'lesson/specific/GET.json'
        ],
        'DELETE' => [
            'callback' => function(null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $affectedRows = BindedQuery($conn, "DELETE FROM `Lesson` WHERE `LessonID` = ?;", 'i', [(int)$regex[1]], true,
                    "Failed to delete lesson (specific lesson DELETE)");
                if(!$affectedRows)
                    MessageResponse(HTTP_NOT_FOUND);

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'lesson/specific/DELETE.json'
        ],
        'PATCH' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                if(isset($request->startDate) && !ValidateDate($request->startDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid start date");
                if(isset($request->endDate) && !ValidateDate($request->endDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid end date");

                // Logical syntax validation
                if(isset($request->studentOverrides))
                {
                    $referencedStudents = array_map(function($key){return (int)$key;}, array_keys(get_object_vars($request->studentOverrides)));
                    if(isset($request->students))
                    {
                        $irrelevantStudents = array_values(array_diff($referencedStudents, $request->students));
                        if(!empty($irrelevantStudents))
                            MessageResponse(HTTP_UNPROCESSABLE, "Some referenced student overrides are not in the student array", ['invalid_ids' => $irrelevantStudents]);
                    }
                    if(isset($request->trackables))
                    {
                        $irrelevantTrackables = [];
                        foreach($request->studentOverrides as $key => $value)
                        {
                            if(!isset($value->trackables))
                                continue;
                            $subset = array_diff(array_keys(get_object_vars($value->trackables)), $request->trackables);
                            $irrelevantTrackables = [...$irrelevantTrackables, array_values($subset)];
                        }
                        if(!empty($irrelevantTrackables))
                            MessageResponse(HTTP_UNPROCESSABLE, "Some referenced trackables are not in the trackables array.", ['invalid_strings' => $irrelevantTrackables]);
                    }
                }

                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $conn->begin_transaction() || InternalError("Failed to begin update transaction (specific lesson PATCH)");

                $id = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `LessonID`, `LocationID`, `SubjectID`, `LessonStart`, `LessonEnd`, `Notes` FROM `Lesson` WHERE `LessonID` = ? FOR UPDATE;", 'i', [$id], true,
                    "Failed to lock lesson (specific lesson PATCH)");
                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_NOT_FOUND);
                }
                $lessonRecord = $matches[0];
                if(empty(get_object_vars($request)))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_OK);
                }
                // Relationship records validation and locking
                if(isset($request->studentOverrides) && !empty(get_object_vars($request->studentOverrides)))
                {
                    $references = array_map(function($str){return (int)$str;}, array_keys(get_object_vars($request->studentOverrides)));
                    $query = "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',', $references) . ");";
                    $matches = BindedQuery($conn, $query, 'i', [ROLE_STUDENT], true,
                        "Failed to lock students (specific lesson PATCH)");
                    if(count($matches) !== count($references))
                    {
                        $diffs = array_diff($references, array_column($matches, 'UserID'));
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some student ids in overrides do not exist", ['invalid_ids' => $diffs]);
                    }

                    // If students were provided, prior validation already made sure all the students are relevant (they will also be added if not)
                    if(!isset($request->students))
                    {
                        $query = "SELECT `StudentID` FROM `Attendance` WHERE `LessonID` = ?;";
                        $matches = BindedQuery($conn, $query, 'i', [$id], true,
                            "Failed to fetch attendance (specific lesson PATCH)");
                        $linked = array_column($matches, 'StudentID');
                        $diffs = array_diff($references, $linked);
                        if(!empty($diffs))
                        {
                            $conn->rollback();
                            MessageResponse(HTTP_CONFLICT, "Some student ids are not in the lesson", ['invalid_ids' => $diffs]);
                        }
                    }
                }
                if(isset($request->students) && !empty($request->students))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',',$request->students) . ") FOR SHARE;", 'i', [ROLE_STUDENT], true,
                        "Failed to lock students (specific lesson PATCH)");
                    if(count($matches) !== count($request->students))
                    {
                        $diffs = array_diff($request->students, array_column($matches, 'UserID'));
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some student ids do not exist", ['invalid_ids' => $diffs]);
                    }
                }
                if(isset($request->locationId))
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Location` WHERE `LocationID` = ? FOR SHARE;", 'i', [$request->locationId], true,
                        "Failed to lock location (specific lesson PATCH)");
                    if(empty($matches))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Location Id does not exist");
                    }
                }
                if(isset($request->trackables) && !empty($request->trackables))
                {
                    $trackableQuery = "SELECT `TrackableName` FROM `Trackable` WHERE `TrackableName` IN (" . implode(',', array_fill(0, count($request->trackables), '?')) . ") FOR SHARE;";
                    $matches = BindedQuery($conn, $trackableQuery, str_repeat('s', count($request->trackables)), $request->trackables, true,
                        "Failed to lock trackables (specific lesson PATCH)");
                }
                if(isset($request->subjectId))
                {
                    $matches = BindedQuery($conn, "SELECT `SubjectID` FROM `Topic` WHERE `TopicID` = ? FOR SHARE;", 'i', [$request->subjectId], true,
                        "Failed to lock subject (specific lesson PATCH)");
                    if(empty($matches))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Subject Id does not exist");
                    }
                    if(isset($matches[0]['SubjectID']))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Subject ID is not a subject");
                    }
                }
                if(isset($request->topics) && !empty($request->topics))
                {
                    $subjectId = $request->subjectId ?? $lessonRecord['SubjectID'];
                    if(!isset($subjectId))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Cannot specify topics if subject ID is undefined");
                    }

                    $topicQuery = "SELECT `TopicID`, `SubjectID` FROM `Topic` WHERE `TopicID` IN (" . implode(',',$request->topics) . ") FOR SHARE;";
                    $matches = BindedQuery($conn, $topicQuery, '', [], true,
                        "Failed to lock topics (specific lesson PATCH)");
                    if(count($matches) !== count($request->topics))
                    {
                        $diffs = array_diff($request->topics, array_column($matches, 'TopicID'));
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some topic ids do not exist", ['invalid_ids' => $diffs]);
                    }
                    $subjects = array_filter($matches, function($record){return $record['SubjectID'] === null;});
                    if(!empty($subjects))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some topic ids are subjects", ['invalid_ids' => array_column($subjects, 'TopicID')]);
                    }
                    $nonrefs = array_filter($matches, function($record)use($subjectId){return $record['SubjectID'] !== $subjectId;});
                    if(!empty($nonrefs))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Some topics are not part of the subject", ['invalid_ids' => array_column($nonrefs, 'TopicID')]);
                    }
                }

                // Record-level updates
                $sets = [];
                $types = '';
                $values = [];
                if(property_exists($request, 'locationId') && $request->locationId !== $lessonRecord['LocationID'])
                {
                    if($request->locationId === null)
                        $sets[] = '`LocationID` = NULL';
                    else
                    {
                        $sets[] = '`LocationID` = ?';
                        $types .= 'i';
                        $values[] = $request->locationId;
                    }
                }
                if(property_exists($request, 'subjectId') && $request->subjectId !== $lessonRecord['SubjectID'])
                {
                    if($request->subjectId === null)
                        $sets[] = '`SubjectID` = NULL';
                    else
                    {
                        $sets[] = '`SubjectID` = ?';
                        $types .= 'i';
                        $values[] = $request->subjectId;
                    }
                }
                if(isset($request->startDate) && $request->startDate !== $lessonRecord['LessonStart'])
                {
                    $sets[] = '`LessonStart` = ?';
                    $types .= 's';
                    $values[] = $request->startDate;
                }
                if(isset($request->endDate) && $request->endDate !== $lessonRecord['LessonEnd'])
                {
                    $sets[] = '`LessonEnd` = ?';
                    $types .= 's';
                    $values[] = $request->endDate;
                }
                if(isset($request->notes) && $request->notes !== $lessonRecord['Notes'])
                {
                    $sets[] = '`Notes` = ?';
                    $types .= 's';
                    $values[] = $request->notes;
                }

                if(!empty($sets))
                {
                    BindedQuery($conn, "UPDATE `Lesson` SET " . implode(',',$sets) . " WHERE `LessonID` = ?;", $types . 'i', [...$values, $id], true,
                        "Failed to update lesson record (specific lesson PATCH)");
                }

                // Relationship changes: topics, students, trackable, student overrides
                if(isset($request->topics))
                {
                    $matches = BindedQuery($conn, "SELECT `TopicID` FROM `LessonTopic` WHERE `LessonID` = ?;", 'i', [$id], true,
                        "Failed to fetch lessontopics (specific lesson PATCH)");
                    $linked = array_column($matches, 'TopicID');
                    $createDiffs = array_diff($request->topics, $linked);
                    $deleteDiffs = array_diff($linked, $request->topics);
                    if(!empty($createDiffs))
                    {
                        $query = "INSERT INTO `LessonTopic`(`LessonID`, `TopicID`)"
                                ." SELECT ?, `TopicID` FROM `Topic` WHERE `TopicID` IN (" . implode(',',$createDiffs) . ");";
                        BindedQuery($conn, $query, 'i', [$id], true,
                            "Failed to insert lessontopics (specific lesson PATCH)");
                    }
                    if(!empty($deleteDiffs))
                    {
                        $query = "DELETE FROM `LessonTopic` WHERE `LessonID` = ? AND `TopicID` IN (" . implode(',',$deleteDiffs) . ");";
                        BindedQuery($conn, $query, 'i', [$id], true,
                            "Failed to delete lessontopics (specific lesson PATCH)");
                    }
                }

                $priorStudents = null;
                $priorTrackables = null;
                $trackables = null;
                if(isset($request->students) || isset($request->trackables))
                {
                    $priorStudents = array_column(BindedQuery($conn, "SELECT `StudentID` FROM `Attendance` WHERE `LessonID` = ?;", 'i', [$id], true,
                        "Failed to fetch attendance (specific lesson PATCH)"), 'StudentID');
                    $priorTrackables = array_column(BindedQuery($conn, "SELECT `TrackableName` FROM `LessonTrackable` WHERE `LessonID` = ?;", 'i', [$id], true,
                            "Failed to fetch lesson trackables (specific lesson PATCH)"), 'TrackableName');
                    $trackables = $request->trackables ?? $priorTrackables;
                }
                if(isset($request->students))
                {
                    $linked = $priorStudents;
                    $createDiffs = array_diff($request->students, $linked);
                    $deleteDiffs = array_diff($linked, $request->students); 

                    if(!empty($createDiffs))
                    {
                        $attendanceIDs = [];
                        foreach($createDiffs as $studentID)
                        {
                            BindedQuery($conn, "INSERT INTO `Attendance`(`LessonID`, `StudentID`, `Attended`) VALUES (?,?,0);", 'ii', [$id, $studentID], true,
                                "Failed to insert attendance (specific lesson PATCH)");
                            $attendanceIDs[] = $conn->insert_id;
                        }

                        $values = [];
                        foreach($trackables as $trackable)
                        {
                            foreach($attendanceIDs as $attendanceID)
                            {
                                $values[] = $attendanceID;
                                $values[] = $trackable;
                            }
                        }
                        if(!empty($values))
                        {
                            $query = "INSERT INTO `TrackableValue`(`AttendanceID`,`TrackableName`,`Value`) VALUES " . implode(',', array_fill(0, count($trackables) * count($attendanceIDs), '(?,?,0)')) . ";";
                            BindedQuery($conn, $query, str_repeat('is', count($trackables) * count($attendanceIDs)), $values, true,
                                "Failed to insert trackablevalues (specific lesson PATCH)");
                        }
                    }
                    if(!empty($deleteDiffs))
                    {
                        $query = "DELETE FROM `Attendance` WHERE `LessonID` = ? AND `StudentID` IN (" . implode($deleteDiffs) . ");";
                        BindedQuery($conn, $query, 'i', [$id], true,
                            "Failed to delete attendance records (specific lesson PATCH)");
                    }
                }
                if(isset($request->trackables))
                {
                    $linked = $priorTrackables;
                    $createDiffs = array_diff($request->trackables, $linked);
                    $deleteDiffs = array_diff($linked, $request->trackables);
                    if(!empty($createDiffs))
                    {
                        $query = "REPLACE INTO `TrackableValue`(`AttendanceID`, `TrackableName`, `Value`)"
                                ." SELECT `Attendance`.`AttendanceID`, `Trackable`.`TrackableName`, 0 FROM"
                                ." `Attendance` JOIN `Trackable` WHERE `Attendance`.`LessonID` = ?"
                                ." AND `Attendance`.`StudentID` IN (" . implode(',', $priorStudents) . ")"
                                ." AND `Trackable`.`TrackableName` IN (" . implode(',', array_fill(0, count($createDiffs), '?')) . ");";
                        BindedQuery($conn, $query, 'i' . str_repeat('s', count($createDiffs)), [$id, ...$createDiffs], true,
                            "Failed to insert trackablevalues (specific lesson PATCH)");
                        $query = "INSERT INTO `LessonTrackable`(`LessonID`, `TrackableName`)"
                                ." SELECT ?, `TrackableName` FROM `Trackable` WHERE `TrackableName` IN (" . implode(',',array_fill(0, count($createDiffs), '?')) . ");";
                        BindedQuery($conn, $query, 'i' . str_repeat('s', count($createDiffs)), [$id,...$createDiffs], true,
                            "Failed to insert lessontrackables (specific lesson PATCH)");
                    }
                    if(!empty($deleteDiffs))
                    {
                        $query = "DELETE `TrackableValue` FROM `TrackableValue` INNER JOIN `Attendance` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID`"
                                ." WHERE `LessonID` = ? AND `TrackableName` IN (" . implode(',',array_fill(0, count($deleteDiffs), '?')) . ");";
                        BindedQuery($conn, $query, 'i' . str_repeat('s', count($deleteDiffs)), [$id, ...$deleteDiffs], true,
                            "Failed to delete trackablevalues (specific lesson PATCH)");
                        $query = "DELETE FROM `LessonTrackable` WHERE `LessonID` = ? AND `TrackableName` IN (" . implode(',',array_fill(0, count($deleteDiffs), '?')) . ")";
                        BindedQuery($conn, $query, 'i' . str_repeat('s', count($deleteDiffs)), [$id, ...$deleteDiffs], true,
                            "Failed to delete lessontopics (specific lesson PATCH)");
                    }
                }
                if(isset($request->studentOverrides))
                {
                    $references = array_keys(get_object_vars($request->studentOverrides));
                    $query = "SELECT `StudentID`, `TrackableName`, `Attended`, `Value`" 
                            ." FROM `Attendance` LEFT JOIN `TrackableValue` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID`"
                            ." WHERE `LessonID` = ? AND `StudentID` IN (" . implode(',', $references) . ");";
                    $matches = BindedQuery($conn, $query, 'i', [$id], true,
                        "Failed to fetch attendance and trackables (specific lesson PATCH)");
                    
                    $students = [];
                    foreach($matches as $record)
                    {
                        if(!isset($students[$record['StudentID']]))
                        {
                            $obj = [];
                            $obj['StudentID'] = $record['StudentID'];
                            $obj['Attended'] = $record['Attended'] === 1;
                            $obj['Trackables'] = [];
                            $students[$record['StudentID']] = $obj;
                            if(!isset($record['TrackableName']))
                                continue;
                        }
                        $students[$record['StudentID']]['Trackables'][$record['TrackableName']] = $record['Value'] === 1;
                    }
                    foreach($request->studentOverrides as $key => $value)
                    {
                        $studentID = (int)$key;
                        if(isset($value->attended) && $value->attended !== $students[$studentID]['Attended'])
                        {
                            $query = "UPDATE `Attendance` SET `Attended` = ? WHERE `StudentID` = ? AND `LessonID` = ?;";
                            BindedQuery($conn, $query, 'iii', [$value->attended ? 1 : 0, $studentID, $id], true,
                                "Failed to update student attendance (specific lesson PATCH)");
                        }
                        if(isset($value->trackables))
                        {
                            foreach($value->trackables as $trackableName => $trackableValue)
                            {
                                if($students[$studentID]['Trackables'][$trackableName] !== $trackableValue)
                                {
                                    $query = "UPDATE `TrackableValue` INNER JOIN `Attendance` ON"
                                            ." `TrackableValue`.`AttendanceID` = `Attendance`.`AttendanceID`"
                                            ." SET `Value` = ? WHERE `StudentID` = ? AND `LessonID` = ? AND `TrackableName` = ?;";
                                    BindedQuery($conn, $query, 'iiis', [$trackableValue ? 1 : 0, $studentID, $id, $trackableName]);
                                }
                            }
                        }
                    }
                }

                $conn->commit() || InternalError("Failed to commit lesson update transaction (specific lesson PATCH)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'lesson/specific/PATCH.json'
        ]
    ]
];