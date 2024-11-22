<?php

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/authentication.php";

$endpoints['/^auth\/ot-token$/'] = [
    'methods' => [
        'GET' => [
            'callback' => function($request, $conn, $regex): never
            {
                $authenticated = FullAuthenticate(false);
                if(!$authenticated)
                    MessageResponse(HTTP_UNAUTHORIZED);
                $token = bin2hex(random_bytes(8));
                $_SESSION['OTToken'] = $token;
                DetailedResponse(HTTP_OK,
                [
                    'status' => HTTP_OK,
                    'message' => GetStatusMessage(HTTP_OK),
                    'token' => $token,
                ]);
            },
            'schema-path' => 'auth/ot-token/GET.json'
        ]
    ]
];