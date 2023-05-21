<?php

declare(strict_types=1);

namespace R2\Templating;

use R2\Templating\PhpEngine;

class Dirk extends PhpEngine
{

    protected $cache;
    protected $echoFormat;

    public function __construct(array $config = [])
    {
        $config = \array_replace_recursive(
            [
                'ext'   => '.blade.php',
                'cache' => '.',
                'echo'  => '\\htmlspecialchars((string) %s, \\ENT_QUOTES, \'UTF-8\')',
            ],
            $config
        );
        $this->cache      = $config['cache'] ?? '.';
        $this->echoFormat = $config['echo']  ?? '%s';
        parent::__construct($config);
    }

    protected $compilers = [
        'Statements',
        'Comments',
        'Echos',
    ];

    protected $shortID = '';
    protected $shortArr = [];

    /**
     * Prepare file to include
     * @param  string $name
     * @return string
     */
    protected function prepare(string $name): string
    {
        $name = \str_replace('.', '/', $name);
        $tpl  = $this->views . '/' . $name . $this->ext;
        $sha1 = \sha1($name);
        $php  = $this->cache . '/' . $sha1 . '.php';

        if (
            ! \file_exists($php)
            || \filemtime($tpl) > \filemtime($php)
        ) {
            $this->shortArr[] = $this->shortID;
            $this->shortID    = \substr($sha1, 0, 4);

            $text = \file_get_contents($tpl);

            foreach ($this->compilers as $type) {
                $text = $this->{'compile' . $type}($text);
            }

            \file_put_contents($php, $text);

            $this->shortID    = \array_pop($this->shortArr);
        }

        return $php;
    }

    /**
     * Compile Statements that start with "@"
     *
     * @param  string  $value
     * @return string
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
     * Compile comments
     *
     * @param  string  $value
     * @return string
     */
    protected function compileComments(string $value): string
    {
        return \preg_replace('%\{\{--(.*?)--\}\}%s', '<?php /*$1*/ ?>', $value);
    }

    /**
     * Compile echos
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEchos(string $value): string
    {
        // compile escaped echoes
        $value = \preg_replace_callback(
            '%\{\{\{\s*(.+?)\s*\}\}\}(\r?\n)?%s',
            function($matches) {
                $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];

                return '<?= \\htmlspecialchars('
                    . $this->compileEchoDefaults($matches[1])
                    . ', \\ENT_QUOTES, \'UTF-8\') ?>'
                    . $whitespace;
            },
            $value
        );

        // compile not escaped echoes
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

        // compile regular echoes
        $value = \preg_replace_callback(
            '%(@)?\{\{\s*(.+?)\s*\}\}(\r?\n)?%s',
            function($matches) {
                $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

                return $matches[1]
                    ? \substr($matches[0], 1)
                    : '<?= '
                        . \sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2]))
                        . ' ?>' . $whitespace;
            },
            $value
        );

        return $value;
    }

    /**
     * Compile the default values for the echo statement.
     *
     * @param  string  $value
     * @return string
     */
    public function compileEchoDefaults(string $value): string
    {
        return \preg_replace('%^(?=\$)(.+?)(?:\s+or\s+)(.+?)$%s', '($1 ?? $2)', $value);
    }

    /**
     * Compile the if statements
     *
     * @param  string  $expression
     * @return string
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
     * Compile the else-if statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElseif(string $expression): string
    {
        return "<?php elseif {$expression}: ?>";
    }

    /**
     * Compile the else statements
     *
     * @return string
     */
    protected function compileElse(): string
    {
        return "<?php else: ?>";
    }

    /**
     * Compile the end-if statements
     *
     * @return string
     */
    protected function compileEndif(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the isset statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileIsset(string $expression): string
    {
        return "<?php if (isset{$expression}): ?>";
    }

    /**
     * Compile the end-isset statements
     *
     * @return string
     */
    protected function compileEndisset(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the end-empty statements
     *
     * @return string
     */
    protected function compileEndempty(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the unless statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnless(string $expression): string
    {
        return "<?php if (! $expression): ?>";
    }

    /**
     * Compile the end unless statements
     *
     * @return string
     */
    protected function compileEndunless(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the for statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileFor(string $expression): string
    {
        return "<?php for {$expression}: ?>";
    }

    /**
     * Compile the end-for statements
     *
     * @return string
     */
    protected function compileEndfor(): string
    {
        return "<?php endfor; ?>";
    }

    protected $loopsCounter = 0;

    /**
     * Compile the foreach statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForeach(string $expression): string
    {
        ++$this->loopsCounter;

        return "<?php \$__iter{$this->shortID}_{$this->loopsCounter} = 0; "
             . "foreach {$expression}: "
             . "++\$__iter{$this->shortID}_{$this->loopsCounter}; ?>";
    }

    /**
     * Compile the end-for-each statements
     *
     * @return string
     */
    protected function compileEndforeach(): string
    {
        --$this->loopsCounter;

        return "<?php endforeach; ?>";
    }

    protected function compileIteration(): string
    {
        return "((int) \$__iter{$this->shortID}_{$this->loopsCounter})";
    }

    /**
     * Compile the forelse statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForelse(string $expression): string
    {
        ++$this->loopsCounter;

        return "<?php \$__iter{$this->shortID}_{$this->loopsCounter} = 0; "
             . "foreach {$expression}: "
             . "++\$__iter{$this->shortID}_{$this->loopsCounter}; ?>";
    }

    /**
     * Compile the end-forelse statements
     * Compile the empty statements
     *
     * @param  string  $expression
     * @return string
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
     * Compile the end-forelse statements
     *
     * @return string
     */
    protected function compileEndforelse(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the while statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileWhile(string $expression): string
    {
        return "<?php while {$expression}: ?>";
    }

    /**
     * Compile the end-while statements
     *
     * @return string
     */
    protected function compileEndwhile(): string
    {
        return "<?php endwhile; ?>";
    }

    /**
     * Compile the extends statements
     *
     * @param  string  $expression
     * @return string
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
     * Compile the include statements
     *
     * @param  string  $expression
     * @return string
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
     * Compile the yield statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileYield(string $expression): string
    {
        return "<?= \$this->block{$expression}; ?>";
    }

    /**
     * Compile the section statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSection(string $expression): string
    {
        return "<?php \$this->beginBlock{$expression}; ?>";
    }

    /**
     * Compile the end-section statements
     *
     * @return string
     */
    protected function compileEndsection(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * Compile the show statements
     *
     * @return string
     */
    protected function compileShow(): string
    {
        return "<?= \$this->block(\$this->endBlock()); ?>";
    }

    /**
     * Compile the append statements
     *
     * @return string
     */
    protected function compileAppend(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * Compile the stop statements
     *
     * @return string
     */
    protected function compileStop(): string
    {
        return "<?php \$this->endBlock(); ?>";
    }

    /**
     * Compile the overwrite statements
     *
     * @return string
     */
    protected function compileOverwrite(): string
    {
        return "<?php \$this->endBlock(true); ?>";
    }

    protected function compileSwitch(string $expression): string
    {
        return "<?php switch {$expression}: ?>";
    }

    protected function compileCase(string $expression): string
    {
        $expression = \substr($expression, 1, -1);

        return "<?php case {$expression}: ?>";
    }

    protected function compileDefault(): string
    {
        return "<?php default: ?>";
    }

    protected function compileEndswitch(): string
    {
        return "<?php endswitch; ?>";
    }

    protected function compileBreak(): string
    {
        return "<?php break; ?>";
    }
}
