<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Execute extends Method
{
    protected $selectForIndex;
    protected $selectForPosts;
    protected $sortType;
    protected $words;
    protected $stmt;

    /**
     * @param array $options
     *
     * @throws RuntimeException
     *
     * @return array
     */
    public function execute(array $options)
    {
        if (! is_array($this->model->queryWords) || ! is_string($this->model->queryText)) {
            throw new InvalidArgumentException('No query data');
        }

echo '<pre>';
var_dump($this->model->queryText);

        $this->words = [];
        $this->stmt  = null;
        $vars        = $this->buildSelect($options);

        $ids = $this->exec($this->model->queryWords, $vars);

        if ('asc' === $options['sort_dir']) {
            asort($ids, $this->sortType);
        } else {
            arsort($ids, $this->sortType);
        }

var_dump($ids);
echo '</pre>';
        exit();
    }

    /**
     * Поиск по словам рекурсивного списка
     *
     * @param array $words
     * @param array $vars
     * @param array $ids
     *
     * @return array
     */
    protected function exec(array $words, array $vars, array $ids = [])
    {
        $type  = 'AND';
        $count = 0;

        foreach ($words as $word) {

var_dump($word);

            // служебное слово
            if ('AND' === $word || 'OR' === $word || 'NOT' === $word) {
                $type = $word;
                continue;
            }

            // если до сих пор ни чего не найдено и тип операции не ИЛИ, то выполнять не надо
            if ($count && empty($ids) && 'OR' !== $type) {
                continue;
            }

            if (is_array($word) && (! isset($word['type']) || 'CJK' !== $word['type'])) {
                $ids = $this->exec($word, $vars, $ids);
            } else {
                $CJK = false;
                if (isset($word['type']) && 'CJK' === $word['type']) {
                    $CJK  = true;
                    $word = $word['word']; //???? добавить *
                }

                $word = str_replace(['*', '?'], ['%', '_'], $word);

                if (isset($this->words[$word])) {
                    $list = $this->words[$word];
                } else {
                    $vars[':word'] = $word;

                    if (null === $this->stmt) {
                        $this->stmt = $this->c->DB->prepare($this->selectForIndex, $vars);
                        $this->stmt->execute();
                    } else {
                        $this->stmt->execute($vars);
                    }
                    $this->words[$word] = $list = $this->stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                }

var_dump($list);
                if (! $count) {
                    $ids = $list;
                } elseif ('AND' === $type) {
                    $ids = array_intersect_key($ids, $list);
                } elseif ('OR' === $type) {
                    $ids += $list;
                } elseif ('NOT' === $type) {
                    $ids = array_diff_key($ids, $list);
                }
            }

            ++$count;
        }

        return $ids;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function buildSelect(array $options)
    {
        # ["keywords"]=> string(5) "fnghj"
        # ["author"]  => string(0) ""
        # ["forums"]  => NULL
        # ["serch_in"]=> string(3) "all"
        # ["sort_by"] => string(4) "post"
        # ["sort_dir"]=> string(4) "desc"
        # ["show_as"] => string(5) "posts"
        $vars  = [];
        $where = [];
        $joinT = false;
        $joinP = false;

        switch ($options['serch_in']) {
            case 'posts':
                $where[]            = 'm.subject_match=0';
                break;
            case 'topics':
                $where[]            = 'm.subject_match=1';
                // при поиске в заголовках результат только в виде списка тем
                $options['show_as'] = 'topics';
                break;
        }

        if (! empty($options['forums'])) {
            $joinT                  = true;
            $where[]                = 't.forum_id IN (?ai:forums)';
            $vars[':forums']        = (array) $options['forums'];
        }

        if ('topics' === $options['show_as']) {
            $showTopics             = true;
            $joinP                  = true;
            $selectF                = 'p.topic_id';
        } else {
            $showTopics             = false;
            $selectF                = 'm.post_id';
        }

        //???? нужен индекс по авторам сообщений
        //???? что делать с подчеркиванием в именах?
        if ('' != $options['author']) {
            $joinP                  = true;
            $vars[':author']        = str_replace(['*', '?'], ['%', '_'], $options['author']);
            $where[]                = 'p.poster LIKE ?s:author';
        }

        switch ($options['sort_by']) {
            case 'author':
                if ($showTopics) {
                    $sortBy         = 't.poster';
                    $joinT          = true;
                } else {
                    $sortBy         = 'p.poster';
                    $joinP          = true;
                }
                $this->sortType     = SORT_STRING;
                break;
            case 'subject':
                $sortBy             = 't.subject';
                $joinT              = true;
                $this->sortType     = SORT_STRING;
                break;
            case 'forum':
                $sortBy             = 't.forum_id';
                $joinT              = true;
                $this->sortType     = SORT_NUMERIC;
                break;
            default:
                if ($showTopics) {
                    $sortBy         = 't.last_post';
                    $joinT          = true;
                } else {
                    $sortBy         = 'm.post_id';
                }
                $this->sortType     = SORT_NUMERIC;
                break;
        }

        $joinP = $joinP || $joinT ? 'INNER JOIN ::posts AS p ON p.id=m.post_id '   : '';
        $joinT = $joinT           ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '';
        $where = empty($where)    ? '' : ' AND ' . implode(' AND ', $where);

        $this->selectForIndex = "SELECT {$selectF}, {$sortBy} FROM ::search_words AS w " .
                                'INNER JOIN ::search_matches AS m ON m.word_id=w.id ' .
                                $joinP .
                                $joinT .
                                'WHERE w.word LIKE ?s:word' . $where;

        return $vars;
    }
}
