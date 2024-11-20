<?php

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