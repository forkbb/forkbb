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
    protected $queryIdx;
    protected $queryCJK;
    protected $sortType;
    protected $words;
    protected $stmtIdx;
    protected $stmtCJK;

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

        $this->words   = [];
        $this->stmtIdx = null;
        $this->stmtCJK = null;
        $vars          = $this->buildSelect($options);

var_dump($this->queryIdx, $this->queryCJK);

        $ids = $this->exec($this->model->queryWords, $vars);

        if ('asc' === $options['sort_dir']) {
            asort($ids, $this->sortType);
        } else {
            arsort($ids, $this->sortType);
        }

var_dump($ids);
echo '</pre>';

        return $ids;
    }

    /**
     * Поиск по словам рекурсивного списка
     *
     * @param array $words
     * @param array $vars
     *
     * @return array
     */
    protected function exec(array $words, array $vars)
    {
        $type  = 'AND';
        $count = 0;
        $ids   = [];

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
                $ids = $this->exec($word, $vars);
            } else {
                $CJK = false;
                if (isset($word['type']) && 'CJK' === $word['type']) {
                    $CJK  = true;
                    $word = '*' . trim($word['word'], '*') . '*';
                }

                $word = str_replace(['*', '?'], ['%', '_'], $word);

                if (isset($this->words[$word])) {
                    $list = $this->words[$word];
                } else {
                    $vars[':word'] = $word;

                    if ($CJK) {
                        if (null === $this->stmtCJK) {
                            $this->stmtCJK = $this->c->DB->prepare($this->queryCJK, $vars);
                            $this->stmtCJK->execute();
                        } else {
                            $this->stmtCJK->execute($vars);
                        }
                        $this->words[$word] = $list = $this->stmtCJK->fetchAll(PDO::FETCH_KEY_PAIR);
                    } else {
                        if (null === $this->stmtIdx) {
                            $this->stmtIdx = $this->c->DB->prepare($this->queryIdx, $vars);
                            $this->stmtIdx->execute();
                        } else {
                            $this->stmtIdx->execute($vars);
                        }
                        $this->words[$word] = $list = $this->stmtIdx->fetchAll(PDO::FETCH_KEY_PAIR);
                    }
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
        $whereIdx = [];
        $whereCJK = [];
        $joinTIdx = false;
        $joinPIdx = false;
        $useT     = false;
        $useP     = false;

        if (! empty($options['forums'])) {
            $joinTIdx               = true;
            $whereIdx[]             = 't.forum_id IN (?ai:forums)';
            $whereCJK[]             = 't.forum_id IN (?ai:forums)';
            $useT                   = true;
            $vars[':forums']        = (array) $options['forums'];
        }

        //???? нужен индекс по авторам сообщений/тем
        //???? что делать с подчеркиванием в именах?
        if ('' != $options['author']) {
            $joinPIdx               = true;
            $vars[':author']        = str_replace(['*', '?'], ['%', '_'], $options['author']);
            $whereIdx[]             = 'p.poster LIKE ?s:author';
        }

        switch ($options['serch_in']) {
            case 'posts':
                $whereIdx[]         = 'm.subject_match=0';
                $whereCJK[]         = 'p.message LIKE ?s:word';
                $useP               = true;
                if (isset($vars[':author'])) {
                    $whereCJK[]     = 'p.poster LIKE ?s:author';
                }
                break;
            case 'topics':
                $whereIdx[]         = 'm.subject_match=1';
                $whereCJK[]         = 't.subject LIKE ?s:word';
                $useT               = true;
                if (isset($vars[':author'])) {
                    $whereCJK[]     = 't.poster LIKE ?s:author';
                }
                // при поиске в заголовках результат только в виде списка тем
                $options['show_as'] = 'topics';
                break;
            default:
                if (isset($vars[':author'])) {
                    $whereCJK[]     = '((p.message LIKE ?s:word AND p.poster LIKE ?s:author) OR (t.subject LIKE ?s:word AND t.poster LIKE ?s:author))';
                } else {
                    $whereCJK[]     = '(p.message LIKE ?s:word OR t.subject LIKE ?s:word)';
                }
                $useP               = true;
                $useT               = true;
                break;
        }

        if ('topics' === $options['show_as']) {
            $showTopics             = true;
            $joinPIdx               = true;
            $selectFIdx             = 'p.topic_id';
            $selectFCJK             = 't.id';
            $useT                   = true;
        } else {
            $showTopics             = false;
            $selectFIdx             = 'm.post_id';
            $selectFCJK             = 'p.id';
            $useP                   = true;
        }

        switch ($options['sort_by']) {
            case 'author':
                if ($showTopics) {
                    $sortIdx        = 't.poster';
                    $sortCJK        = 't.poster';
                    $joinTIdx       = true;
                    $useT           = true;
                } else {
                    $sortIdx        = 'p.poster';
                    $sortCJK        = 'p.poster';
                    $joinPIdx       = true;
                    $useP           = true;
                }
                $this->sortType     = SORT_STRING;
                break;
            case 'subject':
                $sortIdx            = 't.subject';
                $sortCJK            = 't.subject';
                $joinTIdx           = true;
                $useT               = true;
                $this->sortType     = SORT_STRING;
                break;
            case 'forum':
                $sortIdx            = 't.forum_id';
                $sortCJK            = 't.forum_id';
                $joinTIdx           = true;
                $useT               = true;
                $this->sortType     = SORT_NUMERIC;
                break;
            default:
                if ($showTopics) {
                    $sortIdx        = 't.last_post';
                    $sortCJK        = 't.last_post';
                    $joinTIdx       = true;
                    $useT           = true;
                } else {
                    $sortIdx        = 'm.post_id';
                    $sortCJK        = 'p.id';
                    $useP           = true;
                }
                $this->sortType     = SORT_NUMERIC;
                break;
        }

        $joinPIdx = $joinPIdx || $joinTIdx ? 'INNER JOIN ::posts AS p ON p.id=m.post_id '   : '';
        $joinTIdx = $joinTIdx           ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '';
        $whereIdx = empty($whereIdx)    ? '' : ' AND ' . implode(' AND ', $whereIdx);

        $this->queryIdx = "SELECT {$selectFIdx}, {$sortIdx} FROM ::search_words AS w " .
                          'INNER JOIN ::search_matches AS m ON m.word_id=w.id ' .
                          $joinPIdx .
                          $joinTIdx .
                          'WHERE w.word LIKE ?s:word' . $whereIdx;

        if ($useP) {
            $this->queryCJK = "SELECT {$selectFCJK}, {$sortCJK} FROM ::posts AS p " .
                              ($useT ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '') .
                              'WHERE ' . implode(' AND ', $whereCJK);
        } else {
            $this->queryCJK = "SELECT {$selectFCJK}, {$sortCJK} FROM ::topics AS t " .
                              'WHERE ' . implode(' AND ', $whereCJK);
        }

        return $vars;
    }
}
