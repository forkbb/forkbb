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

    /**
     * Список отсканированных папок
     */
    protected array $folders = [];

    /**
     * Текст ошибки
     */
    protected string|array $error = '';

    /**
     * Путь до файла, который содержит данные из всех установленных расширений
     */
    protected string $commonFile;

    /**
     * Возвращает action (или свойство) по его имени
     */
    public function __get(string $name): mixed
    {
        return 'error' === $name ? $this->error : parent::__get($name);
    }

    /**
     * Инициализирует менеджер
     */
    public function init(): Extensions
    {
        $this->commonFile = $this->c->DIR_CONFIG . '/ext/common.php';

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
                'name'                       => 'required|string',
                'type'                       => 'required|string|in:forkbb-extension',
                'description'                => 'required|string',
                'homepage'                   => 'string',
                'version'                    => 'required|string',
                'time'                       => 'string',
                'license'                    => 'string',
                'authors'                    => 'required|array',
                'authors.*.name'             => 'required|string',
                'authors.*.email'            => 'string',
                'authors.*.homepage'         => 'string',
                'authors.*.role'             => 'string',
                'autoload.psr-4'             => 'required|array',
                'autoload.psr-4.*'           => 'required|string',
                'require'                    => 'required|array',
                'extra'                      => 'required|array',
                'extra.display-name'         => 'required|string',
                'extra.requirements'         => 'array',
                'extra.templates'            => 'array',
                'extra.templates.*.type'     => 'required|string|in:pre',
                'extra.templates.*.template' => 'required|string',
                'extra.templates.*.name'     => 'string',
                'extra.templates.*.priority' => 'integer',
                'extra.templates.*.file'     => 'string',
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

    /**
     * Устанавливает расширение
     */
    public function install(Extension $ext): bool
    {
        if (true !== $ext->canInstall) {
            $this->error = 'Invalid action';

            return false;
        }

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

            return false;
        }

        $vars = [
            ':name' => $ext->name,
            ':data' => \json_encode($ext->fileData, FORK_JSON_ENCODE),
        ];
        $query = 'INSERT INTO ::extensions (ext_name, ext_status, ext_data)
            VALUES(?s:name, 1, ?s:data)';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 1,
            'dbData'   => $ext->fileData,
            'fileData' => $ext->fileData,
        ]);

        $this->updateCommon($ext);

        return true;
    }

    /**
     * Удаляет расширение
     */
    public function uninstall(Extension $ext): bool
    {
        if (true !== $ext->canUninstall) {
            $this->error = 'Invalid action';

            return false;
        }

        $vars = [
            ':name' => $ext->name,
        ];
        $query = 'DELETE
            FROM ::extensions
            WHERE ext_name=?s:name';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => null,
            'dbData'   => null,
            'fileData' => $ext->fileData,
        ]);

        $this->updateCommon($ext);

        return true;
    }

    /**
     * Обновляет расширение
     */
    public function update(Extension $ext): bool
    {
        if (true !== $ext->canUpdate) {
            $this->error = 'Invalid action';

            return false;
        }

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

            return false;
        }

        $vars = [
            ':name' => $ext->name,
            ':data' => \json_encode($ext->fileData, FORK_JSON_ENCODE),
        ];
        $query = 'UPDATE ::extensions SET ext_data=?s:data
            WHERE ext_name=?s:name';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => $ext->dbStatus,
            'dbData'   => $ext->fileData,
            'fileData' => $ext->fileData,
        ]);

        $this->updateCommon($ext);

        return true;
    }

    /**
     * Обновляет расширение
     */
    public function downdate(Extension $ext): bool
    {
        if (true !== $ext->canDowndate) {
            $this->error = 'Invalid action';

            return false;
        }

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

            return false;
        }

        $vars = [
            ':name' => $ext->name,
            ':data' => \json_encode($ext->fileData, FORK_JSON_ENCODE),
        ];
        $query = 'UPDATE ::extensions SET ext_data=?s:data
            WHERE ext_name=?s:name';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => $ext->dbStatus,
            'dbData'   => $ext->fileData,
            'fileData' => $ext->fileData,
        ]);

        $this->updateCommon($ext);

        return true;
    }

    /**
     * Включает расширение
     */
    public function enable(Extension $ext): bool
    {
        if (true !== $ext->canEnable) {
            $this->error = 'Invalid action';

            return false;
        }

        $vars = [
            ':name' => $ext->name,
        ];
        $query = 'UPDATE ::extensions SET ext_status=1
            WHERE ext_name=?s:name';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 1,
            'dbData'   => $ext->dbData,
            'fileData' => $ext->fileData,
        ]);

        return true;
    }

    /**
     * Выключает расширение
     */
    public function disable(Extension $ext): bool
    {
        if (true !== $ext->canDisable) {
            $this->error = 'Invalid action';

            return false;
        }

        $vars = [
            ':name' => $ext->name,
        ];
        $query = 'UPDATE ::extensions SET ext_status=0
            WHERE ext_name=?s:name';

        $this->c->DB->exec($query, $vars);

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 0,
            'dbData'   => $ext->dbData,
            'fileData' => $ext->fileData,
        ]);

        return true;
    }

    /**
     * Обновляет файл с общими данными по расширениям
     */
    protected function updateCommon(Extension $ext): bool
    {
        if (\is_file($this->commonFile)) {
            $data = include $this->commonFile;
        } else {
            $data = [];
        }

        if ($ext::NOT_INSTALLED === $ext->status) {
            unset($data[$ext->name]);
        } else {
            $data[$ext->name] = $ext->prepareData();
        }

        return $this->putData($this->commonFile, $data);
    }

    /**
     * Записывает данные в указанный файл
     */
    protected function putData(string $file, mixed $data): bool
    {
        $content = "<?php\n\nreturn " . \var_export($data, true) . ";\n";

        if (false === \file_put_contents($file, $content, \LOCK_EX)) {
            return false;
        } else {
            if (\function_exists('\\opcache_invalidate')) {
                \opcache_invalidate($file, true);
            } elseif (\function_exists('\\apc_delete_file')) {
                \apc_delete_file($file);
            }

            return true;
        }
    }
}
