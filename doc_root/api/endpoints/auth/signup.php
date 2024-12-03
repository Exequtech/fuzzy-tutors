<?php
require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/functions/authentication.php";
require_once API_ROOT . "/db/validation.php";

$endpoints['/^auth\/signup$/'] = [
    'methods' => [
        'POST' => [
            'callback' => function(object|null $request, mysqli $conn, array $regex): never
            {
                // Create user
                $userType = StrToUserTypeInt($request->userType);
                $user = CreateUser($conn, $request->username, $request->email, $userType, false, $request->password);

                if(!$user)
                    MessageResponse(HTTP_INTERNAL_ERROR);
                
                LoginSession($user);
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'auth/signup/POST.json',
            'db-validate' => 'DBValidateNewUser'
        ]
    ],
];