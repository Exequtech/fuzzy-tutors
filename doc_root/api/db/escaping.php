<?php

function EscapeWildChars(string $str): string
{
    return str_replace(['%', '_'], ['\%', '\_'], $str);
}