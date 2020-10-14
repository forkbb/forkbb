<?php

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Core\Container;
use ForkBB\Models\Model as ParentModel;
use RuntimeException;
use Throwable;

class Structure extends ParentModel
{
    const TAG_PATTERN  = '%^(?:ROOT|[a-z\*][a-z\d-]{0,10})$%D';
    const ATTR_PATTERN = '%^[a-z-]{2,15}$%D';

    const JSON_OPTIONS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'text handler' => ['text_handler'],
            'text only'    => ['text_only'],
            'tags only'    => ['tags_only'],
            'self nesting' => ['self_nesting'],
            'attrs'        => ['no_attr', 'def_attr', 'other_attrs'],
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
                return $this->setAttrs($cur);
            }
        }
    }

    public function fromString(string $data): Structure
    {
        return $this->setAttrs(\json_decode($data, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function toString(): string
    {
        $a = [
            'tag'     => $this->tag,
            'type'    => $this->type,
            'parents' => $this->parents,
        ];

        if (! empty($this->handler) && \is_string($this->handler)) {
            $a['handler'] = $this->handler;
        }

        if (! empty($this->text_handler) && \is_string($this->text_handler)) {
            $a['text handler'] = $this->text_handler;
        }

        if (null !== $this->auto) {
            $a['auto'] = (bool) $this->auto;
        }

        if (null !== $this->self_nesting) {
            $a['self nesting'] = (int) $this->self_nesting > 0 ? (int) $this->self_nesting : false;
        }

        if (null !== $this->recursive) {
            $a['recursive'] = true;
        }

        if (null !== $this->text_only) {
            $a['text only'] = true;
        }

        if (null !== $this->tags_only) {
            $a['tags only'] = true;
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
            $this->setBBAttr($this->new_attr['name'], $this->new_attr, ['required', 'format', 'body format', 'text only']);
        }

        $a['attrs'] = $this->other_attrs;

        if (null !== $this->no_attr) {
            $a['attrs']['no attr'] = $this->no_attr;
        }

        if (null !== $this->def_attr) {
            $a['attrs']['Def'] = $this->def_attr;
        }

        if (empty($a['attrs'])) {
            unset($a['attrs']);
        }

        return \json_encode($a, self::JSON_OPTIONS);
    }

    protected function gettype(): string
    {
        $type = $this->getAttr('type');

        return \is_string($type) ? $type : 'inline';
    }

    protected function getparents(): array
    {
        $parents = $this->getAttr('parents');

        if (\is_array($parents)) {
            return $parents;
        } elseif ('inline' === $this->type) {
            return ['inline', 'block'];
        } else {
            return ['block'];
        }
    }

    protected function gettext_handler(): ?string
    {
        return $this->getAttr('text handler');
    }

    protected function settext_handler(?string $value): void
    {
        $this->setAttr('text handler', $value);
    }

    protected function setrecursive($value): void
    {
        $value = empty($value) ? null : true;
        $this->setAttr('recursive', $value);
    }

    protected function gettext_only(): ?bool
    {
        return $this->getAttr('text only');
    }

    protected function settext_only($value): void
    {
        $value = empty($value) ? null : true;
        $this->setAttr('text only', $value);
    }

    protected function gettags_only(): ?bool
    {
        return $this->getAttr('tags only');
    }

    protected function settags_only($value): void
    {
        $value = empty($value) ? null : true;
        $this->setAttr('tags only', $value);
    }

    protected function setpre($value): void
    {
        $value = empty($value) ? null : true;
        $this->setAttr('pre', $value);
    }

    protected function setsingle($value): void
    {
        $value = empty($value) ? null : true;
        $this->setAttr('single', $value);
    }

    protected function getauto(): bool
    {
        $auto = $this->getAttr('auto');

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
        $this->setAttr('auto', $value);
    }

    protected function getself_nesting() /* : mixed */
    {
        return $this->getAttr('self nesting');
    }

    protected function setself_nesting($value): void
    {
        $value = $value < 1 ? false : (int) $value;
        $this->setAttr('self nesting', $value);
    }

    protected function getBBAttr(string $name, array $fields) /* : mixed */
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
                    case 'body format':
                        $value = isset($data[$field]) && \is_string($data[$field]) ? $data[$field] : null;
                        break;
                    case 'required':
                    case 'text only':
                        $value = isset($data[$field]) ? true : null;
                        break;
                    default:
                        throw new RuntimeException('Unknown attribute property');
                }

                $key          = \str_replace(' ', '_', $field);
                $result[$key] = $value;
            }

            return $result;
        }
    }

    protected function setBBAttr(string $name, /* mixed */ $data, array $fields): void
    {
        $attrs = $this->getAttr('attrs');

        if (
            empty($data['allowed'])
            || $data['allowed'] < 1
        ) {
            unset($attrs[$name]);
        } else {
            $result = [];
            foreach ($fields as $field) {
                $key = \str_replace(' ', '_', $field);

                switch ($field) {
                    case 'format':
                    case 'body format':
                        $value = ! empty($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
                        break;
                    case 'required':
                    case 'text only':
                        $value = ! empty($data[$key]) ? true : null;
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

        $this->setAttr('attrs', $attrs);
    }

    protected function getno_attr() /* : mixed */
    {
        return $this->getBBAttr('no attr', ['body format', 'text only']);
    }

    protected function setno_attr(array $value): void
    {
        $this->setBBAttr('no attr', $value, ['body format', 'text only']);
    }

    protected function getdef_attr() /* : mixed */
    {
        return $this->getBBAttr('Def', ['required', 'format', 'body format', 'text only']);
    }

    protected function setdef_attr(array $value): void
    {
        $this->setBBAttr('Def', $value, ['required', 'format', 'body format', 'text only']);
    }

    protected function getother_attrs(): array
    {
        $attrs = $this->getAttr('attrs');

        if (! \is_array($attrs)) {
            return [];
        }

        unset($attrs['no attr'], $attrs['Def'], $attrs['New']);

        $result = [];
        foreach ($attrs as $name => $attr) {
            $value = $this->getBBAttr($name, ['required', 'format', 'body format', 'text only']);

            if (null === $value) {
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    protected function setother_attrs(array $attrs): void
    {
        unset($attrs['no attr'], $attrs['Def']);

        foreach ($attrs as $name => $attr) {
            $this->setBBAttr($name, $attr, ['required', 'format', 'body format', 'text only']);
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
                    'no attr' !== $name
                    && 'Def' !== $name
                    && ! preg_match(self::ATTR_PATTERN, $name)
                ) {
                    return ['Attribute name %s is not valid', $name];
                }

                if (isset($attr['format'])) {
                    if (
                        ! \is_string($attr['format'])
                        || false === @\preg_match($attr['format'], 'abcdef')
                    ) {
                        return ['Attribute %1$s, %2$s - regular expression error', $name, 'Format'];
                    }
                }

                if (isset($attr['body format'])) {
                    if (
                        ! \is_string($attr['body format'])
                        || false === @\preg_match($attr['body format'], 'abcdef')
                    ) {
                        return ['Attribute %1$s, %2$s - regular expression error', $name, 'Body format'];
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
                'no attr' === $name
                || 'Def' === $name
                || isset($this->attrs[$name])
                || ! preg_match(self::ATTR_PATTERN, $name)
            ) {
                return ['Attribute name %s is not valid', $name];
            }

            if (isset($this->new_attr['format'])) {
                if (
                    ! \is_string($this->new_attr['format'])
                    || false === @\preg_match($this->new_attr['format'], 'abcdef')
                ) {
                    return ['Attribute %1$s, %2$s - regular expression error', $name, 'Format'];
                }
            }

            if (isset($this->new_attr['body format'])) {
                if (
                    ! \is_string($this->new_attr['body format'])
                    || false === @\preg_match($this->new_attr['body format'], 'abcdef')
                ) {
                    return ['Attribute %1$s, %2$s - regular expression error', $name, 'Body format'];
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
        $testCode = "\$testVar = function(\$body, \$attrs, \$parser) { {$code} };\nreturn true;";

        try {
            $result = @eval($testCode);

            if (true !== $result) {
                $error = \error_get_last();
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
