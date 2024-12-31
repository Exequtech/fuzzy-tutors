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

				if(isset($request->students) && empty($request->students))
					MessageResponse(HTTP_OK, null, ['results' => []]);

				$conn->begin_transaction() || InternalError("Failed to start read transaction (stats trackable GET)");

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
					$conditions[] = '(`StartDate` >= ? OR `EndDate` >= ?)';
					$values[] = $request->startDate;
					$values[] = $request->startDate;
					$types .= 'ss';
				}
				if(isset($request->endDate)) {
					$conditions[] = '(`StartDate` <= ? OR `EndDate` <= ?)';
					$values[] = $request->endDate;
					$values[] = $request->endDate;
					$types .= 'ss';
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
				$query = "SELECT `StudentID`, `TrackableName`, SUM(`TrackableValue`.`Value`) `Truths`, COUNT(*) `Total` FROM `TrackableValue` INNER JOIN `Attendance` ON `Attendance`.`AttendanceID` = `TrackableValue`.`AttendanceID` WHERE `LessonID` IN (" . implode(',', $lessonIDs) . ") AND `StudentID` IN (" . implode(',', $students) . ") GROUP BY `StudentID`, `TrackableName`;";
				$matches = BindedQuery($conn, $query, '', [], true,
			   		"Failed to fetch trackable records (stats trackable GET)");
				
				$trackables = [];
				foreach($matches as $record) {
					$studentName = $names[$record['StudentID']];
					if(!isset($trackables[$studentName]))
						$trackables[$studentName] = [];
					$trackables[$studentName][$record['TrackableName']] = [
						'truths' => $record['Truths'],
						'total' => $record['Total']
					];
				}
				
				$conn->commit();
				MessageResponse(HTTP_OK, null, ['results' => $trackables]);
			},
			'schema-path' => 'trackable/stats/GET.json'
		]
	]
];


