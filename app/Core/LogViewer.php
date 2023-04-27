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

    protected string $dir;
    protected string $namePattern;
    protected string $linePattern;
    protected string $typePattern;
    protected array $fileList;
    protected array $hashList;
    protected array $replName = [
        '.' => '\\.',
        '*' => '.*',
        '?' => '.',
        '%' => '\\%',
    ];
    protected array $replLine = [
        '['            => '\\[',
        ']'            => '\\]',
        '%datetime%'   => '(?P<datetime>(?=\S)[a-z0-9,\.:/ -]+)',
        '%level_name%' => '(?P<level_name>(?:emergency|alert|critical|error|warning|notice|info|debug))',
        '%message%'    => '(?P<message>.*?)',
        '%context%'    => '(?P<context>(?:\[.*?\]|{.*?}))',
    ];

    public function __construct(array $config, protected CacheInterface $cache)
    {
        $this->dir         = \rtrim(\realpath($config['dir'] ?? __DIR__ . '/../log'), '\\/');
        $this->namePattern = $this->toNamePattern($config['pattern'] ?? '*.log');
        $this->fileList    = $this->getFileList();

        $this->setPatterns($config['lineFormat'] ?? "%datetime% [%level_name%] %message%\t%context%\n");
    }

    protected function setPatterns(string $format): void
    {
        $pos = \strpos($format, '%level_name%');

        if (false === $pos) {
            throw new RuntimeException('Missing log level in log format');
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
        $this->setHashList($result);

        return $result;
    }

    protected function setHashList(array $fileList): void
    {
        $this->hashList = [];

        foreach ($fileList as $name => $time) {
            $this->hashList[\sha1($name)] = $name;
        }
    }

    /**
     * Возвращает список логов в виде:
     * 'реальный путь до файла' => 'время последнего изменения', ...
     */
    public function files(): array
    {
        return $this->fileList;
    }

    /**
     * Возвращает путь к логу по его хэшу
     */
    public function getPath(string $hash): ?string
    {
        return $this->hashList[$hash] ?? null;
    }

    /**
     * Возвращает имя лога
     */
    public function getName(string $path): string
    {
        if (! \preg_match('%[\\\/]([^\\\/]++)$%D', $path, $matches)) {
            throw new RuntimeException("Can't extract filename from path '{$path}'");
        }

        return $matches[1];
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
                $result[$hash] = $cache[$hash]['data'];
            } else {
                $result[$hash] = $this->generateInfo($key);
                $cache[$hash]  = [
                    'time' => $this->fileList[$key],
                    'data' => $result[$hash],
                ];
            }
        }

        $cache = \array_intersect_key($cache, $this->hashList);

        $this->cache->set(self::CACHE_KEY, $cache);

        return $result;
    }

    protected function generateInfo(string $file): array
    {
        $result = [
            'log_name'  => $this->getName($file),
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

    public function parse(string $file): array
    {
        $result = [];
        $handle = \fopen($file, 'rb');

        if ($handle) {
            $contents = '';
            $current  = '';
            $matches1 = [];

            while (! \feof($handle)) {
                $contents .= \fread($handle, 8192);
                $contents = \str_replace("\r\n", "\n", $contents);
                $contents = \str_replace("\r", "\n", $contents);

                while (false !== ($pos = \strpos($contents, "\n"))) {
                    $line     = \substr($contents, 0, $pos + 1);
                    $contents = \substr($contents,  $pos + 1);

                    if (\preg_match($this->typePattern, $line, $matches)) {
                        if ('' !== $current) {
                            $result[] = $this->toResult($current, $matches1);
                        }

                        $current  = $line;
                        $matches1 = $matches;
                    } else {
                        $current .= $line;
                    }
                }
            }

            \fclose($handle);
        }

        if ('' !== $current) {
            $result[] = $this->toResult($current, $matches1);
        }

        return $result;
    }

    protected function toResult(string $current, array $matches1): array
    {
        if (\preg_match($this->linePattern, $current, $matches2)) {
            $result            = $this->clearMatches($matches2);
            $result['context'] = \json_decode($result['context'], true, 512, \JSON_THROW_ON_ERROR);

            return $result;
        } else {
            return $this->clearMatches($matches1) + [
                'message' => 'LOG PARSER ERROR',
                'context' => null,
            ];
        }
    }

    protected function clearMatches(array $matches): array
    {
        return \array_filter($matches, function($key) {
            return \is_string($key);
        }, \ARRAY_FILTER_USE_KEY);
    }
}
