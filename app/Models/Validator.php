<?php

namespace ForkBB\Models;

use R2\DependencyInjection\ContainerInterface;
use RuntimeException;

class Validator
{
    const T_UNKNOWN = 0;
    const T_STRING = 1;
    const T_NUMERIC = 2;
    const T_INT = 3;
    const T_ARRAY = 4;

    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var array
     */
    protected $data;

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

    /**
     * Тип текущего поля при валидации
     * @var int
     */
    protected $curType;

    /**
     * Конструктор
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
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
        $this->data = [];
        $this->alias = [];
        $this->errors = [];
        $this->arguments = [];
        foreach ($list as $field => $raw) {
            $rules = [];
            // псевдоним содержится в списке правил
            if (is_array($raw)) {
                $this->aliases[$field] = $raw[1];
                $raw = $raw[0];
            }
            // перебор правил для текущего поля
            $rawRules = explode('|', $raw);
            foreach ($rawRules as $rule) {
                 $tmp = explode(':', $rule, 2);
                 if (! method_exists($this, $tmp[0] . 'Rule')) {
                     throw new RuntimeException('Rule not found');
                 }
                 $rules[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            $this->rules[$field] = $rules;
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
        $this->aliases = $aliases;
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
        $ok = true;
        $this->errors = [];
        // перебор всех полей
        foreach ($this->rules as $field => $rules) {
            $error = false;
            $this->curType = self::T_UNKNOWN;
            // обязательное поле отсутствует
            if (! isset($raw[$field]) && isset($rules['required'])) {
                $rule = 'required';
                $attr = $rules['required'];
                $args = $this->getArguments($field, $rule);
                list($value, $error) = $this->requiredRule('', $attr, $args);
            } else {
                $value = isset($raw[$field])
                    ? $this->c->get('Secury')->replInvalidChars($raw[$field])
                    : null;
                // перебор правил для текущего поля
                foreach ($rules as $rule => $attr) {
                    $args = $this->getArguments($field, $rule);
                    $method = $rule . 'Rule';
                    list($value, $error) = $this->$method($value, $attr, $args);
                    // ошибок нет
                    if (false === $error) {
                        continue;
                    }
                    break;
                }
            }
            $ok = $this->error($error, $field, $rule, $attr, $ok);
            $this->data[$field] = $value;
        }
        return $ok;
    }

    /**
     * Получение дополнительных аргументов
     * @param string $field
     * @param string $field
     * @return mixed
     */
    protected function getArguments($field, $rule)
    {
        if (isset($this->arguments[$field . '.'. $rule])) {
            return $this->arguments[$field . '.'. $rule];
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
     * @param bool $ok
     * return bool
     */
    protected function error($error, $field, $rule, $attr, $ok)
    {
        if (is_bool($error)) {
            return $ok;
        }
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
        return false;
    }

    /**
     * Возвращает проверенные данные
     * Поля с ошибками содержат значения по умолчанию или значения с ошибками
     * @return array
     * @throws \RuntimeException
     */
    public function getData()
    {
        if (empty($this->data)) {
            throw new RuntimeException('Data not found');
        }
        return $this->data;
    }

    /**
     * Возращает массив ошибок
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Правило "required"
     * @param mixed $value
     * @param string $attrs
     * @param mixed $args
     * @return array
     */
    protected function requiredRule($value, $attr, $args)
    {
        $f = function () use ($value) {
            if (is_string($value)) {
                $this->curType = self::T_STRING;
                return isset($value{0});
            } elseif (is_array($value)) {
                $this->curType = self::T_ARRAY;
                return ! empty($value);
            } else {
                return null !== $value;
            }
        };
        if ($f()) {
            if (is_numeric($value)) {
                if (is_int(0 + $value)) {
                    $this->curType = self::T_INT;
                } else {
                    $this->curType = self::T_NUMERIC;
                }
            }
            return [$value, false];
        } else {
            return [$attr, 'The :alias is required'];
        }
    }

    protected function stringRule($value, $attr)
    {
        if (is_string($value)) {
            $this->curType = self::T_STRING;
            return [$value, false];
        } else {
            return [$attr, 'The :alias must be string'];
        }
    }

    protected function numericRule($value, $attr)
    {
        if (is_numeric($value)) {
            $this->curType = self::T_NUMERIC;
            return [0 + $value, false];
        } else {
            return [$attr, 'The :alias must be numeric'];
        }
    }

    protected function intRule($value, $attr)
    {
        if (is_numeric($value) && is_int(0 + $value)) {
            $this->curType = self::T_INT;
            return [(int) $value, false];
        } else {
            return [$attr, 'The :alias must be integer'];
        }
    }

    protected function arrayRule($value, $attr)
    {
        if (is_array($value)) {
            $this->curType = self::T_ARRAY;
            return [$value, false];
        } else {
            return [$attr, 'The :alias must be array'];
        }
    }

    protected function minRule($value, $attr)
    {
        switch ($this->curType) {
            case self::T_STRING:
                if (mb_strlen($value) < $attr) {
                    return [$value, 'The :alias minimum is :attr characters'];
                }
                break;
            case self::T_NUMERIC:
            case self::T_INT:
                if ($value < $attr) {
                    return [$value, 'The :alias minimum is :attr'];
                }
                break;
            case self::T_ARRAY:
                if (count($value) < $attr) {
                    return [$value, 'The :alias minimum is :attr elements'];
                }
                break;
            default:
                return ['', 'The :alias minimum is :attr'];
                break;
        }
        return [$value, false];
    }

    protected function maxRule($value, $attr)
    {
        switch ($this->curType) {
            case self::T_STRING:
                if (mb_strlen($value) > $attr) {
                    return [$value, 'The :alias maximum is :attr characters'];
                }
                break;
            case self::T_NUMERIC:
            case self::T_INT:
                if ($value > $attr) {
                    return [$value, 'The :alias maximum is :attr'];
                }
                break;
            case self::T_ARRAY:
                if (count($value) > $attr) {
                    return [$value, 'The :alias maximum is :attr elements'];
                }
                break;
            default:
                return ['', 'The :alias maximum is :attr'];
                break;
        }
        return [$value, false];
    }

    protected function tokenRule($value, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        if (is_string($value) && $this->c->get('Csrf')->verify($value, $attr, $args)) {
            return [$value, false];
        } else {
            return ['', ['Bad token', 'e']];
        }
    }

    protected function checkboxRule($value, $attr)
    {
        return [! empty($value), false]; //????
    }

    protected function refererRule($value, $attr, $args)
    {
        if (! is_array($args)) {
            $args = [];
        }
        return [$this->c->get('Router')->validate($value, $attr), false];
    }

    protected function emailRule($value, $attr)
    {
        if ($this->c->get('Mail')->valid($value)) {
            return [$value, false];
        } else {
            if (! is_string($value)) {
                $value = (string) $value;
            }
            return [$value, 'The :alias is not valid email'];
        }
    }

    protected function sameRule($value, $attr)
    {
        if (isset($this->data[$attr]) && $value === $this->data[$attr]) {
            return [$value, false];
        } else {
            return [$value, 'The :alias must be same with original'];
        }
    }
}
