<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */
/**
 * based on Dirk <https://github.com/artoodetoo/dirk>
 *
 * @copyright (c) 2015 artoodetoo <i.am@artoodetoo.org, https://github.com/artoodetoo>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\View;

use RuntimeException;

class Compiler
{
    protected string $shortID;
    protected int    $loopsCounter = 0;
    protected array  $compilers = [
        'PrePaste',
        'Statements',
        'Comments',
        'Echos',
        'Transformations',
    ];
    protected array  $preArray = [];
    protected string $tplName;

    public function __construct(string $preFile)
    {
        if (
            ! empty($preFile)
            && \is_file($preFile)
        ) {
            $this->preArray = include $preFile;
        }
    }

    /**
     * Генерирует php код на основе шаблона из $text
     */
    public function create(string $name, string $text, string $hash): string
    {
        $this->shortID = $hash;
        $this->tplName = $name;

        foreach ($this->compilers as $type) {
            $text = $this->{'compile' . $type}($text);
        }

        return $text;
    }

    /**
     * Обрабатывает предварительную подстановку кода в шаблон
     */
    protected function compilePrePaste(string $value): string
    {
        $pre = $this->preArray[$this->tplName] ?? null;

        return \preg_replace_callback(
            '%[ \t]*+<!-- PRE (\w+) -->[ \t]*\r?\n?%',
            function($match) use ($pre) {
                if (isset($pre[$match[1]])) {
                    return \rtrim($pre[$match[1]]) . "\n";
                } else {
                    return '';
                }
            },
            $value
        );
    }

    /**
     * Обрабатывает операторы начинающиеся с @
     */
    protected function compileStatements(string $value): string
    {
        return \preg_replace_callback(
            '%[ \t]*+\B@(\w+)(?: [ \t]*( \( ( (?>[^()]+) | (?2) )* \) ) )?%x',
            function($match) {
                if (\method_exists($this, $method = 'compile' . \ucfirst($match[1]))) {
                    return $this->$method($match[2] ?? '');
                } else {
                    return $match[0];
                }
            },
            $value
        );
    }

    /**
     * Обрабатывает комментарии
     */
    protected function compileComments(string $value): string
    {
        return \preg_replace('%\{\{--(.*?)--\}\}%s', '<?php /*$1*/ ?>', $value);
    }

    /**
     * Обрабатывает вывод информации
     */
    protected function compileEchos(string $value): string
    {
        // {{! !}}
        $value = \preg_replace_callback(
            '%(@)?\{\{!\s*(.+?)\s*!\}\}(\r?\n)?%s',
            function($matches) {
                $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

                return $matches[1]
                    ? \substr($matches[0], 1)
                    : '<?= \\htmlspecialchars((string) '
                        . $this->compileEchoDefaults($matches[2])
                        . ', \\ENT_HTML5 | \\ENT_QUOTES | \\ENT_SUBSTITUTE, \'UTF-8\', false) ?>'
                        . $whitespace;
            },
            $value
        );

        // {!! !!}
        $value = \preg_replace_callback(
            '%\{\!!\s*(.+?)\s*!!\}(\r?\n)?%s',
            function($matches) {
                $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];

                return '<?= '
                    . $this->compileEchoDefaults($matches[1])
                    . ' ?>'
                    . $whitespace;
            },
            $value
        );

        // {{ }}
        $value = \preg_replace_callback(
            '%(@)?\{\{\s*(.+?)\s*\}\}(\r?\n)?%s',
            function($matches) {
                $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

                return $matches[1]
                    ? \substr($matches[0], 1)
                    : '<?= \\htmlspecialchars((string) '
                        . $this->compileEchoDefaults($matches[2])
                        . ', \\ENT_HTML5 | \\ENT_QUOTES | \\ENT_SUBSTITUTE, \'UTF-8\') ?>'
                        . $whitespace;
            },
            $value
        );

        return $value;
    }

    /**
     * Трансформирует скомпилированный шаблон
     */
    protected function compileTransformations(string $value): string
    {
        if (\str_starts_with($value, '<?xml ')) {
            $value = \str_replace(' \\ENT_HTML5 | \\ENT_QUOTES | \\ENT_SUBSTITUTE,', ' \\ENT_XML1,', $value);
        }

        $perfix = <<<'EOD'
<?php

declare(strict_types=1);

use function \ForkBB\{__, num, dt, size};

?>
EOD;

        if (false === \strpos($value, '<!-- inline -->')) {
            return $perfix . $value;
        }

        return $perfix . \preg_replace_callback(
            '%<!-- inline -->([^<]*(?:<(?!!-- endinline -->)[^<]*)*+)(?:<!-- endinline -->)?%',
            function ($matches) {
                return \preg_replace('%\h*\R\s*%', '', $matches[1]);
            },
            $value
        );
    }

    /**
     * Обрабатывает значение по умолчанию для вывода информации
     */
    public function compileEchoDefaults(string $value): string
    {
        return \preg_replace('%^(?=\$)(.+?)(?:\s+or\s+)(.+?)$%s', '($1 ?? $2)', $value);
    }

    /**
     * @if()
     */
    protected function compileIf(string $expression): string
    {
        if (\preg_match('%^\(\s*(\!\s*)?(\$[\w>-]+\[(?:\w+|[\'"]\w+[\'"])\])\s*\)$%', $expression, $matches)) {
            if (empty($matches[1])) {
                return "<?php if (! empty{$expression}): ?>";
            } else {
                return "<?php if (empty({$matches[2]})): ?>";
            }
        } else {
            return "<?php if {$expression}: ?>";
        }
    }

    /**
     * @elseif()
     */
    protected function compileElseif(string $expression): string
    {
        return "<?php elseif {$expression}: ?>";
    }

    /**
     * @else
     */
    protected function compileElse(): string
    {
        return "<?php else: ?>";
    }

    /**
     * @endif
     */
    protected function compileEndif(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * @isset()
     */
    protected function compileIsset(string $expression): string
    {
        return "<?php if (isset{$expression}): ?>";
    }

    /**
     * @endisset
     */
    protected function compileEndisset(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * @endempty
     */
    protected function compileEndempty(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * @unless()
     */
    protected function compileUnless(string $expression): string
    {
        return "<?php if (! $expression): ?>";
    }

    /**
     * @endunless
     */
    protected function compileEndunless(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * @for()
     */
    protected function compileFor(string $expression): string
    {
        return "<?php for {$expression}: ?>";
    }

    /**
     * @endfor
     */
    protected function compileEndfor(): string
    {
        return "<?php endfor; ?>";
    }

    /**
     * @foreach()
     */
    protected function compileForeach(string $expression): string
    {
        ++$this->loopsCounter;

        return "<?php \$__iter{$this->shortID}_{$this->loopsCounter} = 0; "
             . "foreach {$expression}: "
             . "++\$__iter{$this->shortID}_{$this->loopsCounter}; ?>";
    }

    /**
     * @endforeach
     */
    protected function compileEndforeach(): string
    {
        --$this->loopsCounter;

        return "<?php endforeach; ?>";
    }

    /**
     * @iteration
     */
    protected function compileIteration(): string
    {
        return "((int) \$__iter{$this->shortID}_{$this->loopsCounter})";
    }

    /**
     * @forelse()
     */
    protected function compileForelse(string $expression): string
    {
        ++$this->loopsCounter;

        return "<?php \$__iter{$this->shortID}_{$this->loopsCounter} = 0; "
             . "foreach {$expression}: "
             . "++\$__iter{$this->shortID}_{$this->loopsCounter}; ?>";
    }

    /**
     * @empty / @empty()
     */
    protected function compileEmpty(string $expression): string
    {
        if (
            isset($expression[0])
            && '(' == $expression[0]
        ) {
            return "<?php if (empty{$expression}): ?>";
        } else {
            $s = "<?php endforeach; if (0 === \$__iter{$this->shortID}_{$this->loopsCounter}): ?>";

            --$this->loopsCounter;

            return $s;
        }
    }

    /**
     * @endforelse
     */
    protected function compileEndforelse(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * @while()
     */
    protected function compileWhile(string $expression): string
    {
        return "<?php while {$expression}: ?>";
    }

    /**
     * @endwhile
     */
    protected function compileEndwhile(): string
    {
        return "<?php endwhile; ?>";
    }

    /**
     * @extends()
     */
    protected function compileExtends(string $expression): string
    {
        if (
            isset($expression[0])
            && '(' == $expression[0]
        ) {
            $expression = \substr($expression, 1, -1);
        }

        return "<?php \$this->extend({$expression}); ?>";
    }

    /**
     * @include()
     */
    protected function compileInclude(string $expression): string
    {
        if (
            isset($expression[0])
            && '(' == $expression[0]
        ) {
            $expression = \substr($expression, 1, -1);
        }

        return "<?php include \$this->prepare({$expression}); ?>";
    }

    /**
     * @yield()
     */
    protected function compileYield(string $expression): string
    {
        return "<?= \$this->block{$expression}; ?>";
    }

    /**
     * @section()
     */
    protected function compileSection(string $expression): string
    {
        return "<?php \$this->beginBlock{$expression}; ?>";
    }

    /**
     * @endsection
     */
    protected function compileEndsection(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * @show()
     */
    protected function compileShow(): string
    {
        return "<?= \$this->block(\$this->endBlock()); ?>";
    }

    /**
     * @append
     */
    protected function compileAppend(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * @stop
     */
    protected function compileStop(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * @overwrite
     */
    protected function compileOverwrite(): string
    {
        return "<?php \$this->endBlock(true); ?>";
    }

    /**
     * @switch()
     */
    protected function compileSwitch(string $expression): string
    {
        return "<?php switch {$expression}: ?>";
    }

    /**
     * @case()
     */
    protected function compileCase(string $expression): string
    {
        $expression = \substr($expression, 1, -1);

        return "<?php case {$expression}: ?>";
    }

    /**
     * @default
     */
    protected function compileDefault(): string
    {
        return "<?php default: ?>";
    }

    /**
     * @endswitch
     */
    protected function compileEndswitch(): string
    {
        return "<?php endswitch; ?>";
    }

    /**
     * @break
     */
    protected function compileBreak(): string
    {
        return "<?php break; ?>";
    }
}
