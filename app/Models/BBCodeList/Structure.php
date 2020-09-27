<?php

namespace ForkBB\Models\BBCodeList;

use ForkBB\Core\Container;
use ForkBB\Models\Model as ParentModel;
use RuntimeException;

class Structure extends ParentModel
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'text handler' => ['text_handler'],
            'text only'    => ['text_only'],
            'tags only'    => ['tags_only'],
            'self nesting' => ['self_nesting'],
            'attrs'        => ['no_attr', 'def_attr'],
        ];
    }

    public function fromString(string $data): Structure
    {
        return $this->setAttrs(\json_decode($data, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function toString(): string
    {

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

    protected function getBBAttr(/* mixed */ $data, array $fields) /* : mixed */
    {
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
                        $value = isset($data[$field]) && true === $data[$field] ? true : null;
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

    protected function setBBAttr(/* mixed */ $data, array $fields) /* : mixed */
    {
        if (
            empty($data['allowed'])
            || $data['allowed'] < 1
        ) {
            return null;
        }

        $result = [];
        foreach ($fields as $field) {
            switch ($field) {
                case 'format':
                case 'body format':
                    $value = isset($data[$field]) && \is_string($data[$field]) ? $data[$field] : null;
                    break;
                case 'required':
                case 'text only':
                    $value = isset($data[$field]) && true === $data[$field] ? true : null;
                    break;
                default:
                    throw new RuntimeException('Unknown attribute property');
            }

            if (isset($value)) {
                $key          = \str_replace(' ', '_', $field);
                $result[$key] = $value;
            }
        }

        return empty($result) ? true : $result;
    }

    protected function getno_attr() /* mixed */
    {
        return $this->getBBAttr($this->attrs['no attr'] ?? null, ['body format', 'text only']);
    }

    protected function setno_attr(array $value): void
    {
        $value = $this->getBBAttr($value, ['body format', 'text only']);
        $attrs = $this->getAttr('attrs');

        if (null === $value) {
            unset($attrs['no attr']);
        } else {
            $attrs['no attr'] = $value;
        }

        $this->setAttr('attrs', $attrs);
    }

    protected function getdef_attr() /* mixed */
    {
        return $this->getBBAttr($this->attrs['Def'] ?? null, ['required', 'format', 'body format', 'text only']);
    }

    protected function setdef_attr(array $value): void
    {
        $value = $this->getBBAttr($value, ['required', 'format', 'body format', 'text only']);
        $attrs = $this->getAttr('attrs');

        if (null === $value) {
            unset($attrs['Def']);
        } else {
            $attrs['Def'] = $value;
        }

        $this->setAttr('attrs', $attrs);
    }
}
