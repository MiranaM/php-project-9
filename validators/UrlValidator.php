<?php

namespace Validators;

use Valitron\Validator;

class UrlValidator
{
    public static function validate(array $data): array
    {
        $validator = new Validator($data);
        $validator->labels(['name' => 'URL-адрес']);
        $validator->rule('required', 'name');
        $validator->rule('url', 'name');
        $validator->rule('lengthMax', 'name', 255);

        if (!$validator->validate()) {
            return $validator->errors();
        }

        return [];
    }
}
