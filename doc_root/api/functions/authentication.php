<?php
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/../db/init.php";
require_once __DIR__ . "/../db/api_functions.php";

function LoginSession($user): void
{
    $init = InitializeSession();
    if($init)
        session_regenerate_id(true);
    $_SESSION['UserID'] = $user['UserID'];
    $_SESSION['LoginIP'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['LoggedIn'] = true;
}

// Simply returns whether the necessary fields for login exist in the current session
function HaveSessionLoginFields()
{
    $init = InitializeSession();
    if(!$init)
        return false;
    if(!(isset($_SESSION['LoggedIn']) && $_SESSION['LoggedIn']) || !isset($_SESSION['UserID'], $_SESSION['LoginIP']))
        return false;
    return true;
}

// Validate session and other header-related data
function SessionAuthenticate(): bool
{
    // Incomplete / uninitiated session
    if(!HaveSessionLoginFields())
        return false;

    // User agent is small extra security layer
    if(!isset($_SERVER['HTTP_USER_AGENT']))
        return false;

    // If the 2 hashes don't match, it means the same session is likely being used in a different browser.
    // A violation of this rule logs out the user and destroys relevant session information.
    if(!hash_equals($_SESSION['DeviceHash'], hash('sha256', $_SESSION['Salt'] . $_SERVER['HTTP_USER_AGENT']))) 
    {
        SessionLog("Attempted authentication with different user agent. Ending session.");
        DestroySession();
        return false;
    }

    return true;
}

// Check if a valid token is present
function CheckOTToken(): bool
{
    // Check for no token available
    if(!isset($_SESSION['OTToken']))
        return false;

    // Check for wrong/no token provided
    $headers = apache_request_headers();
    if(!isset($headers['X-OT-Token']) || !hash_equals($_SESSION['OTToken'], $headers['X-OT-Token']))
        return false;

    return true;
}

// Caching full-authentication results. DO NOT TOUCH VARIABLES OUTSIDE THIS CODE.
// authenticated, user
$AuthData = [null, null];

// This function uses external state (above) to allow multiple calls without prompting multiple DB connections.
// The following functions govern identification for API functionality
function FullAuthenticate(bool $requireOT = true): bool
{
    // Check cache for calculated result
    global $AuthData;
    if($AuthData[0] !== null)
        return $AuthData[0];

    // Double check session and headers. It should be certain after this point that a properly logged in ID was sent, and nothing more.
    if(!SessionAuthenticate())
    {
        $AuthData[0] = false;
        return false;
    }

    // Double check for token
    if($requireOT && !CheckOTToken())
    {
        $AuthData[0] = false;
        return false;
    }

    global $conn;
    $get = BindedQuery($conn, "SELECT * FROM `User` WHERE UserID = ?;", 'i', [$_SESSION['UserID']]);
    // Check for non-existent user
    if(count($get) === 0)
    {
        SessionLog("Session issued to non-existent user. Ending session.");
        DestroySession();
        $AuthData[0] = false;
        return false;
    }

    // Check for unauthorized user
    if(!$get[0]['Authorized'])
    {
        $AuthData[0] = false;
        return false;
    }

    $AuthData[1] = $get[0];
    $AuthData[0] = true;
    return true;
}

function GetUser(): array|null
{
    global $AuthData;

    // Check for cache presence
    if(!isset($AuthData[1], $AuthData[0]))
        FullAuthenticate();

    if(!$AuthData[0])
        return null;

    return $AuthData[1];
}