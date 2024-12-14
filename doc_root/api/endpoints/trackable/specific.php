<?php

require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^trackable\/([^\/]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $matches = BindedQuery($conn, "SELECT `TrackableName`, `RecordDate` FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$regex[1]], true,
                    "Failed to fetch trackable (specific trackable GET)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $ret = [];
                $ret['name'] = $matches[0]['TrackableName'];
                $ret['recordDate'] = $matches[0]['RecordDate'];

                MessageResponse(HTTP_OK, null, ['result' => $ret]);
            },
            'schema-path' => 'trackable/specific/GET.json'
        ],
        'DELETE' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $affectedRows = BindedQuery($conn, "DELETE FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$regex[1]], true,
                    "Failed to delete trackable (specific trackable DELETE)");

                if(!$affectedRows)
                    MessageResponse(HTTP_NOT_FOUND);

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'trackable/specific/DELETE.json'
        ],
        'PATCH' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $trackableName = $regex[1];

                $matches = BindedQuery($conn, "SELECT `TrackableName`, `Description` FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$trackableName], true,
                    "Failed to fetch trackable (specific trackable PATCH)");

                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                if(empty(get_object_vars($request)))
                    MessageResponse(HTTP_OK);

                $sets = [];
                $types = [];
                $values = [];
                if(isset($request->name) && $request->name !== $trackableName)
                {
                    $sets[] = '`TrackableName` = ?';
                    $types[] = 's';
                    $values[] = $request->name;
                }
                if(property_exists($request, 'description') && $request->description !== $matches['Description'])
                {
                    $sets[] = '`Description` = ?';
                    $types[] = 's';
                    $values[] = $request->description;
                }
                if(empty($sets))
                    MessageResponse(HTTP_OK);

                $query = "UPDATE `Trackable` SET " . implode(',',$sets) . " WHERE `TrackableName` = ?;";
                $rowsAffected = BindedQuery($conn, $query, implode($types) . 's', [...$values, $trackableName], true,
                    "Failed to update trackable name (specific trackable PATCH)");
                
                if(!$rowsAffected)
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$request->name ?? $trackableName], true,
                        "Failed to check for trackable existence (specific trackable PATCH)");
                    if(empty($matches))
                        MessageResponse(HTTP_NOT_FOUND);
                }

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'trackable/specific/PATCH.json'
        ]
    ]
];