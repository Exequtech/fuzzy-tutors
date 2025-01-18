<?php

if(!defined('STARTED_SESSION'))
    session_start();
const STARTED_SESSION = true;

function SessionLog(string $msg, string $sess_id = null): void
{
    if($sess_id == null)
        $sess_id = session_id();
    error_log("SESSION " . $sess_id . " :\n$msg");
}

// Return whether the session was already used prior
function InitializeSession(): bool
{
    $init = isset($_SESSION['Initiated']);
    $_SESSION['Initiated'] = true;
    $sess_id = session_id();
    if(!$init)
    {
        $_SESSION['InitTime'] = time();
        $_SESSION['Salt'] = bin2hex(random_bytes(3));
        $_SESSION['DeviceHash'] = hash('sha256', $_SESSION['Salt'] . $_SERVER['HTTP_USER_AGENT']);
    }
    else if(!isset($_SESSION['Salt']) || !isset($_SESSION['DeviceHash']))
    {
        SessionLog("Found an initialized session without proper init information.", $sess_id);
        unset($_SESSION['Initiated']);
        InitializeSession();
        return true;
    }
    return $init;
}

// This removes all session relevant data, and removes the session cookie
function DestroySession()
{
    UnsetSessCookie();
    session_unset();
    session_destroy();
}
function UnsetSessCookie()
{
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
