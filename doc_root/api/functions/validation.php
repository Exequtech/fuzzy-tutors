<?php

require_once __DIR__ . "/../vendor/autoload.php";

use JsonSchema\Validator;

function Validate(object $schema, object|null $data): array|bool
{
    $validator = new Validator();
    $validator->validate($data, $schema);
    if($validator->isValid())
        return true;
    else
        return $validator->getErrors();
}