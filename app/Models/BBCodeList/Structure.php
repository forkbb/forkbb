<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;
use Throwable;

class Structure extends Model
{
    const TAG_PATTERN  = '%^(?:ROOT|[a-z\*][a-z\d-]{0,10})$%D';
    const ATTR_PATTERN = '%^[a-z-]{2,15}$%D';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'BBStructure';

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'attrs' => ['no_attr', 'def_attr', 'other_attrs'],
        ];
    }

    public function isInDefault(): bool
    {
        if (empty($this->tag)) {
            return false;
        }

        $bbcode = include $this->c->bbcode->fileDefault;

        foreach ($bbcode as $cur) {
            if ($this->tag === $cur['tag']) {
                return true;
            }
        }

        return false;
    }

    public function setDefault(): Structure
    {
        if (! $this->isInDefault()) {
            throw new RuntimeException("There is no default for the '{$this->tag}' tag");
        }

        $bbcode = include $this->c->bbcode->fileDefault;

        foreach ($bbcode as $cur) {
            if ($this->tag === $cur['tag']) {
                return $this->setModelAttrs($cur);
            }
        }
    }

    public function fromString(string $data): Structure
    {
        return $this->setModelAttrs(\json_decode($data, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function toString(): string
    {
        $a = [
            'tag'     => $this->tag,
            'type'    => $this->type,
            'parents' => $this->parents,
        ];

        if (null !== $this->auto) {
            $a['auto'] = (bool) $this->auto;
        }

        if (null !== $this->self_nesting) {
            $a['self_nesting'] = (int) $this->self_nesting > 0 ? (int) $this->self_nesting : false;
        }

        if (null !== $this->recursive) {
            $a['recursive'] = true;
        }

        if (null !== $this->text_only) {
            $a['text_only'] = true;
        }

        if (null !== $this->tags_only) {
            $a['tags_only'] = true;
        }

        if (null !== $this->single) {
            $a['single'] = true;
        }

        if (null !== $this->pre) {
            $a['pre'] = true;
        }

        if (
            \is_array($this->new_attr)
            && ! empty($this->new_attr['allowed'])
            && ! empty($this->new_attr['name'])
        ) {
            $this->setBBAttr($this->new_attr['name'], $this->new_attr, ['required', 'format', 'body_format', 'text_only']);
        }

        $a['attrs'] = $this->other_attrs;

        if (null !== $this->no_attr) {
            $a['attrs']['No_attr'] = $this->no_attr;
        }

        if (null !== $this->def_attr) {
            $a['attrs']['Def'] = $this->def_attr;
        }

        if (empty($a['attrs'])) {
            unset($a['attrs']);
        }

        if (! empty($this->handler) && \is_string($this->handler)) {
            $a['handler'] = $this->handler;
        }

        if (! empty($this->text_handler) && \is_string($this->text_handler)) {
            $a['text_handler'] = $this->text_handler;
        }

        return \json_encode($a, FORK_JSON_ENCODE);
    }

    protected function gettype(): string
    {
        $type = $this->getModelAttr('type');

        return \is_string($type) ? $type : 'inline';
    }

    protected function getparents(): array
    {
        $parents = $this->getModelAttr('parents');

        if (\is_array($parents)) {
            return $parents;

        } elseif ('inline' === $this->type) {
            return ['inline', 'block'];

        } else {
            return ['block'];
        }
    }

    protected function setrecursive($value): void
    {
        $value = empty($value) ? null : true;

        $this->setModelAttr('recursive', $value);
    }

    protected function settext_only($value): void
    {
        $value = empty($value) ? null : true;

        $this->setModelAttr('text_only', $value);
    }

    protected function settags_only($value): void
    {
        $value = empty($value) ? null : true;

        $this->setModelAttr('tags_only', $value);
    }

    protected function setpre($value): void
    {
        $value = empty($value) ? null : true;

        $this->setModelAttr('pre', $value);
    }

    protected function setsingle($value): void
    {
        $value = empty($value) ? null : true;

        $this->setModelAttr('single', $value);
    }

    protected function getauto(): bool
    {
        $auto = $this->getModelAttr('auto');

        if (\is_bool($auto)) {
            return $auto;

        } elseif ('inline' === $this->type) {
            return true;

        } else {
            return false;
        }
    }

    protected function setauto($value): void
    {
        $value = ! empty($value);

        $this->setModelAttr('auto', $value);
    }

    protected function setself_nesting($value): void
    {
        $value = (int) $value < 1 ? false : (int) $value;

        $this->setModelAttr('self_nesting', $value);
    }

    protected function getBBAttr(string $name, array $fields): mixed
    {
        if (empty($this->attrs[$name])) {
            return null;
        }

        $data = $this->attrs[$name];

        if (true === $data) {
            return true;

        } elseif (! \is_array($data)) {
            return null;

        } else {
            $result = [];

            foreach ($fields as $field) {
                switch ($field) {
                    case 'format':
                    case 'body_format':
                        $value = isset($data[$field]) && \is_string($data[$field]) ? $data[$field] : null;

                        break;
                    case 'required':
                    case 'text_only':
                        $value = isset($data[$field]) ? true : null;

                        break;
                    default:
                        throw new RuntimeException('Unknown attribute property');
                }

                $result[$field] = $value;
            }

            return $result;
        }
    }

    protected function setBBAttr(string $name, mixed $data, array $fields): void
    {
        $attrs = $this->getModelAttr('attrs');

        if (
            empty($data['allowed'])
            || $data['allowed'] < 1
        ) {
            unset($attrs[$name]);

        } else {
            $result = [];

            foreach ($fields as $field) {
                switch ($field) {
                    case 'format':
                    case 'body_format':
                        $value = ! empty($data[$field]) && \is_string($data[$field]) ? $data[$field] : null;

                        break;
                    case 'required':
                    case 'text_only':
                        $value = ! empty($data[$field]) ? true : null;

                        break;
                    default:
                        throw new RuntimeException('Unknown attribute property');
                }

                if (isset($value)) {
                    $result[$field] = $value;
                }
            }

            $attrs[$name] = empty($result) ? true : $result;
        }

        $this->setModelAttr('attrs', $attrs);
    }

    protected function getno_attr(): mixed
    {
        return $this->getBBAttr('No_attr', ['body_format', 'text_only']);
    }

    protected function setno_attr(array $value): void
    {
        $this->setBBAttr('No_attr', $value, ['body_format', 'text_only']);
    }

    protected function getdef_attr(): mixed
    {
        return $this->getBBAttr('Def', ['required', 'format', 'body_format', 'text_only']);
    }

    protected function setdef_attr(array $value): void
    {
        $this->setBBAttr('Def', $value, ['required', 'format', 'body_format', 'text_only']);
    }

    protected function getother_attrs(): array
    {
        $attrs = $this->getModelAttr('attrs');

        if (! \is_array($attrs)) {
            return [];
        }

        unset($attrs['No_attr'], $attrs['Def'], $attrs['New']);

        $result = [];

        foreach ($attrs as $name => $attr) {
            $value = $this->getBBAttr($name, ['required', 'format', 'body_format', 'text_only']);

            if (null !== $value) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    protected function setother_attrs(array $attrs): void
    {
        unset($attrs['No_attr'], $attrs['Def']);

        foreach ($attrs as $name => $attr) {
            $this->setBBAttr($name, $attr, ['required', 'format', 'body_format', 'text_only']);
        }
    }

    /**
     * Ищет ошибку в структуре bb-кода
     */
    public function getError(): ?array
    {
        if (
            ! \is_string($this->tag)
            || ! \preg_match(self::TAG_PATTERN, $this->tag)
        ) {
            return ['Tag name not specified'];
        }

        $result = $this->testPHP($this->handler);

        if (null !== $result) {
            return ['PHP code error in Handler: %s', $result];
        }

        $result = $this->testPHP($this->text_handler);

        if (null !== $result ) {
            return ['PHP code error in Text handler: %s', $result];
        }

        if (
            null !== $this->recursive
            && null !== $this->tags_only
        ) {
            return ['Recursive and Tags only are enabled at the same time'];
        }

        if (
            null !== $this->recursive
            && null !== $this->single
        ) {
            return ['Recursive and Single are enabled at the same time'];
        }

        if (
            null !== $this->text_only
            && null !== $this->tags_only
        ) {
            return ['Text only and Tags only are enabled at the same time'];
        }

        if (\is_array($this->attrs)) {
            foreach ($this->attrs as $name => $attr) {
                if (
                    'No_attr' !== $name
                    && 'Def' !== $name
                    && ! \preg_match(self::ATTR_PATTERN, $name)
                ) {
                    return ['Attribute name %s is not valid', $name];
                }

                if (isset($attr['format'])) {
                    $result = ['Attribute %1$s, %2$s - regular expression error', $name, 'Format'];

                    try {
                        if (
                            ! \is_string($attr['format'])
                            || false === @\preg_match($attr['format'], 'abcdef')
                        ) {
                            return $result;
                        }
                    } catch (Throwable $e) {
                        return $result;
                    }
                }

                if (isset($attr['body_format'])) {
                    $result = ['Attribute %1$s, %2$s - regular expression error', $name, 'Body format'];

                    try {
                        if (
                            ! \is_string($attr['body_format'])
                            || false === @\preg_match($attr['body_format'], 'abcdef')
                        ) {
                            return $result;
                        }
                    } catch (Throwable $e) {
                        return $result;
                    }
                }
            }
        }

        if (
            \is_array($this->new_attr)
            && ! empty($this->new_attr['allowed'])
            && ! empty($this->new_attr['name'])
        ) {
            $name = $this->new_attr['name'];

            if (
                'No_attr' === $name
                || 'Def' === $name
                || isset($this->attrs[$name])
                || ! \preg_match(self::ATTR_PATTERN, $name)
            ) {
                return ['Attribute name %s is not valid', $name];
            }

            if (isset($this->new_attr['format'])) {
                $result = ['Attribute %1$s, %2$s - regular expression error', $name, 'Format'];

                try {
                    if (
                        ! \is_string($this->new_attr['format'])
                        || false === @\preg_match($this->new_attr['format'], 'abcdef')
                    ) {
                        return $result;
                    }
                } catch (Throwable $e) {
                    return $result;
                }
            }

            if (isset($this->new_attr['body_format'])) {
                $result = ['Attribute %1$s, %2$s - regular expression error', $name, 'Body format'];

                try {
                    if (
                        ! \is_string($this->new_attr['body_format'])
                        || false === @\preg_match($this->new_attr['body_format'], 'abcdef')
                    ) {
                        return $result;
                    }
                } catch (Throwable $e) {
                    return $result;
                }
            }
        }

        return null;
    }

    protected function testPHP(?string $code): ?string
    {
        if (
            null === $code
            || '' === $code
        ) {
            return null;
        }

        // тест на парность скобок
        $testCode = \preg_replace('%//[^\r\n]*+|#[^\r\n]*+|/\*.*?\*/|\'.*?(?<!\\\\)\'|".*?(?<!\\\\)"%s', '', $code);

        if (false === \preg_match_all('%[(){}\[\]]%s', $testCode, $matches)) {
            throw new RuntimeException('The preg_match_all() returned an error');
        }

        $round  = 0;
        $square = 0;
        $curly  = 0;

        foreach ($matches[0] as $value) {
            switch ($value) {
                case '(':
                    ++$round;

                    break;
                case ')':
                    --$round;

                    if ($round < 0) {
                        return '\')\' > \'(\'.';
                    }

                    break;
                case '[':
                    ++$square;

                    break;
                case ']':
                    --$square;

                    if ($square < 0) {
                        return '\']\' > \'[\'.';
                    }

                    break;
                case '{':
                    ++$curly;

                    break;
                case '}':
                    --$curly;

                    if ($curly < 0) {
                        return '\'}\' > \'{\'.';
                    }

                    break;
                default:
                    throw new RuntimeException('Unknown bracket type');
            }
        }

        if (0 !== $round) {
            return '\'(\' != \')\'.';
        }

        if (0 !== $square) {
            return '\'[\' != \']\'.';
        }

        if (0 !== $curly) {
            return '\'{\' != \'}\'.';
        }

        // тест на выполнение DANGER! DANGER! DANGER! O_o
        $testCode = "\$testVar = function (\$body, \$attrs, \$parser) { {$code} };\nreturn true;";

        try {
            $result = @eval($testCode);

            if (true !== $result) {
                $error   = \error_get_last();
                $message = $error['message'] ?? 'Unknown error';
                $line    = $error['line'] ?? '';

                return "{$message}: [$line]";
            }
        } catch (Throwable $e) {
            return "{$e->getMessage()}: [{$e->getLine()}]";
        }

        return null;
    }
}
