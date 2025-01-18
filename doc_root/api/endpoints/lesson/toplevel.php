<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^lesson\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                if(isset($request->before) && !ValidateDate($request->before))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid before date");
                if(isset($request->after) && !ValidateDate($request->after))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid after date");
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
                    $conditions[] = '`LessonID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->tutorId))
                {
                    $conditions[] = '`TutorID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->tutorId;
                }
                if(isset($request->subjectId))
                {
                    if($request->subjectId === 'null')
                        $conditions[] = '`SubjectID` IS NULL';
                    else
                    {
                        $conditions[] = '`SubjectID` = ?';
                        $types[] = 'i';
                        $values[] = (int)$request->subjectId;
                    }
                }
                if(isset($request->locationId))
                {
                    if($request->locationId === 'null')
                        $conditions[] = '`LocationID` IS NULL';
                    else
                    {
                        $conditions[] = '`LocationID` = ?';
                        $types[] = 'i';
                        $values[] = (int)$request->locationId;
                    }
                }
                if(isset($request->before))
                {
                    $conditions[] = '`LessonStart` < ?';
                    $types[] = 's';
                    $values[] = $request->before;
                }
                if(isset($request->after))
                {
                    $conditions[] = '`LessonStart` > ?';
                    $types[] = 's';
                    $values[] = $request->after;
                }
                if(isset($request->notes))
                {
                    $conditions[] = '`Notes` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->notes) . '%';
                }

                $order = ' ORDER BY ';
                $orderColumn = $request->orderBy ?? 'startDate';
                $orderType = $request->order ?? 'asc';
                $order .= match($orderColumn)
                {
                    'id' => '`LessonID`',
                    'tutorId' => '`TutorID`',
                    'subjectId' => '`SubjectID`',
                    'locationId' => '`LocationID`',
                    'startDate' => '`LessonStart`',
                    'endDate' => '`LessonEnd`',
                    'notes' => '`Notes`'
                };
                $order .= match($orderType)
                {
                    'asc' => ' ASC',
                    'desc' => ' DESC',
                };

                $conn->begin_transaction() || InternalError("Failed to begin read transaction (toplevel lesson GET)");
                $lessonQuery = "SELECT `LessonID`, `TutorID`, `SubjectID`, `LocationID`, `LessonStart`, `LessonEnd`, `Notes` FROM `Lesson`"
                        .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions)). $order
                        . " LIMIT $offset, $pageSize";
                $mainQuery = "SELECT `LessonID`, `Username`, `l`.`SubjectID`, `TopicName`, `l`.`LocationID`, `LocationName`, `LessonStart`, `LessonEnd`, `Notes` FROM ($lessonQuery) `l`"
                        ." LEFT JOIN `Location` ON `l`.`LocationID` = `Location`.`LocationID`"
                        ." LEFT JOIN `Topic` ON `l`.`SubjectID` = `Topic`.`TopicID`"
                        ." LEFT JOIN `User` ON `l`.`TutorID` = `UserID`;";

                $lessonRecords = BindedQuery($conn, $mainQuery, implode($types), $values, true,
                    "Failed to fetch lessons (toplevel lesson GET)");

                if(empty($lessonRecords))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_OK, null, ['results' => []]);
                }
                
                $lessons = [];
                foreach($lessonRecords as $record)
                {
                    $lessons[$record['LessonID']] = [
                        'id' => $record['LessonID'],
                        'tutorName' => $record['Username'],
                        'subjectId' => $record['SubjectID'],
                        'subjectName' => $record['TopicName'],
                        'locationId' => $record['LocationID'],
                        'locationName' => $record['LocationName'],
                        'startDate' => $record['LessonStart'],
                        'endDate' => $record['LessonEnd'],
                        'notes' => $record['Notes'],
                        'students' => [],
                        'topics' => [],
                        'trackables' => []
                    ];
                }

                $lessonIDs = array_map(function($record){return $record['LessonID'];}, $lessonRecords);
                
                $studentQuery = "SELECT `Username`, `StudentID`, `LessonID`, `Attended`, `TrackableName`, `Value` FROM `Attendance` LEFT JOIN `TrackableValue` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID` LEFT JOIN `User` ON `StudentID` = `UserID`"
                        ." WHERE `LessonID` IN (" . implode(',', $lessonIDs) . ");";
                $students = BindedQuery($conn, $studentQuery, '', [], true,
                    "Failed to fetch attendance (toplevel lesson GET)");
                foreach($students as $record)
                {
                    if(!isset($lessons[$record['LessonID']]['students'][$record['StudentID']]))
                    {
                        $obj = [];
                        $obj['name'] = $record['Username'];
                        $obj['attended'] = $record['Attended'] === 1;
                        $obj['trackables'] = [];
                        $lessons[$record['LessonID']]['students'][$record['StudentID']] = $obj;
                        if(!isset($record['TrackableName']))
                            continue;
                    }
                    $lessons[$record['LessonID']]['students'][$record['StudentID']]['trackables'][$record['TrackableName']] = $record['Value'] === 1;
                }
                foreach($lessons as $lesson)
                {
                    $lesson['students'] = array_values($lesson['students']);
                }

                $topics = BindedQuery($conn, "SELECT `LessonID`, `Topic`.`TopicID`, `TopicName` FROM `LessonTopic` INNER JOIN `Topic` ON `LessonTopic`.`TopicID` = `Topic`.`TopicID` WHERE `LessonID` IN (" . implode(',', $lessonIDs) . ");", '', [], true,
                    "Failed to fetch topics (toplevel lesson GET)");
                foreach($topics as $record)
                    $lessons[$record['LessonID']]['topics'][] = ['name' => $record['TopicName'], 'id' => $record['TopicID']];

                $trackables = BindedQuery($conn, "SELECT `LessonID`, `TrackableName` FROM `LessonTrackable` WHERE `LessonID` IN (" . implode(',',$lessonIDs) . ");", '', [], true,
                    "Failed to fetch trackables (toplevel lesson GET)");
                foreach($trackables as $record)
                    $lessons[$record['LessonID']]['trackables'][] = $record['TrackableName'];

                $conn->commit();
                MessageResponse(HTTP_OK, null, ['results' => array_values($lessons)]);
            },
            'schema-path' => 'lesson/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                if(!ValidateDate($request->startDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid start date");
                if(!ValidateDate($request->endDate))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid end date");

                $conn->begin_transaction() || InternalError("Failed to begin lesson creation transaction (toplevel lesson POST)");

                // Lesson insert variables
                $properties = [];
                $types = [];
                $values = [];

                $students = [];
                if(isset($request->classId))
                {
                    $matches = BindedQuery($conn, "SELECT `StudentID` FROM `Class` LEFT JOIN `StudentClass` ON `Class`.`ClassID` = `StudentClass`.`ClassID` WHERE `Class`.`ClassID` = ?;", 'i', [$request->classId], true,
                        "Failed to fetch class student data (toplevel lesson POST)");
                    if(empty($matches))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Class ID does not exist");
                    }
                    if(count($matches) !== 1)
                    {
                        foreach($matches as $record)
                        {
                            $students[] = $record['StudentID'];
                        }
                    }
                    // Acquire lock to ensure that students do not disappear during further transaction
                    if(!empty($students))
                    {
                        $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserID` IN (" . implode(',',$students) . ') FOR SHARE;', '', [], true,
                            "Failed to lock students (toplevel lesson POST)");
                        // Handle gracefully, even if some students have disappeared (such state difference is acceptable)
                        $students = array_map(function($record){return $record['UserID'];}, $matches);
                    }
                }
                elseif(!empty($request->students))
                {
                    $matches = BindedQuery($conn, "SELECT `UserID` FROM `User` WHERE `UserType` = ? AND `UserID` IN (" . implode(',',$request->students) . ') FOR SHARE;', 'i', [ROLE_STUDENT], true,
                        "Failed to lock students (toplevel lesson POST)");
                    $exists = array_map(function($record){return $record['UserID'];}, $matches);
                    if(count($exists) !== count($request->students))
                        MessageResponse(HTTP_CONFLICT, "Some student ids do not exist", ['invalid_ids' => array_diff($request->students, $exists)]);
                    $students = $request->students;
                }
                if(isset($request->locationId))
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Location` WHERE `LocationID` = ? FOR SHARE;", 'i', [$request->locationId], true,
                        "Failed to check for location existence (toplevel lesson POST)");
                    if(empty($matches))
                    {
                        $conn->rollback();
                        MessageResponse(HTTP_CONFLICT, "Location ID does not exist");
                    }
                    $properties[] = '`LocationID`';
                    $types[] = 'i';
                    $values[] = $request->locationId;
                }
                if(!empty($request->trackables))
                {
                    $query = "SELECT `TrackableName` FROM `Trackable` WHERE `TrackableName` IN (?" . str_repeat(',?', count($request->trackables) - 1) . ') FOR SHARE;';
                    $matches = BindedQuery($conn, $query, 's', $request->trackables, true,
                        "Failed to lock trackables (toplevel lesson POST)");
                    if(count($matches) !== count($matches))
                    {
                        $conn->rollback();
                        $exists = array_map(function($record){return $record['TrackableName'];}, $matches);
                        MessageResponse(HTTP_CONFLICT, "Some trackables do not exist", ['invalid_strings' => array_diff($request->trackables, $exists)]);
                    }
                }
                
                $matches = BindedQuery($conn, "SELECT `SubjectID` FROM `Topic` WHERE `TopicID` = ? FOR SHARE;", 'i', [$request->subjectId], true,
                    "Failed to lock subject");
                if(empty($matches))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_CONFLICT, "Subject ID does not exist");
                }
                if(isset($matches[0]['SubjectID']))
                {
                    $conn->rollback();
                    MessageResponse(HTTP_CONFLICT, "Subject ID is not a subject");
                }
                if(!empty($request->topics))
                {
                    $query = "SELECT `TopicID`, `SubjectID` FROM `Topic` WHERE `TopicID` IN (" . implode(',',$request->topics) . ") FOR SHARE;";
                    $matches = BindedQuery($conn, $query, '', [], true,
                        "Failed to lock topics (toplevel lesson POST)");
                    if(count($matches) !== count($request->topics))
                    {
                        $conn->rollback();
                        $exists = array_map(function($record){return $record['TopicID'];}, $matches);
                        MessageResponse(HTTP_CONFLICT, "Some topics do not exist", ['invalid_ids' => array_diff($request->topics, $exists)]);
                    }
                    $subjects = array_filter($matches, function($topic){return $topic['SubjectID'] === null;});
                    if(!empty($subjects))
                    {
                        $conn->rollback();
                        $subjects = array_map(function($record){return $record['TopicID'];}, $subjects);
                        MessageResponse(HTTP_CONFLICT, "Some provided topics are subjects", ['invalid_ids' => $subjects]);
                    }
                    $irrelevant = array_filter($matches, function($record)use($request){return $record['SubjectID'] !== $request->subjectId;});
                    if(!empty($irrelevant))
                    {
                        $conn->rollback();
                        $irrelevant = array_map(function($record){return $record['TopicID'];}, $matches);
                        MessageResponse(HTTP_CONFLICT, "Some provided topics are not in the given subject", ['invalid_ids' => $irrelevant]);
                    }
                }
                if(isset($request->notes))
                {
                    $properties[] = '`Notes`';
                    $types[] = 's';
                    $values[] = $request->notes;
                }

                // Insert main lesson record
                $lessonInsert = "INSERT INTO `Lesson`(`TutorID`, `SubjectID`, `LessonStart`, `LessonEnd`" .(empty($properties) ? '' : ', ' . implode(', ', $properties)) . ') '
                        ."VALUES (?,?,?,?" . str_repeat(',?', count($properties)) . ");";
                BindedQuery($conn, $lessonInsert, 'iiss' . implode($types), [GetUser()['UserID'], $request->subjectId, $request->startDate, $request->endDate, ...$values], true,
                    "Failed to insert lesson record (toplevel lesson POST)");

                $lessonID = $conn->insert_id;

                // Bridge table records
                if(!empty($request->topics))
                {
                    $values = [];
                    foreach($request->topics as $topic)
                    {
                        $values[] = $topic;
                        $values[] = $lessonID;
                    }
                    $query = "INSERT INTO `LessonTopic`(`TopicID`, `LessonID`) VALUES " . implode(',', array_fill(0, count($request->topics),'(?,?)')) . ";";
                    BindedQuery($conn, $query, str_repeat('i', 2 * count($request->topics)), $values, true,
                        "Failed to attach topics to lesson (toplevel lesson POST)");
                }
                $attendanceIDs = [];
                foreach($students as $student)
                {
                    BindedQuery($conn, "INSERT INTO `Attendance`(`StudentID`, `LessonID`, `Attended`) VALUES (?, ?, 0);", 'ii', [$student, $lessonID], true,
                        "Failed to insert attendance records (toplevel lesson POST)");
                    $attendanceIDs[] = $conn->insert_id;
                }
                if(!empty($request->trackables))
                {
                    $values = [];
                    foreach($request->trackables as $trackable)
                    {
                        $values[] = $trackable;
                        $values[] = $lessonID;
                    }
                    $query = "INSERT INTO `LessonTrackable`(`TrackableName`, `LessonID`) VALUES " . implode(',', array_fill(0, count($request->trackables), '(?,?)')) . ';';
                    BindedQuery($conn, $query, str_repeat('si', count($request->trackables)), $values, true,
                        "Failed to attach trackables to lesson (toplevel lesson POST)");

                    if(!empty($attendanceIDs))
                    {
                        $values = [];
                        foreach($request->trackables as $trackable)
                        {
                            foreach($attendanceIDs as $attendanceID)
                            {
                                $values[] = $trackable;
                                $values[] = $attendanceID;
                            }
                        }
                        $query = "INSERT INTO `TrackableValue`(`TrackableName`, `AttendanceID`, `Value`) VALUES " . implode(',', array_fill(0, count($request->trackables) * count($attendanceIDs), '(?,?,0)'));
                        BindedQuery($conn, $query, str_repeat('si', count($request->trackables) * count($attendanceIDs)), $values, true,
                            "Failed to fill trackable values (toplevel lesson POST)");
                    }
                }
                $conn->commit() || InternalError("Failed to commit lesson creation transaction (toplevel lesson POST)");
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'lesson/toplevel/POST.json'
        ]
    ]
];