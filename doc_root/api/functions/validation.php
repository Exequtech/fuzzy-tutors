<?php

require_once __DIR__ . "/../vendor/autoload.php";

use JsonSchema\Validator;

function Validate(object $schema, mixed $data): array|bool
{
    $validator = new Validator();
    $validator->validate($data, $schema);
    if($validator->isValid())
        return true;
    else
        return $validator->getErrors();
}

function ValidateDate(string $date, string $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}