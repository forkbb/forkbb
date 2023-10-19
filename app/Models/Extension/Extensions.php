<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Extension;

use ForkBB\Models\Extension\Extension;
use ForkBB\Models\Manager;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;

class Extensions extends Manager
{
     /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Extensions';

    protected array $folders = [];

    /**
     * Инициализирует менеджер
     */
    public function init(): Extensions
    {
        $this->fromDB();

        $list = $this->scan($this->c->DIR_EXT);

        $this->fromList($this->prepare($list));

        \uasort($this->repository, function (Extension $a, Extension $b) {
            return $a->dispalyName <=> $b->dispalyName;
        });

        return $this;
    }

    /**
     * Загружает в репозиторий из БД список расширений
     */
    protected function fromDB(): void
    {
        $query = 'SELECT ext_name, ext_status, ext_data
            FROM ::extensions
            ORDER BY ext_name';

        $stmt = $this->c->DB->query($query);

        while ($row = $stmt->fetch()) {
            $model = $this->c->ExtensionModel->setModelAttrs([
                'name'     => $row['ext_name'],
                'dbStatus' => $row['ext_status'],
                'dbData'   => \json_decode($row['ext_data'], true, 512, \JSON_THROW_ON_ERROR),
            ]);

            $this->set($row['ext_name'], $model);
        }
    }

    /**
     * Заполняет массив данными из файлов composer.json
     */
    protected function scan(string $folder, array $result = []): array
    {
        $folder = \rtrim($folder, '\\/');

        if (
            empty($folder)
            || ! \is_dir($folder)
        ) {
            throw new RuntimeException("Not a directory: {$folder}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        );
        $files    = new RegexIterator($iterator, '%[\\\\/]composer\.json$%i', RegexIterator::MATCH);

        foreach ($files as $file) {
            $data = \file_get_contents($file->getPathname());

            if (\is_string($data)) {
                $data = \json_decode($data, true);
            }

            $result[$file->getPath()] = $data;
        }

        $this->folders[] = $folder;

        return $result;
    }

    /**
     * Подготавливает данные для моделей
     */
    protected function prepare(array $files): array
    {
        $v = clone $this->c->Validator;
        $v = $v->reset()
            ->addValidators([
            ])->addRules([
                'name'               => 'required|string',
                'type'               => 'required|string|in:forkbb-extension',
                'description'        => 'required|string',
                'homepage'           => 'string',
                'version'            => 'required|string',
                'time'               => 'string',
                'license'            => 'string',
                'authors'            => 'required|array',
                'authors.*.name'     => 'required|string',
                'authors.*.email'    => 'string',
                'authors.*.homepage' => 'string',
                'authors.*.role'     => 'string',
                'autoload.psr-4'     => 'required|array',
                'autoload.psr-4.*'   => 'required|string',
                'require'            => 'required|array',
                'extra'              => 'required|array',
                'extra.display-name' => 'required|string',
                'extra.requirements' => 'array',
            ])->addAliases([
            ])->addArguments([
            ])->addMessages([
            ]);

        $result = [];

        foreach ($files as $path => $file) {
            if (! \is_array($file)) {
                continue;
            } elseif (! $v->validation($file)) {
                continue;
            }

            $data             = $v->getData(true);
            $data['path']     = $path;
            $result[$v->name] = $data;
        }

        return $result;
    }

    /**
     * Дополняет репозиторий данными из файлов composer.json
     */
    protected function fromList(array $list): void
    {
        foreach ($list as $name => $data) {
            $model = $this->get($name);

            if (! $model instanceof Extension) {
                $model = $this->c->ExtensionModel->setModelAttrs([
                    'name'     => $name,
                    'fileData' => $data,
                ]);

                $this->set($name, $model);
            } else {
                $model->setModelAttr('fileData', $data);
            }
        }
    }
}
