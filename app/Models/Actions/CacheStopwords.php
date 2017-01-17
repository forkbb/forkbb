<?php

namespace ForkBB\Models\Actions;

use ForkBB\Core\Cache;

class CacheStopwords
{
    /**
     * @var ForkBB\Core\Cache
     */
    protected $cache;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $id;

    /**
     * Конструктор
     *
     * @param ForkBB\Core\Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Возвращает массив слов, которые не участвуют в поиске
     *
     * @return array
     */
    public function load()
    {
        $arr = $this->cache->get('stopwords');
        if (isset($arr['id'])
            && isset($arr['stopwords'])
            && $arr['id'] === $this->generateId()
        ) {
            return $arr['stopwords'];
        } else {
            return $this->regeneration();
        }
    }

    /**
     * Генерация id кэша на основе найденных файлов stopwords.txt
     *
     * @return string
     */
    protected function generateId()
    {
        if (! empty($this->id)) {
            return $this->id;
        }

        $files = glob(PUN_ROOT . 'lang/*/stopwords.txt');
        if ($files === false) {
            return 'cache_id_error';
        }

        $this->files = $files;
        $hash = [];

        foreach ($files as $file) {
            $hash[] = $file;
            $hash[] = filemtime($file);
        }

        return $this->id = sha1(implode('|', $hash));
    }

    /**
     * Регенерация кэша массива слов с возвращением результата
     *
     * @return array
     */
    protected function regeneration()
    {
        $id = $this->generateId();

        if (! is_array($this->files)) {
            return [];
        }

        $stopwords = [];
        foreach ($this->files as $file) {
            $stopwords = array_merge($stopwords, file($file));
        }

        // Tidy up and filter the stopwords
        $stopwords = array_map('trim', $stopwords);
        $stopwords = array_filter($stopwords);

        $this->cache->set('stopwords', ['id' => $id, 'stopwords' => $stopwords]);
        return $stopwords;
    }
}
