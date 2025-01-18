<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^trackable\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $conditions = [];
                $order = '';
                $types = [];
                $values = [];
                if(isset($request->name))
                {
                    $conditions[] = '`TrackableName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }
                if(isset($request->description))
                {
                    if($request->description === "/null")
                        $conditions[] = '`Description` IS NULL';
                    else
                    {
                        $conditions[] = '`Description` LIKE ?';
                        $types[] = 's';
                        $values[] = '%' . EscapeWildChars($request->description) . '%';
                    }
                }
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'description' => '`Description`',
                        'name' => '`TrackableName`',
                    };
                    if(isset($request->order))
                        $order .= match($request->order)
                        {
                            'asc' => ' ASC',
                            'desc' => ' DESC'
                        };
                }

                $query = 'SELECT `TrackableName`, `Description`, `RecordDate` FROM `Trackable`'
                        .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions)) . $order . ';';
                $matches = BindedQuery($conn, $query, implode($types), $values, true,
                    "Failed to fetch trackables (toplevel trackable GET)");

                MessageResponse(HTTP_OK, null, ['results' => array_map(function($record)
                {
                    $ret = [];
                    $ret['name'] = $record['TrackableName'];
                    $ret['recordDate'] = $record['RecordDate'];
                    $ret['description'] = $record['Description'];
                    return $ret;
                }, $matches)]);
            },
            'schema-path' => 'trackable/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $matches = BindedQuery($conn, "SELECT 1 FROM `Trackable` WHERE `TrackableName` = ?;", 's', [$request->name], true,
                    "Failed to check for trackable existence (toplevel trackable POST)");
                if(!empty($matches))
                    MessageResponse(HTTP_CONFLICT, "Name already exists.");

                if(isset($request->description))
                    BindedQuery($conn, "INSERT INTO `Trackable`(`TrackableName`, `Description`) VALUES (?, ?)", 'ss', [$request->name, $request->description], true,
                        "Failed to create trackable (toplevel trackable POST)");
                else
                    BindedQuery($conn, "INSERT INTO `Trackable`(`TrackableName`) VALUES (?)", 's', [$request->name], true,
                        "Failed to create trackable (toplevel trackable POST");

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'trackable/toplevel/POST.json'
        ]
    ]
];