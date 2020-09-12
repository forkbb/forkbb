<?php

namespace ForkBB\Core;

use R2\Templating\Dirk;
use ForkBB\Models\Page;
use RuntimeException;

class View extends Dirk
{
    public function __construct ($cache, $views)
    {
        $config = [
            'views'     => $views,
            'cache'     => $cache,
            'ext'       => '.forkbb.php',
            'echo'      => '\\htmlspecialchars(%s, \\ENT_HTML5 | \\ENT_QUOTES | \\ENT_SUBSTITUTE, \'UTF-8\')',
            'separator' => '/',
        ];
        $this->compilers[] = 'Transformations';

        parent::__construct($config);
    }

    /**
     * Compile Statements that start with "@"
     */
    protected function compileStatements(/* string */ $value) /* : mixed */
    {
        return \preg_replace_callback(
            '/[ \t]*+\B@(\w+)(?: [ \t]*( \( ( (?>[^()]+) | (?2) )* \) ) )?/x',
            function($match) {
                if (\method_exists($this, $method = 'compile' . \ucfirst($match[1]))) {
                    return isset($match[2]) ? $this->$method($match[2]) : $this->$method('');
                } else {
                    return $match[0];
                }
            },
            $value
        );
    }

    /**
     * Трансформация скомпилированного шаблона
     */
    protected function compileTransformations(/* string */ $value) /* string */
    {
        if ('<?xml ' === \substr($value, 0, 6)) {
            $value = \str_replace(' \\ENT_HTML5 | \\ENT_QUOTES | \\ENT_SUBSTITUTE,', ' \\ENT_XML1,', $value);
        }

        $perfix = <<<'EOD'
<?php

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
     * Return result of templating
     */
    public function rendering(Page $p): ?string
    {
        foreach ($p->httpHeaders as $catHeader) {
            foreach ($catHeader as $header) {
                \header($header[0], $header[1]);
            }
        }

        if (null === $p->nameTpl) {
            return null;
        }

        $p->prepare();

        $this->templates[] = $p->nameTpl;
        while ($_name = \array_shift($this->templates)) {
            $this->beginBlock('content');
            foreach ($this->composers as $_cname => $_cdata) {
                if (\preg_match($_cname, $_name)) {
                    foreach ($_cdata as $_citem) {
                        \extract((\is_callable($_citem) ? $_citem($this) : $_citem) ?: []);
                    }
                }
            }
            require($this->prepare($_name));
            $this->endBlock(true);
        }

        return $this->block('content');
    }

    /**
     * Compile the if statements
     */
    protected function compileIf(/* string */ $expression) /* : string */
    {
        if (\preg_match('%^\(\s*(\!\s*)?(\$[\w>-]+\[(?:[\'"]\w+[\'"]|\d+)\])\s*\)$%', $expression, $matches)) {
            if (empty($matches[1])) {
                return "<?php if(! empty{$expression}): ?>";
            } else {
                return "<?php if(empty({$matches[2]})): ?>";
            }
        } else {
            return parent::compileIf($expression);
        }
    }
}
