<?php
require_once "db.php";
require_once "functions.php";

function UserTypeInt($str)
{
    return match($str)
    {
        'owner' => 42,
        'student' => 13,
        'tutor' => 23,
    };
}
function InternalError($error)
{
    error_log($error);
    MessageResponse(HTTP_INTERNAL_ERROR);
    exit;
}
function MessageResponse($code, $detail = null, $msg = null)
{
    $body = [
        'status' => $code,
        'message' => $msg ?? GetStatusMessage($code)
    ];
    if(isset($detail))
        $body['detail'] = $detail;
    DetailedResponse($code, $body);
    exit;
}

function DetailedResponse($code, $body)
{
    header("Content-Type: application/json", true, $code);
    echo json_encode($body);
    global $conn;
    $conn->close();
    exit;
}

$endpoints = [
    '/^auth\/login$/' => [
        'POST' => function($request)
        {
            global $conn;
            $user = null;
            if(isset($request->email))
            {
                $stmt = $conn->prepare("SELECT * FROM `User` WHERE Email = ?;");
                $stmt->bind_param('s', $request->email);

                $success = $stmt->execute();
                if(!$success)
                    InternalError($stmt->error);

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            }
            else if(isset($request->username))
            {
                $stmt = $conn->prepare("SELECT * FROM `User` WHERE Username = ?;");
                $stmt->bind_param('s', $request->username);

                $success = $stmt->execute();
                if(!$success)
                    InternalError($stmt->error);

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            }

            if(!$user || !isset($user['Password']))
            {
                MessageResponse(HTTP_UNAUTHORIZED, "User account or login does not exist");
            }
            
            if(password_verify($request->password, $user['Password']))
            {
                session_start();
                session_regenerate_id(true);
                $_SESSION['UserID'] = $user['UserID'];
                $_SESSION['LoginIP'] = $_SERVER['REMOTE_ADDR'];
                MessageResponse(HTTP_OK);
            }
            else
            {
                MessageResponse(HTTP_UNAUTHORIZED, "Invalid credentials");
            }
        }
    ],
    '/^auth\/signup$/' => [
        'POST' => function($request){
            global $conn;
            // Uniqueness checks
            
            // Check for existing username
            $stmt = $conn->prepare("SELECT * FROM `User` WHERE Username = ?;");
            $stmt->bind_param('s', $request->username);
            if(!$stmt->execute())
                InternalError($stmt->error);
            if($stmt->get_result()->fetch_assoc() !== null)
                MessageResponse(HTTP_CONFLICT, "Username already exists.");

            // Check for existing email
            $stmt = $conn->prepare("SELECT * FROM `User` WHERE Email = ?");
            $stmt->bind_param('s', $request->email);
            if(!$stmt->execute())
                InternalError($stmt->error);
            if($stmt->get_result()->fetch_assoc() !== null)
                MessageResponse(HTTP_CONFLICT, "Email already exists.");

            // Add user if business rules weren't violated
            $stmt = $conn->prepare("INSERT INTO `User`(`Username`, `Email`, `Password`, `UserType`, `Authorized`, `RecordDate`) VALUES (?, ?, ?, ?, 0, NOW());");
            $password_hash = password_hash($request->password, PASSWORD_BCRYPT);
            $userType = UserTypeInt($request->userType);
            $stmt->bind_param('sssi', $request->username, $request->email, $password_hash, $userType);
            if(!$stmt->execute())
                InternalError($stmt->error);
            
            // Get user data for session init
            $stmt = $conn->prepare("SELECT * FROM `User` WHERE Username = ?");
            $stmt->bind_param('s', $request->username);
            if(!$stmt->execute())
                InternalError($stmt->error);
            $user = $stmt->get_result()->fetch_assoc() ?? InternalError("User disappeared during signup");
            
            session_start();
            session_regenerate_id(true);
            $_SESSION['UserID'] = $user['UserID'];
            $_SESSION['LoginIP'] = $_SERVER['REMOTE_ADDR'];
            MessageResponse(HTTP_OK);
        }
    ]
];

foreach($endpoints as $key => $value)
{
    if(!preg_match($key, $_GET['path']))
        continue;
    if(!isset($value[$_SERVER['REQUEST_METHOD']]))
        MessageResponse(HTTP_NOT_IMPLEMENTED);
    $schema = json_decode(file_get_contents( 'schema/' . $_GET['path'] . '/' . $_SERVER['REQUEST_METHOD'] . '.json') , false);
    $data = json_decode(file_get_contents('php://input'), false);

    $valid = Validate($schema, $data);
    if($valid === true)
    {
        $value[$_SERVER['REQUEST_METHOD']]($data);
    }
    else
    {
        $errors = [];
        foreach($valid as $err)
        {
            $errors[] = $err['property'] . ':' . $err['message'];
        }
        MessageResponse(HTTP_BAD_REQUEST, $errors);
    }
}

MessageResponse(HTTP_NOT_FOUND);