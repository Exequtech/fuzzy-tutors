<?php

require_once API_ROOT . "/db/api_functions.php";
require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/authentication.php";

$endpoints['/^auth\/login$/'] = [
    'methods' => [
        'POST' => [
            'callback' => function($request, $conn, $regex): never
            {
                // Resolve user reference
                $user = null;
                if(isset($request->email))
                {
                    $matches = BindedQuery($conn, "SELECT * FROM `User` WHERE Email = ?", 's', [$request->email]);
                    if(count($matches) !== 0)
                        $user = $matches[0];
                }
                else if(isset($request->username))
                {
                    $matches = BindedQuery($conn, "SELECT * FROM `User` WHERE Username = ?", 's', [$request->username]);
                    if(count($matches) !== 0)
                        $user = $matches[0];
                }

                if(!$user || !isset($user['Password']))
                    MessageResponse(HTTP_UNAUTHORIZED, "User account or login does not exist");

                // Check credentials
                if(password_verify($request->password, $user['Password']))
                {
                    LoginSession($user);
                    MessageResponse(HTTP_OK);
                }
                else
                    MessageResponse(HTTP_UNAUTHORIZED, "Invalid credentials");
            },
            'schema-path' => 'auth/login/POST.json'
        ]
    ]
];