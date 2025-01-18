<?php

require_once API_ROOT . "/functions/session.php";

$endpoints['/^auth\/logout$/'] = [
    'methods' => [
        'DELETE' => [
            'callback' => function($request, $conn, $regex): never
            {
                DestroySession();
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'auth/logout/DELETE.json'
        ]
    ]
];
