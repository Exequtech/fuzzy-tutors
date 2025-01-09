<?php

require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^student\/([\d]+)\/trackables\/([^\/]+)\/?$/'] = [
	'methods' => [
		'GET' => [
			'callback' => function(object $request, mysqli $conn, array $regex): never
			{
                if(!ValidateDate($request->startDate)) {
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid startDate");
                }
                if(!ValidateDate($request->endDate)) {
                    MessageResponse(HTTP_BAD_REQUEST, "Invalid endDate");
                }
				EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

				$id = intval($regex[1]);
				$trackable = $regex[2];
				$query = "SELECT `Lesson`.`LessonID`, `LessonStart`, `LessonEnd`, `TopicName`, `LocationName`, `Value` "
					."FROM `Attendance` INNER JOIN `TrackableValue` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID` "
					."LEFT JOIN `Lesson` ON `Lesson`.`LessonID` = `Attendance`.`LessonID` "
					."LEFT JOIN `Location` ON `Lesson`.`LocationID` = `Location`.`LocationID` "
					."LEFT JOIN `Topic` ON `Lesson`.`SubjectID` = `Topic`.`TopicID` "
					."WHERE `StudentID` = ? AND `TrackableName` = ? AND (`LessonStart` BETWEEN ? AND ? OR `LessonEnd` BETWEEN ? AND ?);";
				$matches = BindedQuery($conn, $query, 'isssss', [$id, $trackable, $request->startDate, $request->endDate, $request->startDate, $request->endDate], true,
					"Failed to fetch student trackables (trackables student GET)");

				if(empty($matches))
					MessageResponse(HTTP_NOT_FOUND);
				
				if(!isset($matches[0]['LessonID']))
					MessageResponse(HTTP_OK, null, ['results' => []]);
				$results = [];
				foreach($matches as $record)
				{
					$results[] = [
						'lessonId' => $record['LessonID'],
						'startDate' => $record['LessonStart'],
						'endDate' => $record['LessonEnd'],
						'subjectName' => $record['TopicName'],
						'locationName' => $record['LocationName'],
						'value' => !!$record['Value'],
					];
				}

				MessageResponse(HTTP_OK, null, ['results' => $results]);
			},
			'schema-path' => 'student/trackables/GET.json'
		]
	]
];
