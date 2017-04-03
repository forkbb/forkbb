<?php

namespace ForkBB\Core;

use R2\Templating\Dirk;
use ForkBB\Models\Pages\Page;
use RuntimeException;

class View extends Dirk
{
    public function __construct ($cache, $views)
    {
        $config = [
            'views'     => $views,
            'cache'     => $cache,
            'ext'       => '.tpl',
            'echo'      => 'htmlspecialchars(%s, ENT_HTML5 | ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')',
            'separator' => '/',
        ];
        $this->compilers[] = 'Transformations';

        parent::__construct($config);
    }

    /**
     * Трансформация скомпилированного шаблона
     * @param string $value
     * @return string
     */
    protected function compileTransformations($value)
    {
        if (strpos($value, '<!--inline-->') === false) {
            return $value;
        }
        return preg_replace_callback(
            '%<!--inline-->([^<]*(?:<(?!!--endinline-->)[^<]*)*+)(?:<!--endinline-->)?%',
            function ($matches) {
                return preg_replace('%\h*\R\s*%', '', $matches[1]);
            },
            $value);
    }

    public function rendering(Page $page)
    {
        if (! $page->isReady()) {
            throw new RuntimeException('The page model does not contain data ready');
        }

        $headers = $page->httpHeaders();
        foreach ($headers as $header) {
            header($header);
        }

        $tpl = $page->getNameTpl();
        // переадресация
        if (null === $tpl) {
            return null;
        }

        return $this->fetch($tpl, $page->getData());
    }
}
