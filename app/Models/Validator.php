<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use RuntimeException;

class Validator
{
    const T_UNKNOWN = 0;
    const T_STRING = 1;
    const T_NUMERIC = 2;
    const T_INT = 3;
    const T_ARRAY = 4;
    const T_BOOLEAN = 5;

    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * @var array
     */
    protected $validators;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var array
     */
    protected $result;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var array
     */
    protected $messages;

    /**
     * @var array
     */
    protected $aliases;

    /**
     * @var array
     */
    protected $errors;

    protected $fields;
    protected $status;
    protected $raw;

    /**
     * Конструктор
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
     * @param array $validators
     * @param Validator
     */
    public function addValidators(array $validators)
    {
        $this->validators = array_replace($this->validators, $validators);
        return $this;
    }

    /**
     * Установка правил проверки
     * @param array $list
     * @return Validator
     * @throws RuntimeException
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
     * @param array $arguments
     * @return Validator
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * Установка сообщений для конкретных "имя поля"."имя правила".
     * @param array $messages
     * @return Validator
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Установка псевдонимов имен полей для сообщений об ошибках
     * @param array $aliases
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
     * @param array $raw
     * @return bool
     * @throws \RuntimeException
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
            $this->$field;
        }
        $this->raw = null;
        return empty($this->errors);
    }

    /**
     * Проверяет наличие поля
     * @param string $field
     * @return bool
     */
    public function __isset($field)
    {
        return isset($this->result[$field]); //????
    }

    /**
     * Проверяет поле согласно заданным правилам
     * Возвращает значение запрашиваемого поля
     * @param string
     * @return mixed
     * @throws \RuntimeException
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
        $type = self::T_UNKNOWN;
        foreach ($rules as $validator => $attr) {
            $args = $this->getArguments($field, $validator);
            list($value, $type, $error) = $this->validators[$validator]($this, $value, $type, $attr, $args);
            // ошибок нет
            if (false === $error) {
                continue;
            }
            break;
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
     * @param string $field
     * @param string $field
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
     * @param string $field
     * @return bool
     */
    public function getStatus($field)
    {
        if (! isset($this->status[$field])) {
            $this->$field;
        }
        return $this->status[$field];
    }

