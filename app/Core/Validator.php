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
    protected $validators;

    /**
     * Массив правил для текущей проверки данных
     * @var array
     */
    protected $rules;

    /**
     * Массив результатов проверенных данных
     * @var array
     */
    protected $result;

    /**
     * Массив дополнительных аргументов для валидаторов и конкретных полей/правил
     * @var array
     */
    protected $arguments;

    /**
     * Массив сообщений об ошибках для конкретных полей/правил
     * @var array
     */
    protected $messages;

    /**
     * Массив псевдонимов имен полей для вывода в ошибках
     * @var array
     */
    protected $aliases;

    /**
     * Массив ошибок валидации
     * @var array
     */
    protected $errors;

    /**
     * Массив имен полей для обработки
     * @var array
     */
    protected $fields;

    /**
     * Массив состояний проверки полей
     * @var array
     */
    protected $status;

    /**
     * Массив входящих данных для обработки
     * @var array
     */
    protected $raw;

    /**
     * Данные для текущей обработки
     * @var array
     */
    protected $curData;

    /**
     * Флаг ошибки
     * @var mixed
     */
    protected $error;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->reset();
    }

    /**
     * Сброс настроек
     *
     * @return Validator
     */
    public function reset()
    {
        $this->validators = [
            'absent'        => [$this, 'vAbsent'],
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
        $this->rules     = [];
        $this->result    = [];
        $this->arguments = [];
        $this->messages  = [];
        $this->aliases   = [];
        $this->errors    = [];
        $this->fields    = [];
        $this->status    = [];
        return $this;
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
        $this->validators = \array_replace($this->validators, $validators);
        return $this;
    }

    /**
     * Добавление правил проверки
     *
     * @param array $list
     *
     * @throws RuntimeException
     *
     * @return Validator
     */
    public function addRules(array $list)
    {
        foreach ($list as $field => $raw) {
            $suffix = null;
            // правило для элементов массива
            if (\strpos($field, '.') > 0) {
                list($field, $suffix) = \explode('.', $field, 2);
            }
            $rules = [];
            // перебор правил для текущего поля
            foreach (\explode('|', $raw) as $rule) { //???? нужно экоанирование для разделителей
                 $tmp = \explode(':', $rule, 2);
                 if (empty($this->validators[$tmp[0]])) {
                     throw new RuntimeException($tmp[0] . ' validator not found');
                 }
                 $rules[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : '';
            }
            if (isset($suffix)) {
                $this->rules[$field]['array'][$suffix] = $rules;
            } else {
                $this->rules[$field] = $rules;
            }
            $this->fields[$field] = $field;
        }
        return $this;
    }

    /**
     * Добавление дополнительных аргументов для конкретных "имя поля"."имя правила".
     *
     * @param array $arguments
     *
     * @return Validator
     */
    public function addArguments(array $arguments)
    {
        $this->arguments = \array_replace($this->arguments, $arguments);
        return $this;
    }

    /**
     * Добавление сообщений для конкретных "имя поля"."имя правила".
     *
     * @param array $messages
     *
     * @return Validator
     */
    public function addMessages(array $messages)
    {
        $this->messages = \array_replace($this->messages, $messages);
        return $this;
    }

    /**
     * Добавление псевдонимов имен полей для сообщений об ошибках
     *
     * @param array $aliases
     *
     * @return Validator
     */
    public function addAliases(array $aliases)
    {
        $this->aliases = \array_replace($this->aliases, $aliases);
        return $this;
    }

    /**
     * Проверка данных
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
        $this->errors  = [];
        $this->status  = [];
        $this->curData = [];
        $this->raw     = $raw;
        foreach ($this->fields as $field) {
            $this->__get($field);
        }
        $this->raw     = null;
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
        return isset($this->result[$field]);
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

        $value = $this->checkValue($value, $rules, $field);

        $this->status[$field] = true !== $this->error; // в $this->error может быть состояние false
        $this->result[$field] = $value;

        return $value;
    }

    /**
     * Проверка значения списком правил
     *
     * @param mixed $value
     * @param array $rules
     * @param string $field
     *
     * @return mixed
     */
    protected function checkValue($value, array $rules, $field)
    {
        foreach ($rules as $validator => $attr) {
            // данные для обработчика ошибок
            $this->error     = null;
            $this->curData[] = [
                'field' => $field,
                'rule'  => $validator,
                'attr'  => $attr,
            ];

            $value = $this->validators[$validator]($this, $value, $attr, $this->getArguments($field, $validator));

            \array_pop($this->curData);

            if (null !== $this->error) {
                break;
            }
        }
        return $value;
    }

    /**
     * Добавление ошибки
     *
     * @param mixed $error
     * @param string $type
     *
     * @throws RuntimeException
     */
    public function addError($error, $type = 'v')
    {
        if (empty($vars = \end($this->curData))) {
            throw new RuntimeException('The array of variables is empty');
        }

        // нет ошибки, для выхода из цикла проверки правил
        if (true === $error) {
            $this->error = false;
            return;
        }

        \extract($vars);

        // псевдоним имени поля
        $alias = isset($this->aliases[$field]) ? $this->aliases[$field] : $field;

        // текст ошибки
        if (isset($this->messages[$field . '.' . $rule])) {
            $error = $this->messages[$field . '.' . $rule];
        } elseif (isset($this->messages[$field])) {
            $error = $this->messages[$field];
        }
        if (\is_array($error)) {
            $type = $error[1];
            $error = $error[0];
        }

        $this->errors[$type][] = \ForkBB\__($error, [':alias' => \ForkBB\__($alias), ':attr' => $attr]);
        $this->error           = true;
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

    protected function vAbsent($v, $value, $attr)
    {
        if (null !== $value) {
            $this->addError('The :alias should be absent');
        }
        if (isset($attr{0})) {
            return $attr;
        } else {
            return null;
        }
    }

    protected function vRequired($v, $value)
    {
        if (\is_string($value)) {
            if (\strlen(\preg_replace('%^\s+|\s+$%u', '', $value)) > 0) {
                return $value;
            }
        } elseif (\is_array($value)) {
            if (! empty($value)) {
                return $value;
            }
        } elseif (null !== $value) {
            return $value;
        }
        $this->addError('The :alias is required');
        return null;
    }

    protected function vRequiredWith($v, $value, $attr)  //???????????????????????
    {
        foreach (\explode(',', $attr) as $field) {
            if (null !== $this->__get($field)) {     // если есть хотя бы одно поле,
                return $this->vRequired($v, $value); // то проверяем данное поле
            }                                        // на обязательное наличие
        }
        if (null === $value) {                       // если данное поле отсутствует,
            $this->addError(true);                   // то прерываем его проверку
        }
        return $value;

#        list(, $error) = $this->vRequired($v, $value);
#        if (false === $error) {
#            return [null, 'The :alias is not required'];
#        } else {
#            return [$value, true];
#        }
    }

    protected function vString($v, $value, $attr)
    {
        if (null === $value) {
            return null;
        } elseif (\is_string($value)) {
            foreach(\explode(',', $attr) as $action) {
                switch ($action) {
                    case 'trim':
                        $value = \preg_replace('%^\s+|\s+$%u', '', $value);
                        break;
                    case 'lower':
                        $value = \mb_strtolower($value, 'UTF-8');
                        break;
                    case 'spaces':
                        $value = \preg_replace('%\s+%u', ' ', $value);
                        break;
                }
            }
            return $value;
        } else {
            $this->addError('The :alias must be string');
            return null;
        }
    }

    protected function vNumeric($v, $value)
    {
        if (null === $value) {
            return null;
        } elseif (\is_numeric($value)) {
            return 0 + $value;
        } else {
            $this->addError('The :alias must be numeric');
            return null;
        }
    }

    protected function vInteger($v, $value)
    {
        if (null === $value) {
            return null;
        } elseif (\is_numeric($value) && \is_int(0 + $value)) {
            return (int) $value;
        } else {
            $this->addError('The :alias must be integer');
            return null;
        }
    }

    protected function vArray($v, $value, $attr)
    {
        if (null !== $value && ! \is_array($value)) {
            $this->addError('The :alias must be array');
            return null;
        } elseif (! $attr) {
            return $value;
        }

        if (empty($vars = \end($this->curData))) {
            throw new RuntimeException('The array of variables is empty');
        }

        $result = [];
        foreach ($attr as $name => $rules) {
            $this->recArray($value, $result, $name, $rules, $vars['field'] . '.' . $name);
        }
        return $result;
    }

    protected function recArray(&$value, &$result, $name, $rules, $field)
    {
        $idxs = \explode('.', $name);
        $key  = \array_shift($idxs);
        $name = \implode('.', $idxs);

        if ('*' === $key) {
            if (! \is_array($value)) {
                return; //????
            }

            foreach ($value as $i => $cur) {
                if ('' === $name) {
                    $result[$i] = $this->checkValue($cur, $rules, $field);
                } else {
                    $this->recArray($value[$i], $result[$i], $name, $rules, $field);
                }
            }
        } else {
            if (! \array_key_exists($key, $value)) {
                return; //????
            }

            if ('' === $name) {
                $result[$key] = $this->checkValue($value[$key], $rules, $field);
            } else {
                $this->recArray($value[$key], $result[$key], $name, $rules, $field);
            }
        }
    }



    protected function vMin($v, $value, $attr)
    {
        if (\is_string($value)) {
            if ((\strpos($attr, 'bytes') && \strlen($value) < (int) $attr)
                || \mb_strlen($value, 'UTF-8') < $attr
            ) {
                $this->addError('The :alias minimum is :attr characters');
            }
        } elseif (\is_numeric($value)) {
            if (0 + $value < $attr) {
                $this->addError('The :alias minimum is :attr');
            }
        } elseif (\is_array($value)) {
            if (\count($value) < $attr) {
                $this->addError('The :alias minimum is :attr elements');
            }
        } elseif (null !== $value) {
            $this->addError('The :alias minimum is :attr');
            return null;
        }
        return $value;
    }

    protected function vMax($v, $value, $attr)
    {
        if (\is_string($value)) {
            if ((\strpos($attr, 'bytes') && \strlen($value) > (int) $attr)
                || \mb_strlen($value, 'UTF-8') > $attr
            ) {
                $this->addError('The :alias maximum is :attr characters');
            }
        } elseif (\is_numeric($value)) {
            if (0 + $value > $attr) {
                $this->addError('The :alias maximum is :attr');
            }
        } elseif (\is_array($value)) {
            if (count($value) > $attr) {
                $this->addError('The :alias maximum is :attr elements');
            }
        } elseif (null !== $value) {
            $this->addError('The :alias maximum is :attr'); //????
            return null;
        }
        return $value;
    }

    protected function vToken($v, $value, $attr, $args)
    {
        if (! \is_array($args)) {
            $args = [];
        }
        if (! \is_string($value) || ! $this->c->Csrf->verify($value, $attr, $args)) {
            $this->addError('Bad token', 'e');
            return null;
        } else {
            return $value;
        }
    }

    protected function vCheckbox($v, $value)
    {
        return null === $value ? false : (string) $value;
    }

    protected function vReferer($v, $value, $attr, $args)
    {
        if (! \is_array($args)) {
            $args = [];
        }
        return $this->c->Router->validate($value, $attr, $args);
    }

    protected function vEmail($v, $value)
    {
        if (null === $value || '' === $value) { //???? перед правилом должно стоять правило `required`
            return null;
        }
        $email = $this->c->Mail->valid($value, true);
        if (false === $email) {
            $this->addError('The :alias is not valid email');
            return $value;
        } else {
            return $email;
        }
    }

    protected function vSame($v, $value, $attr)
    {
        if (! $this->getStatus($attr) || $value === $this->__get($attr)) {
            return $value;
        } else {
            $this->addError('The :alias must be same with original');
            return null;
        }
    }

    protected function vRegex($v, $value, $attr)
    {
        if (null !== $value
            && (! is_string($value) || ! \preg_match($attr, $value))
        ) {
            $this->addError('The :alias is not valid format');
            return null;
        } else {
            return $value;
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
        if (null !== $value && ! \in_array($value, \explode(',', $attr))) {
            $this->addError('The :alias contains an invalid value');
        }
        return $value;
    }

    protected function vNotIn($v, $value, $attr)
    {
        if (null !== $value && \in_array($value, \explode(',', $attr))) {
            $this->addError('The :alias contains an invalid value');
        }
        return $value;
    }
}
