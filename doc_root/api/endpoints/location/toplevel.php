<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/db/escaping.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^location\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
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
                    $conditions[] = '`LocationID` = ?';
                    $types[] = 'i';
                    $values[] = (int)$request->id;
                }
                if(isset($request->name))
                {
                    $conditions[] = '`LocationName` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->name) . '%';
                }
                if(isset($request->address))
                {
                    $conditions[] = '`Address` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->address) . '%';
                }
                if(isset($request->description))
                {
                    $conditions[] = '`Description` LIKE ?';
                    $types[] = 's';
                    $values[] = '%' . EscapeWildChars($request->description) . '%';
                }

                $order = '';
                if(isset($request->orderBy))
                {
                    $order = ' ORDER BY ' . match($request->orderBy)
                    {
                        'id' => '`LocationID`',
                        'name' => '`LocationName`',
                        'address' => '`Address`',
                        'Description' => '`Description`',
                    };
                    if(isset($request->order))
                        $order .= match($request->order)
                        {
                            'asc' => ' ASC',
                            'desc' => ' DESC',
                        };
                }

                $query = 'SELECT `LocationID`, `LocationName`, `Address`, `Description`, `RecordDate` FROM `Location`'
                    .(empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions))
                    . $order . " LIMIT $offset, $pageSize";
                $matches = BindedQuery($conn, $query, implode($types), $values, true,
                    "Failed to fetch locations (toplevel location GET)");
                
                MessageResponse(HTTP_OK, null, ['results' => array_map(function($location){
                    $ret = [];
                    $ret['id'] = $location['LocationID'];
                    $ret['name'] = $location['LocationName'];
                    $ret['address'] = $location['Address'];
                    $ret['description'] = $location['Description'];
                    $ret['recordDate'] = $location['RecordDate'];
                    return $ret;
                }, $matches)]);
            },
            'schema-path' => 'location/toplevel/GET.json'
        ],
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $types = ['s'];
                $values = [$request->name];
                // Collect optional properties provided
                $properties = [];
                if(isset($request->address))
                {
                    $properties[] = '`Address`';
                    $types[] = 's';
                    $values[] = $request->address;
                }
                if(isset($request->description))
                {
                    $properties[] = '`Description`';
                    $types[] = 's';
                    $values[] = $request->description;
                }

                $query = "INSERT INTO `Location`(`LocationName`" .(empty($properties) ? '' : ',' . implode(',',$properties)) . ')'
                    . ' VALUES (?' . str_repeat(',?', count($properties)) . ');';
                BindedQuery($conn, $query, implode($types), $values, true,
                    "Failed to insert location record (toplevel location POST)");

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'location/toplevel/POST.json'
        ]
    ]
];