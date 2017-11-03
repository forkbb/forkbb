<?php

namespace ForkBB\Core;

use ForkBB\Core\Container;
use RuntimeException;

class Validator
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Массив валидаторов
     * @var array
     */
    protected $validators = [];

    /**
     * Массив правил для текущей проверки данных
     * @var array
     */
    protected $rules = [];

    /**
     * Массив результатов проверенных данных
     * @var array
     */
    protected $result = [];

    /**
     * Массив дополнительных аргументов для валидаторов и конкретных полей/правил
     * @var array
     */
    protected $arguments = [];

    /**
     * Массив сообщений об ошибках для конкретных полей/правил
     * @var array
     */
    protected $messages = [];

    /**
     * Массив псевдонимов имен полей для вывода в ошибках
     * @var array
     */
    protected $aliases = [];

    /**
     * Массив ошибок валидации
     * @var array
     */
    protected $errors = [];

    /**
     * Массив имен полей для обработки
     * @var array
     */
    protected $fields = [];

    /**
     * Массив состояний проверки полей
     * @var array
     */
    protected $status = [];

    /**
     * Массив входящих данных для обработки
     * @var array
     */
    protected $raw;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->validators = [
            'array'         => [$this, 'vArray'],
            'checkbox'      => [$this, 'vCheckbox'],
            'email'         => [$this, 'vEmail'],
            'in'            => [$this, 'vIn'],
            'integer'       => [$this, 'vInteger'],
            'login'         => [$this, 'vLogin'],
            'max'           => [$this, 'vMax'],
            'min'           => [$this, 'vMin'],
            'numeric'       => [$this, 'vNumeric'],
            'not_in'        => [$this, 'vNotIn'],
            'password'      => [$this, 'vPassword'],
            'referer'       => [$this, 'vReferer'],
            'regex'         => [$this, 'vRegex'],
            'required'      => [$this, 'vRequired'],
            'required_with' => [$this, 'vRequiredWith'],
            'same'          => [$this, 'vSame'],
            'string'        => [$this, 'vString'],
            'token'         => [$this, 'vToken'],
        ];
    }

    /**
     * Добавление новых валидаторов
     *
     * @param array $validators
     *
     * @return Validator
     */
    public function addValidators(array $validators)
    {
        $this->validators = array_replace($this->validators, $validators);
        return $this;
    }

    /**
     * Установка правил проверки
     *
     * @param array $list
     *
     * @throws RuntimeException
     *
     * @return Validator
     */
    public function setRules(array $list)
    {
        $this->rules = [];
        $this->result = [];
        $this->alias = [];
        $this->errors = [];
        $this->arguments = [];
        $this->fields = [];
        foreach ($list as $field => $raw) {
            $rules = [];
            // псевдоним содержится в списке правил
            if (is_array($raw)) {
                list($raw, $this->aliases[$field]) = $raw;
            }
            // перебор правил для текущего поля
            foreach (explode('|', $raw) as $rule) {
                 $tmp = explode(':', $rule, 2);
                 if (empty($this->validators[$tmp[0]])) {
                     throw new RuntimeException($tmp[0] . ' validator not found');
                 }
                 $rules[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            $this->rules[$field] = $rules;
            $this->fields[$field] = $field;
        }
        return $this;
    }

    /**
     * Установка дополнительных аргументов для конкретных "имя поля"."имя правила".
     *
     * @param array $arguments
     *
     * @return Validator
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * Установка сообщений для конкретных "имя поля"."имя правила".
     *
     * @param array $messages
     *
     * @return Validator
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Установка псевдонимов имен полей для сообщений об ошибках
     *
     * @param array $aliases
     *
     * @return Validator
     */
    public function setAliases(array $aliases)
    {
        $this->aliases = array_replace($this->aliases, $aliases);
        return $this;
    }

    /**
     * Проверка данных
     * Удачная проверка возвращает true
     *
     * @param array $raw
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function validation(array $raw)
    {
        if (empty($this->rules)) {
            throw new RuntimeException('Rules not found');
        }
        $this->errors = [];
        $this->status = [];
        $this->raw = $raw;
        foreach ($this->fields as $field) {
            $this->__get($field);
        }
        $this->raw = null;
        return empty($this->errors);
    }

    /**
     * Проверяет наличие поля
     *
     * @param string $field
     *
     * @return bool
     */
    public function __isset($field)
    {
        return isset($this->result[$field]); //????
    }

    /**
     * Проверяет поле согласно заданным правилам
     * Возвращает значение запрашиваемого поля
     *
     * @param string
     *
     * @throws RuntimeException
     *
     * @return mixed
     */
    public function __get($field)
    {
        if (isset($this->status[$field])) {
            return $this->result[$field];
        } elseif (empty($this->rules[$field])) {
            throw new RuntimeException("No rules for '{$field}' field");
        }

        $value = null;
        if (! isset($this->raw[$field]) && isset($this->rules[$field]['required'])) {
            $rules = ['required' => ''];
        } else {
            $rules = $this->rules[$field];
            if (isset($this->raw[$field])) {
                $value = $this->c->Secury->replInvalidChars($this->raw[$field]);
            }
        }

        $error = false;
        foreach ($rules as $validator => $attr) {
            $args = $this->getArguments($field, $validator);
            list($value, $error) = $this->validators[$validator]($this, $value, $attr, $args);
            if (false !== $error) {
                break;
            }
        }

        if (! is_bool($error)) {
            $this->error($error, $field, $validator, $attr);
            $this->status[$field] = false;
        } else {
            $this->status[$field] = true;
        }

        $this->result[$field] = $value;
        return $value;
    }

    /**
     * Получение дополнительных аргументов
     *
     * @param string $field
     * @param string $rule
     *
     * @return mixed
     */
    protected function getArguments($field, $rule)
    {
        if (isset($this->arguments[$field . '.' . $rule])) {
            return $this->arguments[$field . '.' . $rule];
        } elseif (isset($this->arguments[$field])) {
            return $this->arguments[$field];
        } else {
            return null;
        }
    }

    /**
     * Обработка ошибки
     *
     * @param mixed $error
     * @param string $field
     * @param string $rule
     * @param string $attr
     */
    protected function error($error, $field, $rule, $attr)
    {
        // псевдоним имени поля
        $alias = isset($this->aliases[$field]) ? $this->aliases[$field] : $field;
        // текст ошибки
        if (isset($this->messages[$field . '.' . $rule])) {
            $error = $this->messages[$field . '.' . $rule];
        } elseif (isset($this->messages[$field])) {
            $error = $this->messages[$field];
        }
        $type = 'v';
        // ошибка содержит тип
        if (is_array($error)) {
            $type = $error[1];
            $error = $error[0];
        }
        $this->errors[$type][] = __($error, [':alias' => $alias, ':attr' => $attr]);
    }

    /**
     * Возвращает статус проверки поля
     *
     * @param string $field
     *
     * @return bool
     */
    public function getStatus($field)
    {
        if (! isset($this->status[$field])) {
            $this->__get($field);
        }
        return $this->status[$field];
    }

    /**
     * Возвращает проверенные данные
     * Поля с ошибками содержат значения по умолчанию или значения с ошибками
     *
     * @throws RuntimeException
     *
     * @return array
     */
    public function getData()
    {
        if (empty($this->result)) {
            throw new RuntimeException('Data not found');
        }
        return $this->result;
    }

    /**
     * Возращает массив ошибок
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    protected function vRequired($v, $value)
    {
        if (is_string($value)) {
            if (strlen(preg_replace('%^\s+|\s+$%u', '', $value)) > 0) {
                return [$value, false];
            }
        } elseif (is_array($value)) {
            if (! empty($value)) {
                return [$value, false];
            }
        } elseif (null !== $value) {
            return [$value, false];
        }
        return [null, 'The :alias is required'];
    }

    protected function vRequiredWith($v, $value, $attr)
    {
        foreach (explode(',', $attr) as $field) {
            if (null !== $this->__get($field)) {
                return $this->vRequired($v, $value);
            }
        }
        list(, $error) = $this->vRequired($v, $value);
        if (false === $error) {
            return [null, 'The :alias is not required'];
        } else {
            return [$value, true];
        }
    }

    protected function vString($v, $value, $attr)
    {
        if (null === $value) {
            return [null, false];
        } elseif (is_string($value)) {
            foreach(explode(',', $attr) as $action) {
                switch ($action) {
                    case 'trim':
                        $value = preg_replace('%^\s+|\s+$%u', '', $value);
                        break;
                    case 'lower':
                        $value = mb_strtolower($value, 'UTF-8');
                        break;
                }
            }
            return [$value, false];
        } else {
            return [null, 'The :alias must be string'];
        }
    }

    protected function vNumeric($v, $value)
    {
        if (null === $value) {
            return [null, false];
        } elseif (is_numeric($value)) {
            return [0 + $value, false];
        } else {
            return [null, 'The :alias must be numeric'];
        }
    }

    protected function vInteger($v, $value)
    {
        if (null === $value) {
            return [null, false];
        } elseif (is_numeric($value) && is_int(0 + $value)) {
            return [(int) $value, false];
        } else {
            return [null, 'The :alias must be integer'];
        }
    }

    protected function vArray($v, $value)
    {
        if (null === $value) {
            return [null, false];
        } elseif (is_array($value)) {
            return [$value, false];
        } else {
            return [null, 'The :alias must be array'];
        }
    }

    protected function vMin($v, $value, $attr)
    {
        if (is_numeric($value)) {
            if (0 + $value < $attr) {
                return [$value, 'The :alias minimum is :attr'];
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value, 'UTF-8') < $attr) {
                return [$value, 'The :alias minimum is :attr characters'];
            }
        } elseif (is_array($value)) {
            if (count($value) < $attr) {
                return [$value, 'The :alias minimum is :attr elements'];
            }
        } else {
            return [null, null === $value ? false : 'The :alias minimum is :attr'];
        }
        return [$value, false];
    }

    protected function vMax($v, $value, $attr)
    {
        if (is_numeric($value)) {
            if (0 + $value > $attr) {
                return [$value, 'The :alias maximum is :attr'];
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value, 'UTF-8') > $attr) {
                return [$value, 'The :alias maximum is :attr characters'];
            }
        } elseif (is_array($value)) {
            if (count($value) > $attr) {
                return [$value, 'The :alias maximum is :attr elements'];
            }
        } else {
            return [null, null === $value ? false : 'The :alias maximum is :attr'];
        }
        return [$value, false];
    }

    protected function vToken($v, $value, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        $value = (string) $value;
        if ($this->c->Csrf->verify($value, $attr, $args)) {
            return [$value, false];
        } else {
            return [$value, ['Bad token', 'e']];
        }
    }

    protected function vCheckbox($v, $value)
    {
        return [! empty($value), false];
    }

    protected function vReferer($v, $value, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        return [$this->c->Router->validate($value, $attr, $args), false];
    }

    protected function vEmail($v, $value)
    {
        if (null === $value) {
            return [$value, false];
        }
        $email = $this->c->Mail->valid($value, true);
        if (false === $email) {
            return [(string) $value, 'The :alias is not valid email'];
        } else {
            return [$email, false];
        }
    }

    protected function vSame($v, $value, $attr)
    {
        if (! $this->getStatus($attr) || $value === $this->__get($attr)) {
            return [$value, false];
        } else {
            return [null, 'The :alias must be same with original'];
        }
    }

    protected function vRegex($v, $value, $attr)
    {
        if (null === $value) {
            return [$value, false];
        } elseif (is_string($value) && preg_match($attr, $value)) {
            return [$value, false];
        } else {
            return [null, 'The :alias is not valid format'];
        }
    }

    protected function vPassword($v, $value)
    {
        return $this->vRegex($v, $value, '%[^\x20][\x20][^\x20]%');
    }

    protected function vLogin($v, $value)
    {
        return $this->vRegex($v, $value, '%^\p{L}[\p{L}\p{N}\x20\._-]+$%uD');
    }

    protected function vIn($v, $value, $attr)
    {
        if (null === $value || in_array($value, explode(',', $attr))) {
            return [$value, false];
        } else {
            return [$value, 'The :alias contains an invalid value'];
        }
    }

    protected function vNotIn($v, $value, $attr)
    {
        if (null === $value || ! in_array($value, explode(',', $attr))) {
            return [$value, false];
        } else {
            return [$value, 'The :alias contains an invalid value'];
        }
    }
}