    /**
     * Возвращает проверенные данные
     * Поля с ошибками содержат значения по умолчанию или значения с ошибками
     * @return array
     * @throws \RuntimeException
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
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    protected function vRequired($v, $value, $type)
    {
        if (is_string($value)) {
            if (strlen(trim($value)) > 0) {
                return [$value, $v::T_STRING, false];
            }
        } elseif (is_array($value)) {
            if (! empty($value)) {
                return [$value, $v::T_ARRAY, false];
            }
        } elseif (null !== $value) {
            if (is_int($value)) {
                $type = $v::T_INT;
            } elseif (is_numeric($value)) {
                $type = $v::T_NUMERIC;
            }
            return [$value, $type, false];
        }
        return [null, $type, 'The :alias is required'];
    }

    protected function vRequiredWith($v, $value, $type, $attr)
    {
        foreach (explode(',', $attr) as $field) {
            if (null !== $v->$field) {
                return $this->vRequired($v, $value, $type);
            }
        }
        list(, , $error) = $this->vRequired($v, $value, $type);
        if (false === $error) {
            return [null, $type, 'The :alias is not required'];
        } else {
            return [$value, $type, true];
        }
    }

    protected function vString($v, $value, $type, $attr)
    {
        if (null === $value) {
            return [null, $type, false];
        } elseif (is_string($value)) {
            foreach(explode(',', $attr) as $action) {
                switch ($action) {
                    case 'trim':
                        $value = preg_replace('%^\s+|\s+$%u', '', $value); // trim($value);
                        break;
                    case 'lower':
                        $value = mb_strtolower($value, 'UTF-8');
                        break;
                }
            }
            return [$value, $v::T_STRING, false];
        } else {
            return [null, $type, 'The :alias must be string'];
        }
    }

    protected function vNumeric($v, $value, $type)
    {
        if (null === $value) {
            return [null, $type, false];
        } elseif (is_numeric($value)) {
            return [0 + $value, $v::T_NUMERIC, false];
        } else {
            return [null, $type, 'The :alias must be numeric'];
        }
    }

    protected function vInteger($v, $value, $type)
    {
        if (null === $value) {
            return [null, $type, false];
        } elseif (is_numeric($value) && is_int(0 + $value)) {
            return [(int) $value, $v::T_INT, false];
        } else {
            return [null, $type, 'The :alias must be integer'];
        }
    }

    protected function vArray($v, $value, $type)
    {
        if (null === $value) {
            return [null, $type, false];
        } elseif (is_array($value)) {
            return [$value, $v::T_ARRAY, false];
        } else {
            return [null, $type, 'The :alias must be array'];
        }
    }

    protected function vMin($v, $value, $type, $attr)
    {
        if (null === $value) {
            return [null, $type, false];
        }
        switch ($type) {
            case self::T_STRING:
                if (mb_strlen($value, 'UTF-8') < $attr) {
                    return [$value, $type, 'The :alias minimum is :attr characters'];
                }
                break;
            case self::T_NUMERIC:
            case self::T_INT:
                if ($value < $attr) {
                    return [$value, $type, 'The :alias minimum is :attr'];
                }
                break;
            case self::T_ARRAY:
                if (count($value) < $attr) {
                    return [$value, $type, 'The :alias minimum is :attr elements'];
                }
                break;
            default:
                return [null, $type, 'The :alias minimum is :attr'];
                break;
        }
        return [$value, $type, false];
    }

    protected function vMax($v, $value, $type, $attr)
    {
        if (null === $value) {
            return [null, $type, false];
        }
        switch ($type) {
            case self::T_STRING:
                if (mb_strlen($value, 'UTF-8') > $attr) {
                    return [$value, $type, 'The :alias maximum is :attr characters'];
                }
                break;
            case self::T_NUMERIC:
            case self::T_INT:
                if ($value > $attr) {
                    return [$value, $type, 'The :alias maximum is :attr'];
                }
                break;
            case self::T_ARRAY:
                if (count($value) > $attr) {
                    return [$value, $type, 'The :alias maximum is :attr elements'];
                }
                break;
            default:
                return [null, $type, 'The :alias maximum is :attr'];
                break;
        }
        return [$value, $type, false];
    }

    protected function vToken($v, $value, $type, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        $value = (string) $value;
        if ($this->c->Csrf->verify($value, $attr, $args)) {
            return [$value, $type, false];
        } else {
            return [$value, $type, ['Bad token', 'e']];
        }
    }

    protected function vCheckbox($v, $value)
    {
        return [! empty($value), $v::T_BOOLEAN, false];
    }

    protected function vReferer($v, $value, $type, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        return [$this->c->Router->validate($value, $attr, $args), $type, false];
    }

    protected function vEmail($v, $value, $type)
    {
        if (null === $value) {
            return [$value, $type, false];
        }
        $email = $this->c->Mail->valid($value, true);
        if (false === $email) {
            return [(string) $value, $type, 'The :alias is not valid email'];
        } else {
            return [$email, $type, false];
        }
    }

    protected function vSame($v, $value, $type, $attr)
    {
        if (! $v->getStatus($attr) || $value === $v->$attr) {
            return [$value, $type, false];
        } else {
            return [null, $type, 'The :alias must be same with original'];
        }
    }

    protected function vRegex($v, $value, $type, $attr)
    {
        if (null === $value) {
            return [$value, $type, false];
        } elseif ($type === $v::T_STRING && preg_match($attr, $value)) {
            return [$value, $type, false];
        } else {
            return [null, $type, 'The :alias is not valid format'];
        }
    }

    protected function vPassword($v, $value, $type)
    {
        return $this->vRegex($v, $value, $type, '%^(?=.*\p{N})(?=.*\p{Lu})(?=.*\p{Ll})(?=.*[^\p{N}\p{L}])%u');
    }

    protected function vLogin($v, $value, $type)
    {
        return $this->vRegex($v, $value, $type, '%^\p{L}[\p{L}\p{N}\x20\._-]+$%uD');
    }

    protected function vIn($v, $value, $type, $attr)
    {
        if (null === $value || in_array($value, explode(',', $attr))) {
            return [$value, $type, false];
        } else {
            return [null, $type, 'The :alias contains an invalid value'];
        }
    }
}
