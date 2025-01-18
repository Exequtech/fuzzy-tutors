<?php

require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/functions/user_types.php";

$endpoints['/^location\/([\d]+)\/?$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER], false);

                $matches = BindedQuery($conn, "SELECT `LocationID`, `LocationName`, `Address`, `Description`, `RecordDate` FROM `Location` WHERE `LocationID` = ?;", 'i', [(int)$regex[1]], true,
                    "Failed to fetch location (specific location GET)");
                
                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $ret = [];
                $ret['id'] = $matches[0]['LocationID'];
                $ret['name'] = $matches[0]['LocationName'];
                $ret['address'] = $matches[0]['Address'];
                $ret['description'] = $matches[0]['Description'];
                $ret['recordDate'] = $matches[0]['RecordDate'];

                MessageResponse(HTTP_OK, null, ['result' => $ret]);
            },
            'schema-path' => 'location/specific/GET.json'
        ],
        'PATCH' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $id = (int)$regex[1];
                $matches = BindedQuery($conn, "SELECT `LocationName`, `Address`, `Description` FROM `Location` WHERE `LocationID` = ?;", 'i', [$id], true,
                    "Failed to fetch location (specific location PATCH)");
                
                if(empty($matches))
                    MessageResponse(HTTP_NOT_FOUND);

                $sets = [];
                $types = [];
                $values = [];

                if(isset($request->name) && $request->name !== $matches[0]['LocationName'])
                {
                    $sets[] = '`LocationName` = ?';
                    $types[] = 's';
                    $values[] = $request->name;
                }
                if(property_exists($request, 'address') && $request->address !== $matches[0]['Address'])
                {
                    $sets[] = '`Address`  = ?';
                    $types[] = 's';
                    $values[] = $request->address;
                }
                if(property_exists($request, 'description') && $request->description !== $matches[0]['Description'])
                {
                    $sets[] = '`Description` = ?';
                    $types[] = 's';
                    $values[] = $request->description;
                }

                if(empty($sets))
                    MessageResponse(HTTP_OK);

                $query = "UPDATE `Location` SET " . implode(',',$sets) . " WHERE `LocationID` = ?;";
                $affectedRows = BindedQuery($conn, $query, implode($types) . 'i', [...$values, $id], true,
                    "Failed to update location (specific location PATCH)");
                if(!$affectedRows)
                {
                    $matches = BindedQuery($conn, "SELECT 1 FROM `Location` WHERE `LocationID` = ?;", 'i', [$id], true,
                        "Failed to check for record existence (specific location PATCH)");
                    if(empty($matches))
                        MessageResponse(HTTP_NOT_FOUND);
                }
                
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'location/specific/PATCH.json'
        ],
        'DELETE' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                EnforceRole([ROLE_TUTOR, ROLE_OWNER]);

                $id = (int)$regex[1];
                $affectedRows = BindedQuery($conn, "DELETE FROM `Location` WHERE `LocationID` = ?;", 'i', [$id], true,
                    "Failed to delete location (specific location DELETE)");

                if(!$affectedRows)
                    MessageResponse(HTTP_NOT_FOUND);

                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'location/specific/DELETE.json'
        ]
    ]
];