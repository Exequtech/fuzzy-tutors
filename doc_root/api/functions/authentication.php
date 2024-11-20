<?php
require_once __DIR__ . "/session.php";

function LoginSession($user): void
{
    session_start();
    $init = InitializeSession();
    if($init)
        session_regenerate_id(true);
    $_SESSION['UserID'] = $user['UserID'];
    $_SESSION['LoginIP'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['LoggedIn'] = true;
}