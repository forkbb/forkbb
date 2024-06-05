<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use function \ForkBB\{__, size};

trait PostCFTrait
{
    /**
     * Дополняет форму настраиваемыми полями
     */
    protected function addCFtoMessageForm(array $data, int $level, array $form, array $args): array
    {
        $vars = $args['_vars']['cf_data'] ?? null;
        $new  = [];

        foreach ($form['sets']['uesm']['fields'] as $key => $cur) {
            if ('message' === $key) {
                foreach ($data as $id => $field) {
                    if ($field['visibility'] < $level) {
                        continue;
                    }

                    $new["cf_data[{$id}]"] = [
                        'class'     => ['w0'],
                        'type'      => 'text',
                        'maxlength' => $field['maxlength'],
                        'caption'   => $field['name'],
                        'required'  => (bool) $field['required'],
                        'value'     => $vars[$id] ?? $field['value'] ?? '',
                        'help'      => match ($field['visibility']) {
                            1 => 'All',
                            2 => 'Not guests',
                            3 => 'Admins and mods',
                            4 => 'Admins only',
                        }
                    ];
                }
            }

            $new[$key] = $cur;
        }

        $form['sets']['uesm']['fields'] = $new;

        return $form;
    }

    /**
     * Дополняет валидатор настраиваемыми полями
     */
    protected function addCFtoMessageValidator(array $data, int $level, Validator $v): void
    {
        foreach ($data as $id => $field) {
            if ($field['visibility'] < $level) {
                continue;
            }

            $rules  = $field['required'] ? 'required|' : '';
            $rules .= "string:trim|max:{$field['maxlength']}";
            $key    = "cf_data.{$id}";

            $v->addRules([
                $key => $rules,
            ])->addAliases([
                $key => $field['name'],
            ]);
        }
    }

    /**
     * Заполняет массив настраиваемых полей данными
     */
    protected function setCFData(array $data, int $level, array $from): array
    {
        foreach ($data as $id => &$field) {
            if ($field['visibility'] < $level) {
                continue;
            }

            $field['value'] = $from[$id];
        }

        unset($field);

        return $data;
    }

    /**
     * Вычисляет минимальный уровень доступа
     */
    protected function setCFLevel(array $data): int
    {
        $a = [];

        foreach ($data as $field) {
            $a[] = $field['visibility'];
        }

        return empty($a) ? 0 : \max(0, \min($a));
    }
}
