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

class BBCodeList extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'BBCodeList';

    public function __construct(string $file, Container $container)
    {
        parent::__construct($container);

        $this->fileDefault = "{$container->DIR_CONFIG}/{$file}";
        $this->fileCache   = "{$container->DIR_CACHE}/generated_bbcode.php";
    }

    /**
     * Загружает массив сгенерированных bbcode
     */
    public function init(): BBCodeList
    {
        if (! \is_file($this->fileCache)) {
            $this->generate();
        }

        $oldBadFile = $this->c->ErrorHandler->addBadFile($this->fileCache);

        $this->list = include $this->fileCache;

        $this->c->ErrorHandler->addBadFile($oldBadFile);

        return $this;
    }

    /**
     * Очищает кеш сгенерированных bbcode
     */
    public function reset(): BBCodeList
    {
        if (\is_file($this->fileCache)) {
            if (\unlink($this->fileCache)) {
                return $this->invalidate();

            } else {
                throw new RuntimeException('The generated bbcode file cannot be deleted');
            }

        } else {
            return $this;
        }
    }

    /**
     * Очищает opcache/apc от закэшированного файла
     */
    public function invalidate(): BBCodeList
    {
        if (\function_exists('\\opcache_invalidate')) {
            \opcache_invalidate($this->fileCache, true);
        }

        return $this;
    }
}
