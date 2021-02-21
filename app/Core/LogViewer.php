<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;

class LogViewer
{
    const CACHE_KEY = 'logs_info';

    protected $cache;
    protected $dir;
    protected $namePattern;
    protected $linePattern;
    protected $typePattern;
    protected $resource;
    protected $fileList;
    protected $replName = [
        '.' => '\\.',
        '*' => '.*',
        '?' => '.',
        '%' => '\\%',
    ];
    protected $replLine = [
        '['            => '\\[',
        ']'            => '\\]',
        '%datetime%'   => '(?P<datetime>(?=\S)[a-z0-9,\.:/ -]+)',
        '%level_name%' => '(?P<level_name>(?:emergency|alert|critical|error|warning|notice|info|debug))',
        '%message%'    => '(?P<message>.*?)',
        '%context%'    => '(?P<context>(?:\[.*?\]|{.*?}))',
    ];

    public function __construct(array $config, CacheInterface $cache)
    {
        $this->cache       = $cache;
        $this->dir         = \rtrim(\realpath($config['dir'] ?? __DIR__ . '/../log'), '\\/');
        $this->namePattern = $this->toNamePattern($config['pattern'] ?? '*.log');
        $this->fileList    = $this->getFileList();

        $this->setPatterns($config['lineFormat'] ?? "%datetime% [%level_name%] %message%\t%context%\n");
    }

    protected function setPatterns($format): void
    {
        $pos = \strpos($format, '%level_name%');

        if (false === $pos) {
            throw new RuntimeException('Missing log level in log format ');
        }

        $pos = $pos + 12;

        while (\preg_match('%[^a-z0-9\%\\\]%', $format[$pos])) {
            ++$pos;
        }

        $typeFormat        = \substr($format, 0, $pos);
        $this->typePattern = '%^' . \strtr($typeFormat, $this->replLine) . '%i';
        $this->linePattern = '%^' . \strtr($format, $this->replLine) . '%i';
    }

    protected function toNamePattern(string $pattern): string
    {
        return '%^' . \strtr($pattern, $this->replName) . '$%i';
    }

    protected function getFileList(): array
    {
        $dir      = new RecursiveDirectoryIterator($this->dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir);
        $files    = new RegexIterator($iterator, $this->namePattern, RegexIterator::MATCH);
        $result   = [];

        foreach ($files as $file) {
            $result[$file->getRealPath()] = $file->getMTime();
        }

        \arsort($result, \SORT_NUMERIC);

        return $result;
    }

    /**
     * Возвращает список логов в виде:
     * 'реальный путь до файла' => 'время последнего изменения', ...
     */
    public function list(): array
    {
        return $this->fileList;
    }

    /**
     * Возвращает общую информацию по логам
     * и генерирует кеш
     */
    public function info(array $files): array
    {
        $result = [];
        $cache  = $this->cache->get(self::CACHE_KEY, []);

        foreach ($files as $key1 => $key2) {
            $key = \is_string($key1) ? $key1 : $key2;

            if (! isset($this->fileList[$key])) {
                continue;
            }

            $hash = \sha1($key);

            if (
                isset($cache[$hash])
                && $cache[$hash]['time'] === $this->fileList[$key]
            ) {
                $result[$key] = $cache[$hash]['data'];
            } else {
                $result[$key] = $this->generateInfo($key);
                $cache[$hash] = [
                    'time' => $this->fileList[$key],
                    'data' => $result[$key],
                ];
            }
        }

        $files = [];

        foreach ($this->fileList as $key => $val) {
            $hash         = \sha1($key);
            $files[$hash] = $val;
        }

        $cache = \array_intersect_key($cache, $files);

        $this->cache->set(self::CACHE_KEY, $cache);

        return $result;
    }

    protected function generateInfo(string $file): array
    {
        $result = [
            'emergency' => 0,
            'alert'     => 0,
            'critical'  => 0,
            'error'     => 0,
            'warning'   => 0,
            'notice'    => 0,
            'info'      => 0,
            'debug'     => 0,
        ];
        $handle = \fopen($file, 'rb');

        if ($handle) {
            $contents = '';

            while (! \feof($handle)) {
                $contents .= \fread($handle, 8192);
                $contents = \str_replace("\r\n", "\n", $contents);
                $contents = \str_replace("\r", "\n", $contents);

                while (false !== ($pos = \strpos($contents, "\n"))) {
                    $line     = \substr($contents, 0, $pos + 1);
                    $contents = \substr($contents,  $pos + 1);

                    if (\preg_match($this->typePattern, $line, $matches)) {
                        ++$result[\strtolower($matches['level_name'])];
                    }
                }
            }

            \fclose($handle);
        }

        return $result;
    }
}
