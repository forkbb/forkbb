<?php

namespace ForkBB\Core;

use ForkBB\Core\Container;
use ForkBB\Core\File;
use ForkBB\Core\Validators;
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
     * Сбрасывает настройки к начальным состояниям
     *
     * @return Validator
     */
    public function reset(): self
    {
        $this->validators = [
            'absent'        => [$this, 'vAbsent'],
            'array'         => [$this, 'vArray'],
            'checkbox'      => [$this, 'vCheckbox'],
            'date'          => [$this, 'vDate'],
            'file'          => [$this, 'vFile'],
            'image'         => [$this, 'vImage'],
            'in'            => [$this, 'vIn'],
            'integer'       => [$this, 'vInteger'],
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
     * Добавляет валидаторы
     *
     * @param array $validators
     *
     * @return Validator
     */
    public function addValidators(array $validators): self
    {
        $this->validators = \array_replace($this->validators, $validators);
        return $this;
    }

    /**
     * Добавляет правила
     *
     * @param array $list
     *
     * @throws RuntimeException
     *
     * @return Validator
     */
    public function addRules(array $list): self
    {
        foreach ($list as $field => $raw) {
            $suffix = null;
            // правило для элементов массива
            if (\strpos($field, '.') > 0) {
                list($field, $suffix) = \explode('.', $field, 2);
            }
            $rules = [];
            // перебор правил для текущего поля
            foreach (\explode('|', $raw) as $rule) { //???? нужно экранирование для разделителей
                 $vs = \explode(':', $rule, 2);
                 if (empty($this->validators[$vs[0]])) {
                     try {
                        $validator = $this->c->{'VL' . $vs[0]};
                     } catch (Exception $e) {
                        $validator = null;
                     }
                     if ($validator instanceof Validators) {
                        $this->validators[$vs[0]] = [$validator, $vs[0]];
                     } else {
                        throw new RuntimeException($vs[0] . ' validator not found');
                     }
                 }
                 $rules[$vs[0]] = $vs[1] ?? '';
            }
            if (isset($suffix)) {
                if (
                    isset($this->rules[$field]['array'])
                    && ! \is_array($this->rules[$field]['array'])
                ) {
                    $this->rules[$field]['array'] = [];
                }
                $this->rules[$field]['array'][$suffix] = $rules;
            } else {
                $this->rules[$field] = $rules;
            }
            $this->fields[$field] = $field;
        }
        return $this;
    }

    /**
     * Добавляет дополнительные аргументы для конкретных "имя поля"."имя правила".
     *
     * @param array $arguments
     *
     * @return Validator
     */
    public function addArguments(array $arguments): self
    {
        $this->arguments = \array_replace($this->arguments, $arguments);
        return $this;
    }

    /**
     * Добавляет сообщения для конкретных "имя поля"."имя правила".
     *
     * @param array $messages
     *
     * @return Validator
     */
    public function addMessages(array $messages): self
    {
        $this->messages = \array_replace($this->messages, $messages);
        return $this;
    }

    /**
     * Добавляет псевдонимы имен полей для сообщений об ошибках
     *
     * @param array $aliases
     *
     * @return Validator
     */
    public function addAliases(array $aliases): self
    {
        $this->aliases = \array_replace($this->aliases, $aliases);
        return $this;
    }

    /**
     * Проверяет данные
     *
     * @param array $raw
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function validation(array $raw): bool
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
    public function __isset(string $field): bool
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
    public function __get(string $field)
    {
        if (isset($this->status[$field])) {
            return $this->result[$field];
        } elseif (empty($this->rules[$field])) {
            throw new RuntimeException("No rules for '{$field}' field");
        }

        $value = null;
        if (
            ! isset($this->raw[$field])
            && isset($this->rules[$field]['required'])
        ) {
            $rules = ['required' => ''];
        } else {
            $rules = $this->rules[$field];
            if (isset($this->raw[$field])) {
                $value = $this->c->Secury->replInvalidChars($this->raw[$field]);
                // пустое поле в соответствии с правилом 'required' должно быть равно null
                if (
                    (
                        \is_string($value)
                        && 0 === \strlen(\preg_replace('%^\s+|\s+$%u', '', $value))
                    )
                    || (
                        \is_array($value)
                        && empty($value)
                    )
                ) {
                    $value = null;
                }
            }
        }

        $value = $this->checkValue($value, $rules, $field);

        $this->status[$field] = true !== $this->error; // в $this->error может быть состояние false
        $this->result[$field] = $value;

        return $value;
    }

    /**
     * Проверяет значение списком правил
     *
     * @param mixed $value
     * @param array $rules
     * @param string $field
     *
     * @return mixed
     */
    protected function checkValue($value, array $rules, string $field)
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
     * Добавляет ошибку
     *
     * @param string|bool $error
     * @param string $type
     *
     * @throws RuntimeException
     */
    public function addError($error, string $type = 'v'): void
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
        $alias = $this->aliases[$field] ?? $field;

        // текст ошибки
        if (isset($this->messages[$field . '.' . $rule])) {
            $error = $this->messages[$field . '.' . $rule];
        } elseif (isset($this->messages[$field])) {
            $error = $this->messages[$field];
        }
        if (\is_array($error)) {
            list($type, $error) = $error;
        }

        $this->errors[$type][] = \ForkBB\__($error, [':alias' => \ForkBB\__($alias), ':attr' => $attr]);
        $this->error           = true;
    }

    /**
     * Возвращает дополнительные аргументы
     *
     * @param string $field
     * @param string $rule
     *
     * @return mixed
     */
    protected function getArguments(string $field, string $rule)
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
    public function getStatus(string $field): bool
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
     * @param bool $all
     *
     * @throws RuntimeException
     *
     * @return array
     */
    public function getData(bool $all = false): array
    {
        if (empty($this->status)) {
            throw new RuntimeException('Data not found');
        }

        if ($all) {
            return $this->result;
        } else {
            return \array_filter($this->result, function ($value) {
                return null !== $value;
            });
        }
    }

    /**
     * Возращает массив ошибок
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Выполняет проверку значения по правилу
     *
     * @param Validator $first   ссылка на валидатор
     * @param mixed $second      проверяемое значение
     * @param mixed $third       атрибут правила
     * @param mixed $fourth      дополнительный аргумент
     *
     * @return mixed
     */
    protected function vAbsent(Validator $v, $value, $attr)
    {
        if (null !== $value) {
            $this->addError('The :alias should be absent');
        }
        if (isset($attr[0])) {
            return $attr;
        } else {
            return null;
        }
    }

    protected function vRequired(Validator $v, $value)
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

    protected function vRequiredWith(Validator $v, $value, $attr)  //???????????????????????
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

    protected function vString(Validator $v, $value, $attr)
    {
        if (null === $value) {                                // для пустого поля типа string
            if (\preg_match('%(?:^|,)trim(?:,|$)%', $attr)) { // при наличии действия trim
                $this->addError(true);                        // прерываем его проверку
                return '';                                    // и возвращаем пустую строку,
            } else {
                return null;                                  // а не null
            }
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
                    case 'linebreaks':
                        $value = \str_replace(["\r\n", "\r"], "\n", $value);
                        break;
                }
            }
            return $value;
        } else {
            $this->addError('The :alias must be string');
            return null;
        }
    }

    protected function vNumeric(Validator $v, $value)
    {
        if (null === $value) {
            return null;
        } elseif (\is_numeric($value)) {
            return 0.0 + $value;
        } else {
            $this->addError('The :alias must be numeric');
            return null;
        }
    }

    protected function vInteger(Validator $v, $value)
    {
        if (null === $value) {
            return null;
        } elseif (
            \is_numeric($value)
            && \is_int(0 + $value)
        ) {
            return (int) $value;
        } else {
            $this->addError('The :alias must be integer');
            return null;
        }
    }

    protected function vArray(Validator $v, $value, $attr)
    {
        if (
            null !== $value
            && ! \is_array($value)
        ) {
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

    protected function vMin(Validator $v, $value, $attr)
    {
        if (\is_string($value)) {
            $isBytes = \strpos($attr, 'bytes');
            if (
                (
                    $isBytes
                    && \strlen($value) < (int) $attr
                )
                || (
                    ! $isBytes
                    && \mb_strlen($value, 'UTF-8') < $attr
                )
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

    protected function vMax(Validator $v, $value, $attr)
    {
        if (\is_string($value)) {
            $isBytes = \strpos($attr, 'bytes');
            if (
                (
                    $isBytes
                    && \strlen($value) > (int) $attr
                )
                || (
                    ! $isBytes
                    && \mb_strlen($value, 'UTF-8') > $attr
                )
            ) {
                $this->addError('The :alias maximum is :attr characters');
            }
        } elseif (\is_numeric($value)) {
            if (0 + $value > $attr) {
                $this->addError('The :alias maximum is :attr');
            }
        } elseif (\is_array($value)) {
            if (\reset($value) instanceof File) {
                $attr *= 1024;
                foreach ($value as $file) {
                    if ($file->size() > $attr) {
                        $this->addError('The :alias contains too large a file');
                        return null;
                    }
                }
            } elseif (\count($value) > $attr) {
                $this->addError('The :alias maximum is :attr elements');
            }
        } elseif ($value instanceof File) {
            if ($value->size() > $attr * 1024) {
                $this->addError('The :alias contains too large a file');
                return null;
            }
        } elseif (null !== $value) {
            $this->addError('The :alias maximum is :attr'); //????
            return null;
        }
        return $value;
    }

    protected function vToken(Validator $v, $value, $attr, $args)
    {
        if (! \is_array($args)) {
            $args = [];
        }
        if (
            ! \is_string($value)
            || ! $this->c->Csrf->verify($value, $attr, $args)
        ) {
            $this->addError('Bad token', 'e');
            return null;
        } else {
            return $value;
        }
    }

    protected function vCheckbox(Validator $v, $value)
    {
        return null === $value ? false : (string) $value;
    }

    protected function vReferer(Validator $v, $value, $attr, $args)
    {
        if (! \is_array($args)) {
            $args = [];
        }
        return $this->c->Router->validate($value, $attr, $args);
    }

    protected function vSame(Validator $v, $value, $attr)
    {
        if (
            ! $this->getStatus($attr)
            || $value === $this->__get($attr)
        ) {
            return $value;
        } else {
            $this->addError('The :alias must be same with original');
            return null;
        }
    }

    protected function vRegex(Validator $v, $value, $attr)
    {
        if (
            null !== $value
            && (
                ! \is_string($value)
                || ! \preg_match($attr, $value)
            )
        ) {
            $this->addError('The :alias is not valid format');
            return null;
        } else {
            return $value;
        }
    }

    protected function vPassword(Validator $v, $value)
    {
        return $this->vRegex($v, $value, '%[^\x20][\x20][^\x20]%');
    }

    protected function vIn(Validator $v, $value, $attr)
    {
        if (
            null !== $value
            && ! \in_array($value, \explode(',', $attr))
        ) {
            $this->addError('The :alias contains an invalid value');
        }
        return $value;
    }

    protected function vNotIn(Validator $v, $value, $attr)
    {
        if (
            null !== $value
            && \in_array($value, \explode(',', $attr))
        ) {
            $this->addError('The :alias contains an invalid value');
        }
        return $value;
    }


    protected function vFile(Validator $v, $value, $attr)
    {
        if (null === $value) {
            return null;
        }
        if (! \is_array($value)) {
            $this->addError('The :alias not contains file');
            return null;
        }
        $value = $this->c->Files->upload($value);
        if (null === $value) {
            return null;
        } elseif (false === $value) {
            $this->addError($this->c->Files->error());
            return null;
        } elseif ('multiple' === $attr) {
            if (! \is_array($value)) {
                $value = [$value];
            }
        } elseif (\is_array($value)) {
            $this->addError('The :alias contains more than one file');
            return null;
        }
        return $value;
    }

    protected function vImage(Validator $v, $value, $attr)
    {
        $value = $this->vFile($v, $value, $attr);

        if (\is_array($value)) {
            foreach ($value as $file) {
                if (null === $this->c->Files->isImage($file)) {
                    $this->addError('The :alias not contains image');
                    return null;
                }
            }
        } elseif (
            null !== $value
            && null === $this->c->Files->isImage($value)
        ) {
            $this->addError('The :alias not contains image');
            return null;
        }
        return $value;
    }

    public function vDate(Validator $v, $value)
    {
        if (null === $value) {
            return null;
        } elseif (
            ! \is_string($value)
            || false === \strtotime($value . ' UTC')
        ) {
            $v->addError('The :alias does not contain a date');
            return \is_string($value) ? $value : null;
        }
        return $value;
    }
}
