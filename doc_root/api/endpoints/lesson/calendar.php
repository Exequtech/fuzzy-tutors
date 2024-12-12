<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/functions/validation.php";

$endpoints['/^calendar\/lesson\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                if(!ValidateDate($request->before))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid before date");
                if(!ValidateDate($request->after))
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid after date");

                $order = ' ORDER BY ';
                $orderColumn = $request->orderBy ?? 'startDate';
                $orderType = $request->order ?? 'asc';
                $order .= match($orderColumn)
                {
                    'id' => '`LessonID`',
                    'tutorName' => '`Username`',
                    'subjectName' => '`TopicName`',
                    'locationName' => '`LocationName`',
                    'startDate' => '`LessonStart`',
                    'endDate' => '`LessonEnd`',
                    'notes' => '`Notes`'
                };
                if($orderColumn !== 'id')
                    $order .= ", `LessonID`";
                $order .= match($orderType)
                {
                    'asc' => ' ASC;',
                    'desc' => ' DESC;'
                };

                $query = "SELECT `Lesson`.`LessonID`, `Username`, `TopicName`, `LocationName`, `LessonStart`, `LessonEnd`, `Lesson`.`Notes`, `StudentID` "
                        ."FROM `Lesson` LEFT JOIN `Location` ON `Lesson`.`LocationID` = `Location`.`LocationID` "
                        ."LEFT JOIN `User` ON `UserID` = `TutorID` "
                        ."LEFT JOIN `Topic` ON `Lesson`.`SubjectID` = `TopicID` "
                        ."LEFT JOIN `Attendance` ON `Lesson`.`LessonID` = `Attendance`.`LessonID` "
                        ."WHERE `LessonStart` BETWEEN ? AND ?" . $order;

                //MessageResponse(HTTP_OK, "Test", ['results' => $query]);
                $results = BindedQuery($conn, $query, 'ss', [$request->after, $request->before], true,
                    "Failed to fetch lesson data (lesson calendar GET)");

                $lessons = [];
                foreach($results as $record)
                {
                    $id = $record['LessonID'];
                    if(!isset($lessons[$id]))
                    {
                        $obj = [];
                        $obj['id'] = $id;
                        $obj['tutorName'] = $record['Username'];
                        $obj['subjectName'] = $record['TopicName'];
                        $obj['locationName'] = $record['LocationName'];
                        $obj['startDate'] = $record['LessonStart'];
                        $obj['endDate'] = $record['LessonEnd'];
                        $obj['notes'] = $record['Notes'];
                        $obj['students'] = [];
                        if(isset($record['StudentID']))
                            $obj['students'][] = $record['StudentID'];
                        $lessons[$id] = $obj;
                    }
                    else
                        $lessons[$id]['students'][] = $record['StudentID'];
                }

                MessageResponse(HTTP_OK, null, ['results' => array_values($lessons)]);
            },
            'schema-path' => 'lesson/calendar/GET.json'
        ]
    ]
];