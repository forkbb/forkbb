<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
    protected string $autoFile;
    protected string $configFile;
    protected string $eventsFile;

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
        $this->autoFile   = $this->c->DIR_CONFIG . '/ext/auto.php';
        $this->configFile = $this->c->DIR_CONFIG . '/ext/config.php';
        $this->eventsFile = $this->c->DIR_CONFIG . '/ext/events.php';

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
                'extra.autoload'             => 'array',
                'extra.autoload.*.prefix'    => 'required|string',
                'extra.autoload.*.path'      => 'required|string',
                'extra.config'               => 'array',
                'extra.actions'              => 'string',
                'extra.events'               => 'array',
                'extra.events.*.name'        => 'required|string',
                'extra.events.*.priority'    => 'required|integer',
                'extra.events.*.listener'    => 'required|string',
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
     * Выполняет метод $action для расширения $ext если в настройках этого расширения указан extra.actions
     */
    protected function extraActions(Extension $ext, string $action): bool
    {
        $data = $ext->prepareData();

        if (empty($data['actions'])) {
            return true;
        }

        foreach ($data['autoload'] as $prefix => $paths) {
            $this->c->autoloader->addPsr4($prefix, $paths);
        }

        $class   = $data['actions'];
        $actions = new $class($this->c);

        return $actions->{$action}();
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

        if (true !== $this->setSymlinks($ext)) {
            $this->error = 'Error creating symbolic link';

            return false;
        }

        if (true !== $this->extraActions($ext, 'install')) {
            $this->error = 'The install method from extra.actions failed';

            return false;
        }

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

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

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

        if (true !== $this->extraActions($ext, 'uninstall')) {
            $this->error = 'The uninstall method from extra.actions failed';

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

        if (true !== $this->extraActions($ext, 'updown')) {
            $this->error = 'The updown method from extra.actions failed';

            return false;
        }

        if ($oldStatus) {
            if (true !== $this->setSymlinks($ext)) {
                $this->error = 'Error creating symbolic link';

                return false;
            }

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

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

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

        if (true !== $this->setSymlinks($ext)) {
            $this->error = 'Error creating symbolic link';

            return false;
        }

        if (true !== $this->extraActions($ext, 'enable')) {
            $this->error = 'The enable method from extra.actions failed';

            return false;
        }

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

        $result = $ext->prepare();

        if (true !== $result) {
            $this->error = $result;

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

        if (true !== $this->extraActions($ext, 'disable')) {
            $this->error = 'The disable method from extra.actions failed';

            return false;
        }

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
    protected function putData(string $file, mixed $data, bool $clearClass = false): bool
    {
        $content = "<?php\n\nreturn " . \var_export($data, true) . ";\n";

        foreach (self::FOLDER_NAMES as $dir) {
            $search  = \str_replace('\\', '\\\\', $this->c->{$dir});
            $content = \str_replace("'{$search}" , "\$this->c->{$dir} . '", $content);
        }

        if ($clearClass) {
            $content = \preg_replace_callback('%(=>\s+)[\'"](\\\\.+?::class)[\'"]%', function ($matches) {
                return $matches[1] . \str_replace('\\\\', '\\', $matches[2]);
            }, $content);
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
        $auto       = [];
        $config     = [];
        $events     = [];

        // выделение данных
        foreach ($this->repository as $ext) {
            if (1 !== $ext->dbStatus) {
                continue;
            }

            $cur = $commonData[$ext->name];

            if (isset($cur['templates']['pre'])) {
                $pre = \array_merge_recursive($pre, $cur['templates']['pre']);
            }

            if (! empty($cur['autoload'])) {
                $auto = \array_merge($auto, $cur['autoload']);
            }

            if (! empty($cur['config'])) {
                $config = \array_merge_recursive($config, $cur['config']);
            }

            if (! empty($cur['events'])) {
                $events = \array_merge($events, $cur['events']);
            }
        }

        $this->putData($this->autoFile, $auto);
        $this->putData($this->configFile, $config, true);

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

        // события
        \uasort($events, function (array $a, array $b) {
            return $b['priority'] <=> $a['priority'];
        });

        $result = [];

        foreach ($events as $cur) {
            $result[$cur['name']][] = $cur['listener'];
        }

        $this->putData($this->eventsFile, $result);

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
            if (\is_link($link)) {
                $this->c->Log->debug('Symlink exists. It will be deleted and recreated.', [
                    'link'       => $link,
                    'target'     => \readlink($link),
                    'new target' => $target,
                ]);

                'Windows' === \PHP_OS_FAMILY ? \rmdir($link) : \unlink($link);
            }

            $level  = $this->c->ErrorHandler->logOnly(\E_WARNING);
            $result = \symlink($target, $link);

            $this->c->ErrorHandler->logOnly($level);

            if (false === $result) {
                return false;
            }
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
                'Windows' === \PHP_OS_FAMILY ? \rmdir($link) : \unlink($link);
            }
        }

        return true;

    }
}
