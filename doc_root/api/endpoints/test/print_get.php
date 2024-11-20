<?php

require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/api_responses.php";

$endpoints['/^test\/print-get$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function($request, $conn, $regex)
            {
                $response = [
                    'status' => HTTP_OK,
                    'message' => GetStatusMessage(HTTP_OK),
                    'return' => []
                ];
                foreach($request as $key => $value)
                {
                    $response['return'][$key] = $value;
                }
                DetailedResponse(HTTP_OK, $response);
            },
            'schema-path' => 'test/print_get.json',
        ]
    ]
];