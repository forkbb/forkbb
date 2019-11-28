<?php

namespace R2\Templating;

use R2\Templating\PhpEngine;

class Dirk extends PhpEngine
{

    protected $cache;
    protected $echoFormat;

    public function __construct(array $config = [])
    {
        $config = array_replace_recursive(
            [
                'ext'   => '.blade.php',
                'cache' => '.',
                'echo'  => 'htmlspecialchars(%s, ENT_QUOTES, \'UTF-8\')',
            ],
            $config
        );
        $this->cache      = isset($config['cache']) ? $config['cache'] : '.';
        $this->echoFormat = isset($config['echo'])  ? $config['echo']  : '%s';
        parent::__construct($config);
    }

    protected $compilers = array(
        'Statements',
        'Comments',
        'Echos'
    );

    /**
     * Prepare file to include
     * @param  string $name
     * @return string
     */
    protected function prepare($name)
    {
        $name = str_replace('.', '/', $name);
        $tpl = $this->views . '/' . $name . $this->ext;
        $php = $this->cache . '/' . md5($name) . '.php';
        if (!file_exists($php) || filemtime($tpl) > filemtime($php)) {
            $text = file_get_contents($tpl);
            foreach ($this->compilers as $type) {
                $text = $this->{'compile' . $type}($text);
            }
            file_put_contents($php, $text);
        }
        return $php;
    }

    /**
     * Compile Statements that start with "@"
     *
     * @param  string  $value
     * @return mixed
     */
    protected function compileStatements($value)
    {
        return preg_replace_callback(
            '/\B@(\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x',
            function($match) {
                if (method_exists($this, $method = 'compile' . ucfirst($match[1]))) {
                    $match[0] = $this->$method(isset($match[3]) ? $match[3] : '');
                }
                return isset($match[3]) ? $match[0] : $match[0] . $match[2];
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
    protected function compileComments($value)
    {
        $pattern = '/\{\{--((.|\s)*?)--\}\}/';

        return preg_replace($pattern, '<?php /*$1*/ ?>', $value);
    }

    /**
     * Compile echos
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEchos($value)
    {
        // compile escaped echoes
        $value = preg_replace_callback(
            '/\{\{\{\s*(.+?)\s*\}\}\}(\r?\n)?/s',
            function($matches) {
                $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];
                return '<?= htmlspecialchars('
                    .$this->compileEchoDefaults($matches[1])
                    .', ENT_QUOTES, \'UTF-8\') ?>'
                    .$whitespace;
            },
            $value
        );

        // compile not escaped echoes
        $value = preg_replace_callback(
            '/\{\!!\s*(.+?)\s*!!\}(\r?\n)?/s',
            function($matches) {
                $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];
                return '<?= '.$this->compileEchoDefaults($matches[1]).' ?>'.$whitespace;
            },
            $value
        );

        // compile regular echoes
        $value = preg_replace_callback(
            '/(@)?\{\{\s*(.+?)\s*\}\}(\r?\n)?/s',
            function($matches) {
                $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];
                return $matches[1]
                    ? substr($matches[0], 1)
                    : '<?= '
                      .sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2]))
                      .' ?>'.$whitespace;
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
    public function compileEchoDefaults($value)
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $value);
    }

    /**
     * Compile the if statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileIf($expression)
    {
        return "<?php if{$expression}: ?>";
    }

    /**
     * Compile the else-if statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElseif($expression)
    {
        return "<?php elseif{$expression}: ?>";
    }

    /**
     * Compile the else statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElse($expression)
    {
        return "<?php else: ?>";
    }

    /**
     * Compile the end-if statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndif($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the unless statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnless($expression)
    {
        return "<?php if(!$expression): ?>";
    }

    /**
     * Compile the end unless statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndunless($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the for statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileFor($expression)
    {
        return "<?php for{$expression}: ?>";
    }

    /**
     * Compile the end-for statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndfor($expression)
    {
        return "<?php endfor; ?>";
    }

    /**
     * Compile the foreach statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForeach($expression)
    {
        return "<?php foreach{$expression}: ?>";
    }

    /**
     * Compile the end-for-each statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndforeach($expression)
    {
        return "<?php endforeach; ?>";
    }

    protected $emptyCounter = 0;
    /**
     * Compile the forelse statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForelse($expression)
    {
        $this->emptyCounter++;
        return "<?php \$__empty_{$this->emptyCounter} = true; "
              ."foreach{$expression}: "
              ."\$__empty_{$this->emptyCounter} = false;?>";
    }

    /**
     * Compile the end-forelse statements
     *
     * @return string
     */
    protected function compileEmpty()
    {
        $s = "<?php endforeach; if (\$__empty_{$this->emptyCounter}): ?>";
        $this->emptyCounter--;
        return $s;
    }

    /**
     * Compile the end-forelse statements
     *
     * @return string
     */
    protected function compileEndforelse()
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the while statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileWhile($expression)
    {
        return "<?php while{$expression}: ?>";
    }

    /**
     * Compile the end-while statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndwhile($expression)
    {
        return "<?php endwhile; ?>";
    }

    /**
     * Compile the extends statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileExtends($expression)
    {
        if (isset($expression[0]) && $expression[0] == '(') {
            $expression = substr($expression, 1, -1);
        }
        return "<?php \$this->extend({$expression}) ?>";
    }

    /**
     * Compile the include statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileInclude($expression)
    {
        if (isset($expression[0]) && $expression[0] == '(') {
            $expression = substr($expression, 1, -1);
        }
        return "<?php include \$this->prepare({$expression}) ?>";
    }

    /**
     * Compile the yield statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileYield($expression)
    {
        return "<?= \$this->block{$expression} ?>";
    }

    /**
     * Compile the section statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSection($expression)
    {
        return "<?php \$this->beginBlock{$expression} ?>";
    }

    /**
     * Compile the end-section statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndsection($expression)
    {
        return "<?php \$this->endBlock() ?>";
    }

    /**
     * Compile the show statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileShow($expression)
    {
        return "<?= \$this->block(\$this->endBlock()) ?>";
    }

    /**
     * Compile the append statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileAppend($expression)
    {
        return "<?php \$this->endBlock() ?>";
    }

    /**
     * Compile the stop statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStop($expression)
    {
        return "<?php \$this->endBlock() ?>";
    }

    /**
     * Compile the overwrite statements
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileOverwrite($expression)
    {
        return "<?php \$this->endBlock(true) ?>";
    }
}
