<?php

const
    ROLE_OWNER = 42,
    ROLE_STUDENT = 13,
    ROLE_TUTOR = 23;

function StrToUserTypeInt(string $str): int
{
    return match($str)
    {
        'owner' => ROLE_OWNER,
        'student' => ROLE_STUDENT,
        'tutor' => ROLE_TUTOR,
    };
}
function UserTypeIntToStr(int $code) : string
{
    return match($code)
    {
        ROLE_OWNER => 'owner',
        ROLE_STUDENT => 'student',
        ROLE_TUTOR => 'tutor',
    };
}