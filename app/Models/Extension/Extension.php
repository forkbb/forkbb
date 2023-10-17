<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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

    protected function getdispalyName(): string
    {
        return $this->dbData['extra']['display-name'] ?? $this->fileData['extra']['display-name'];
    }

    protected function getversion(): string
    {
        return $this->dbData['version'] ?? $this->fileData['version'];
    }

    protected function getname(): string
    {
        return $this->dbData['name'] ?? $this->fileData['name'];
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
}
