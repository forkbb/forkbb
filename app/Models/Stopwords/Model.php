<?php

namespace ForkBB\Models\Stopwords;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список игнорируемых при индексации слов из кеша/БД
     *
     * @return Stopwords\Model
     */
    public function init(): self
    {
        $data = $this->c->Cache->get('stopwords');
        if (isset($data['id'], $data['stopwords']) && $data['id'] === $this->generateId()) {
            $this->list = $data['stopwords'];
        } else {
            $this->load();
        }
        return $this;
    }

    /**
     * Генерирует id кэша на основе найденных файлов stopwords.txt
     *
     * @return string
     */
    protected function generateId(): string
    {
        if (! empty($this->id)) {
            return $this->id;
        }

        $files = \glob($this->c->DIR_LANG . '/*/stopwords.txt');
        if ($files === false) {
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
     *
     * @return Stopwords\Model
     */
    protected function load(): self
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
        $stopwords = \array_map('trim', $stopwords);
        $stopwords = \array_filter($stopwords);
        $stopwords = \array_flip($stopwords);

        $this->c->Cache->set('stopwords', ['id' => $id, 'stopwords' => $stopwords]);
        $this->list = $stopwords;
        return $this;
    }
}
