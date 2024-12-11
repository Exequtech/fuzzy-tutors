<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^trackable\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $condition = '';
                $order = '';
                $types = [];
                $values = [];
                if(isset($request->name))
                {
                    $condition = ' WHERE `TrackableName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }
                if(isset($request->order))
                {
                    $order = ' ORDER BY `TrackableName` ' . match($request->order)
                    {
                        'asc' => 'ASC',
                        'desc' => 'DESC'
                    }; 
                }

                $query = 'SELECT `TrackableName`, `RecordDate` FROM `Trackable`' . $condition . $order;
                $matches = BindedQuery($conn, $query, implode($types), $values, true,
                    "Failed to fetch trackables (toplevel trackable GET)");

                MessageResponse(HTTP_OK, null, ['results' => array_map(function($record)
                {
                    $ret = [];
                    $ret['name'] = $record['TrackableName'];
                    $ret['recordDate'] = $record['RecordDate'];
                    return $ret;
                }, $matches)]);
            },
            'schema-path' => 'trackable/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $matches = BindedQuery($conn, "SELECT 1 FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$request->name], true,
                    "Failed to check for trackable existence (toplevel trackable POST");
                if(!empty($matches))
                    MessageResponse(HTTP_CONFLICT, "Name already exists.");

                BindedQuery($conn, "INSERT INTO `Trackable`(`TrackableName`) VALUES (?)", 's', [$request->name], true,
                    "Failed to create trackable (toplevel trackable POST");

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'trackable/toplevel/POST.json'
        ]
    ]
];