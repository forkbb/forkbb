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
    const FOLDER_NAMES = ['DIR_EXT', 'DIR_PUBLIC', 'DIR_VIEWS', 'DIR_LOG', 'DIR_LANG', 'DIR_CONFIG', 'DIR_CACHE', 'DIR_APP', 'DIR_ROOT'];

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

    protected string $commonFile;
    protected string $preFile;

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
        $this->preFile    = $this->c->DIR_CONFIG . '/ext/pre.php';

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
                'name'                       => 'required|string|regex:%^[a-z0-9](?:[_.-]?[a-z0-9]+)*/[a-z0-9](?:[_.-]?[a-z0-9]+)*$%',
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
                'autoload.psr-4'             => 'array',
                'autoload.psr-4.*'           => 'required|string',
                'require'                    => 'array',
                'extra'                      => 'required|array',
                'extra.display-name'         => 'required|string',
                'extra.requirements'         => 'array',
                'extra.symlinks'             => 'array',
                'extra.symlinks.*.type'      => 'required|string|in:public',
                'extra.symlinks.*.target'    => 'required|string',
                'extra.symlinks.*.link'      => 'required|string',
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
            $context = null;

            if (! \is_array($file)) {
                $context = [
                    'errors' => ['Bad json'],
                ];

            } elseif (! $v->validation($file)) {
                $context = [
                    'errors' => \array_map('\\ForkBB\__', $v->getErrorsWithoutType()),
                ];
            }

            if (null === $context) {
                $data             = $v->getData(true);
                $data['path']     = $path;
                $result[$v->name] = $data;

            } else {
                $context['headers'] = false;
                $path               = \preg_replace('%^.+((?:[\\\\/]+[^\\\\/]+){3})$%', '$1', $path);

                $this->c->Log->debug("Extension: Bad structure for {$path}", $context);
            }
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

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 1,
            'dbData'   => $ext->fileData,
            'fileData' => $ext->fileData,
        ]);

        if (true !== $this->updateCommon($ext)) {
            $this->error = 'An error occurred in updateCommon';

            return false;
        }

        $this->setSymlinks($ext);
        $this->updateIndividual();

        $this->c->DB->exec($query, $vars);

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

        $oldStatus = $ext->dbStatus;

        $vars = [
            ':name' => $ext->name,
        ];
        $query = 'DELETE
            FROM ::extensions
            WHERE ext_name=?s:name';

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => null,
            'dbData'   => null,
            'fileData' => $ext->fileData,
        ]);

        $this->removeSymlinks($ext);

        if (true !== $this->updateCommon($ext)) {
            $this->error = 'An error occurred in updateCommon';

            return false;
        }

        if ($oldStatus) {
            $this->updateIndividual();
        }

        $this->c->DB->exec($query, $vars);

        return true;
    }

    /**
     * Обновляет расширение
     */
    public function update(Extension $ext): bool
    {
        if (true === $ext->canUpdate) {
            return $this->updown($ext);

        } else {
            $this->error = 'Invalid action';

            return false;
        }
    }

    /**
     * Обновляет расширение
     */
    public function downdate(Extension $ext): bool
    {
        if (true === $ext->canDowndate) {
            return $this->updown($ext);

        } else {
            $this->error = 'Invalid action';

            return false;
        }
    }

    protected function updown(Extension $ext): bool
    {
        $oldStatus = $ext->dbStatus;
        $result    = $ext->prepare();

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

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => $ext->dbStatus,
            'dbData'   => $ext->fileData,
            'fileData' => $ext->fileData,
        ]);

        if ($oldStatus) {
            $this->removeSymlinks($ext);
        }

        if (true !== $this->updateCommon($ext)) {
            $this->error = 'An error occurred in updateCommon';

            return false;
        }

        if ($oldStatus) {
            $this->setSymlinks($ext);
            $this->updateIndividual();
        }

        $this->c->DB->exec($query, $vars);

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

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 1,
            'dbData'   => $ext->dbData,
            'fileData' => $ext->fileData,
        ]);

        $this->setSymlinks($ext);
        $this->updateIndividual();

        $this->c->DB->exec($query, $vars);

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

        $ext->setModelAttrs([
            'name'     => $ext->name,
            'dbStatus' => 0,
            'dbData'   => $ext->dbData,
            'fileData' => $ext->fileData,
        ]);

        $this->removeSymlinks($ext);
        $this->updateIndividual();

        $this->c->DB->exec($query, $vars);

        return true;
    }

    /**
     * Возвращает данные из файла с общими данными по расширениям
     */
    protected function loadDataFromFile(string $file): array
    {
        if (\is_file($file)) {
            return include $file;

        } else {
            return [];
        }
    }

    /**
     * Обновляет файл с общими данными по расширениям
     */
    protected function updateCommon(Extension $ext): bool
    {
        $data = $this->loadDataFromFile($this->commonFile);

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

        foreach (self::FOLDER_NAMES as $dir) {
            $search  = \str_replace('\\', '\\\\', $this->c->{$dir});
            $content = \str_replace("'{$search}" , "\$this->c->{$dir} . '", $content);
        }

        if (false === \file_put_contents($file, $content, \LOCK_EX)) {
            return false;

        } else {
            if (\function_exists('\\opcache_invalidate')) {
                \opcache_invalidate($file, true);
            }

            return true;
        }
    }

    /**
     * Обновляет индивидуальные файлы с данными по расширениям
     */
    protected function updateIndividual(): bool
    {
        $oldPre     = $this->loadDataFromFile($this->preFile);
        $templates  = [];
        $commonData = $this->loadDataFromFile($this->commonFile);
        $pre        = [];
        $newPre     = [];

        // выделение данных
        foreach ($this->repository as $ext) {
            if (1 !== $ext->dbStatus) {
                continue;
            }

            if (isset($commonData[$ext->name]['templates']['pre'])) {
                $pre = \array_merge_recursive($pre, $commonData[$ext->name]['templates']['pre']);
            }
        }

        // PRE-данные шаблонов
        foreach ($pre as $template => $names) {
            $templates[$template] = $template;

            foreach ($names as $name => $list) {
                \uasort($list, function (array $a, array $b) {
                    return $b['priority'] <=> $a['priority'];
                });

                $result = '';

                foreach ($list as $value) {
                    $result .= $value['data'];
                }

                $newPre[$template][$name] = $result;
            }
        }

        $this->putData($this->preFile, $newPre);

        // удаление скомпилированных шаблонов
        foreach (\array_merge($this->diffPre($oldPre, $newPre), $this->diffPre($newPre, $oldPre)) as $template) {
            $this->c->View->delete($template);
        }

        return true;
    }

    /**
     * Вычисляет расхождение для PRE-данных
     */
    protected function diffPre(array $a, array $b): array
    {
        $result = [];

        foreach ($a as $template => $names) {
            if (! isset($b[$template])) {
                $result[$template] = $template;

                continue;
            }

            foreach ($names as $name => $value) {
                if (
                    ! isset($b[$template][$name])
                    || $value !== $b[$template][$name]
                ) {
                    $result[$template] = $template;

                    continue 2;
                }
            }
        }

        return $result;
    }

    /**
     * Создает симлинки для расширения
     */
    protected function setSymlinks(Extension $ext): bool
    {
        $data     = $this->loadDataFromFile($this->commonFile);
        $symlinks = $data[$ext->name]['symlinks'] ?? [];

        foreach ($symlinks as $target => $link) {
            \symlink($target, $link);
        }

        return true;
    }

    /**
     * Удаляет симлинки расширения
     */
    protected function removeSymlinks(Extension $ext): bool
    {
        $data     = $this->loadDataFromFile($this->commonFile);
        $symlinks = $data[$ext->name]['symlinks'] ?? [];

        foreach ($symlinks as $target => $link) {
            if (\is_link($link)) {
                \is_file($link) ? \unlink($link) : \rmdir($link);
            }
        }

        return true;

    }
}
