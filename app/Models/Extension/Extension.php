<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Extension;

use ForkBB\Models\Model;
use RuntimeException;

class Extension extends Model
{
    const NOT_INSTALLED = 0;
    const DISABLED      = 4;
    const DISABLED_DOWN = 5;
    const DISABLED_UP   = 6;
    const ENABLED       = 8;
    const ENABLED_DOWN  = 9;
    const ENABLED_UP    = 10;
    const CRASH         = 12;

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Extension';

    protected array $prepareData;

    protected function getdispalyName(): string
    {
        return $this->dbData['extra']['display-name'] ?? $this->fileData['extra']['display-name'];
    }

    protected function getversion(): string
    {
        return $this->dbData['version'] ?? $this->fileData['version'];
    }

    protected function getfileVersion(): string
    {
        return $this->fileData['version'] ?? '-';
    }

    protected function getname(): string
    {
        return $this->dbData['name'] ?? $this->fileData['name'];
    }

    protected function getid(): string
    {
        return 'ext-' . \trim(\preg_replace('%\W+%', '-', $this->name), '-');
    }

    protected function getdescription(): string
    {
        return $this->dbData['description'] ?? $this->fileData['description'];
    }

    protected function gettime(): ?string
    {
        return $this->dbData['time'] ?? $this->fileData['time'];
    }

    protected function gethomepage(): ?string
    {
        return $this->dbData['homepage'] ?? $this->fileData['homepage'];
    }

    protected function getlicense(): ?string
    {
        return $this->dbData['license'] ?? $this->fileData['license'];
    }

    protected function getrequirements(): array
    {
        return $this->dbData['extra']['requirements'] ?? $this->fileData['extra']['requirements'];
    }

    protected function getauthors(): array
    {
        return $this->dbData['authors'] ?? $this->fileData['authors'];
    }

    protected function getstatus(): int
    {
        if (null === $this->dbStatus) {
            return self::NOT_INSTALLED;

        } elseif (empty($this->fileData['version'])) {
            return self::CRASH;
        }

        switch (
            \version_compare($this->fileData['version'], $this->dbData['version'])
            + 4 * (1 === $this->dbStatus)
        ) {
            case -1:
                return self::DISABLED_DOWN;
            case 0:
                return self::DISABLED;
            case 1:
                return self::DISABLED_UP;
            case 3:
                return self::ENABLED_DOWN;
            case 4:
                return self::ENABLED;
            case 5:
                return self::ENABLED_UP;
            default:
                throw new RuntimeException("Error in {$this->name} extension status");
        }
    }

    protected function getcanInstall(): bool
    {
        return self::NOT_INSTALLED === $this->status;
    }

    protected function getcanUninstall(): bool
    {
        return \in_array($this->status, [self::DISABLED, self::DISABLED_DOWN, self::DISABLED_UP], true);
    }

    protected function getcanUpdate(): bool
    {
        return \in_array($this->status, [self::DISABLED_UP, self::ENABLED_UP], true);
    }

    protected function getcanDowndate(): bool
    {
        return \in_array($this->status, [self::DISABLED_DOWN, self::ENABLED_DOWN], true);
    }

    protected function getcanEnable(): bool
    {
        return self::DISABLED === $this->status;
    }

    protected function getcanDisable(): bool
    {
        return \in_array($this->status, [self::ENABLED, self::ENABLED_DOWN, self::ENABLED_UP, self::CRASH], true);
    }

    public function prepare(): bool|string|array
    {
        $this->prepareData = [];

        if ($this->fileData['extra']['templates']) {
            foreach ($this->fileData['extra']['templates'] as $cur) {
                switch($cur['type']) {
                    case 'pre':
                        if (empty($cur['name'])) {
                            return 'PRE name not found';

                        } elseif (empty($cur['file'])) {
                            return ['Template file \'%s\' not found', $cur['file']];
                        }

                        $path = $this->fileData['path'] . '/' . \ltrim($cur['file'], '\\/');

                        if (
                            $this->c->Files->isBadPath($path)
                            || ! \is_file($path)
                        ) {
                            return ['Template file \'%s\' not found', $cur['file']];
                        }

                        $data = \file_get_contents($path);

                        foreach (\explode(',', $cur['template']) as $template) {
                            $this->prepareData['templates']['pre'][$template][$cur['name']][] = [
                                'priority' => $cur['priority'] ?: 0,
                                'data'     => $data,
                            ];
                        }

                        break;
                    default:
                        return 'Invalid template type';
                }
            }
        }

        if ($this->fileData['extra']['symlinks']) {
            foreach ($this->fileData['extra']['symlinks'] as $cur) {
                switch($cur['type']) {
                    case 'public':
                        if (
                            empty($cur['target'])
                            || empty($cur['link'])
                            || $this->c->Files->isBadPath($cur['target'])
                            || $this->c->Files->isBadPath($cur['link'])
                        ) {
                            return 'Bad symlink';
                        }

                        $target = $this->fileData['path'] . '/' . \trim($cur['target'], '\\/');

                        if (
                            ! \is_file($target)
                            && ! \is_dir($target)
                        ) {
                            return ['Target \'%s\' not found', $cur['target']];
                        }

                        $link = $this->c->DIR_PUBLIC . '/' . \trim($cur['link'], '\\/');

                        if (
                            ! \is_link($link)
                            && (
                                \is_file($link)
                                || \is_dir($link)
                            )
                        ) {
                            return ['Link \'%s\' already exists', $cur['link']];
                        }

                        $this->prepareData['symlinks'][$target] = $link;

                        break;
                    default:
                        return 'Invalid symlink type';
                }
            }
        }

        if ($this->fileData['extra']['autoload']) {
            foreach ($this->fileData['extra']['autoload'] as $cur) {
                $this->prepareData['autoload'][$cur['prefix']] = $this->fileData['path'] . '/' . \trim($cur['path'], '\\/');
            }
        }

        if ($this->fileData['extra']['config']) {
            $this->prepareData['config'] = $this->fileData['extra']['config'];
        }

        if ($this->fileData['extra']['actions']) {
            $this->prepareData['actions'] = $this->fileData['extra']['actions'];
        }

        if ($this->fileData['extra']['events']) {
            $this->prepareData['events'] = $this->fileData['extra']['events'];
        }

        return true;
    }

    public function prepareData(): array
    {
        return $this->prepareData;
    }
}
