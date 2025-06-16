<?php

namespace Validators;

use Valitron\Validator;

class UrlValidator
{
    public static function validate(array $data): array
    {
        $name = trim($data['url']['name'] ?? '');

        $validator = new Validator(['name' => $name]);
        $validator->labels(['name' => 'URL']);

        $validator->rule('required', 'name')->message('URL не должен быть пустым');
        $validator->rule('url', 'name')->message('Некорректный URL');
        $validator->rule('lengthMax', 'name', 255)->message('URL не должен превышать 255 символов');

        if (!$validator->validate()) {
            $errors = $validator->errors();

            if (isset($errors['name']) && in_array('URL не должен быть пустым', $errors['name'], true)) {
                return ['name' => ['URL не должен быть пустым']];
            }

            return $errors;
        }

        return [];
    }
}
