<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\StopwordList;

use ForkBB\Models\Model;
use RuntimeException;

class StopwordList extends Model
{
    const CACHE_KEY = 'stopwords';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'StopwordList';

    /**
     * Загружает список игнорируемых при индексации слов из кеша/БД
     */
    public function init(): StopwordList
    {
        $data = $this->c->Cache->get(self::CACHE_KEY);

        if (
            isset($data['id'], $data['stopwords'])
            && $data['id'] === $this->generateId()
        ) {
            $this->list = $data['stopwords'];

        } else {
            $this->load();
        }

        return $this;
    }

    /**
     * Генерирует id кэша на основе найденных файлов stopwords.txt
     */
    protected function generateId(): string
    {
        if (! empty($this->id)) {
            return $this->id;
        }

        $files = \glob($this->c->DIR_LANG . '/*/stopwords.txt');

        if (false === $files) {
            return 'cache_id_error';
        }

        $this->files = $files;
        $hash = [];

        foreach ($files as $file) {
            $hash[] = $file;
            $hash[] = \filemtime($file);
        }

        return $this->id = \sha1(\implode('|', $hash));
    }

    /**
     * Регенерация кэша массива слов с возвращением результата
     */
    protected function load(): StopwordList
    {
        $id = $this->generateId();

        if (! \is_array($this->files)) {
            $this->list = [];

            return $this;
        }

        $stopwords = [];

        foreach ($this->files as $file) {
            $stopwords = \array_merge($stopwords, \file($file));
        }

        // Tidy up and filter the stopwords
        $stopwords = \array_map('\\trim', $stopwords);
        $stopwords = \array_filter($stopwords);
        $stopwords = \array_flip($stopwords);

        if (true !== $this->c->Cache->set(self::CACHE_KEY, ['id' => $id, 'stopwords' => $stopwords])) {
            throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
        }

        $this->list = $stopwords;

        return $this;
    }
}
