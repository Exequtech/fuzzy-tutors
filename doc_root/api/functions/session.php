<?php

function LoginSession($user)
{
    session_start();
    session_regenerate_id(true);
    $_SESSION['UserID'] = $user['UserID'];
    $_SESSION['LoginIP'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['LoggedIn'] = true;
}