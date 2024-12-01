<?php
require_once API_ROOT . "/db/api_functions.php";

require_once API_ROOT . "/functions/api_responses.php";
require_once API_ROOT . "/functions/response_codes.php";
require_once API_ROOT . "/functions/user_types.php";
require_once API_ROOT . "/functions/authentication.php";

$endpoints['/^auth\/signup$/'] = [
    'methods' => [
        'POST' => [
            'callback' => function($request, $conn, $regex)
            {
                // Uniqueness checks
                
                // Check for existing username
                if(count(BindedQuery($conn, "SELECT * FROM `User` WHERE `Username` = ?;", 's', [$request->username])) != 0)
                    MessageResponse(HTTP_CONFLICT, "Username already exists.");

                // Check for existing email
                if(count(BindedQuery($conn, "SELECT * FROM `User` WHERE `Email` = ?;", 's', [$request->email])) != 0)
                    MessageResponse(HTTP_CONFLICT, "Email already exists.");

                // Add user if business rules weren't violated
                $password_hash = password_hash($request->password, PASSWORD_BCRYPT);
                $userType = StrToUserTypeInt($request->userType);
                BindedQuery($conn, "INSERT INTO `User`(`Username`, `Email`, `Password`, `UserType`, `Authorized`, `RecordDate`) VALUES (?, ?, ?, ?, 0, NOW());", 'sssi',
                [$request->username, $request->email, $password_hash, $userType]);
                
                // Get user data for session init
                $matches = BindedQuery($conn, "SELECT * FROM `User` WHERE `Username` = ?;", 's', [$request->username]);
                if(count($matches) == 0)
                    InternalError("User disappeared during signup");
                
                LoginSession($matches[0]);
                MessageResponse(HTTP_OK);
            },
            'schema-path' => 'auth/signup/POST.json'
        ]
    ],
];