<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Core\Validator;
use ForkBB\Core\DB\DBStatement;
use ForkBB\Models\Method;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Post\Post;
use PDO;
use RuntimeException;

class Execute extends Method
{
    protected string $queryIdx;
    protected string $queryCJK;
    protected int $sortType;
    protected array $words;
    protected ?DBStatement $stmtIdx;
    protected ?DBStatement $stmtCJK;

    /**
     * Поиск тем/сообщений в соответствии с поисковым запросом
     * Получение данных из таблицы кеша
     * Сохранение результатов в таблицу кеша
     */
    public function execute(Validator $v, array $forumIdxs, bool $flood): bool
    {
        if (
            ! \is_array($this->model->queryWords)
            || ! \is_string($this->model->queryText)
        ) {
            throw new RuntimeException('No query data');
        }

        $delimiter     = \time() - $this->c->config->i_search_ttl;
        $this->words   = [];
        $this->stmtIdx = null;
        $this->stmtCJK = null;
        $queryVars     = $this->buildSelect($v, $forumIdxs);

        $key = $this->c->user->group_id . '-' .
               $v->serch_in .
               $v->sort_by .
               $v->sort_dir .
               $this->model->showAs . '-' .
               $this->model->queryText . '-' . // $v->keywords
               $v->author . '-' .
               $v->forums;

        $vars = [
            ':key' => $key,
        ];
        $query = 'SELECT sc.search_time, sc.search_data
            FROM ::search_cache AS sc
            WHERE sc.search_key=?s:key
            ORDER BY sc.search_time DESC
            LIMIT 1';

        $row = $this->c->DB->query($query, $vars)->fetch();

        if (
            ! empty($row['search_time'])
            && $delimiter <= $row['search_time']
        ) {
            $result                    = \explode("\n", $row['search_data']);
            $this->model->queryIds     = $result[0];
            $this->model->queryNoCache = false;

            return true;
        } elseif ($flood) {
            return false;
        }

        $ids = $this->exec($this->model->queryWords, $queryVars);

        if (1 === $v->sort_dir) {
            \asort($ids, $this->sortType);
        } else {
            \arsort($ids, $this->sortType);
        }

        $ids = \array_keys($ids);

        $data  = [
            \implode(',', $ids),
        ];
        $vars = [
            ':data' => \implode("\n", $data),
            ':key'  => $key,
            ':time' => \time(),
        ];
        $query = 'INSERT INTO ::search_cache (search_key, search_time, search_data)
            VALUES (?s:key, ?i:time, ?s:data)';

        $this->c->DB->exec($query, $vars);

        $this->model->queryIds     = $ids;
        $this->model->queryNoCache = true;

        $vars = [
            ':time' => $delimiter,
        ];
        $query = 'DELETE FROM ::search_cache WHERE search_time<?i:time';

        $this->c->DB->exec($query, $vars);

        return true;
    }

    /**
     * Поиск по словам рекурсивного списка
     */
    protected function exec(array $words, array $vars): array
    {
        $type  = 'AND';
        $count = 0;
        $ids   = [];

        foreach ($words as $word) {
            // служебное слово
            if (
                'AND' === $word
                || 'OR' === $word
                || 'NOT' === $word
            ) {
                $type = $word;

                continue;
            }

            // если до сих пор ни чего не найдено и тип операции не ИЛИ, то выполнять не надо
            if (
                $count
                && empty($ids)
                && 'OR' !== $type
            ) {
                continue;
            }

            if (
                \is_array($word)
                && (
                    ! isset($word['type'])
                    || 'CJK' !== $word['type']
                )
            ) {
                $ids = $this->exec($word, $vars);
            } else {
                $CJK = false;

                if (
                    isset($word['type'])
                    && 'CJK' === $word['type']
                ) {
                    $CJK  = true;
                    $word = '*' . \trim($word['word'], '*') . '*';
                }

                $word = \str_replace(['*', '?'], ['%', '_'], $word);

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

                if (! $count) {
                    $ids = $list;
                } elseif ('AND' === $type) {
                    $ids = \array_intersect_key($ids, $list);
                } elseif ('OR' === $type) {
                    $ids += $list;
                } elseif ('NOT' === $type) {
                    $ids = \array_diff_key($ids, $list);
                }
            }

            ++$count;
        }

        return $ids;
    }

    /**
     * Создание sql запросов к поисковому индексу и к сообщениям/темам
     */
    protected function buildSelect(Validator $v, array $forumIdxs): array
    {
        $vars     = [];
        $whereIdx = [];
        $whereCJK = [];
        $useTIdx  = false;
        $usePIdx  = false;
        $useTCJK  = false;
        $usePCJK  = false;
        $like     = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';

        if (
            '*' !== $v->forums
            || ! $this->c->user->isAdmin
        ) {
            $useTIdx                 = true;
            $whereIdx[]              = 't.forum_id IN (?ai:forums)';
            $whereCJK[]              = 't.forum_id IN (?ai:forums)';
            $useTCJK                 = true;
            $vars[':forums']         = '*' === $v->forums ? $forumIdxs : \explode('.', $v->forums);
        }

        //???? нужен индекс по авторам сообщений/тем?
        if ('*' !== $v->author) {
            $usePIdx                 = true;
            $vars[':author']         = \str_replace(['#', '%', '_', '*', '?'], ['##', '#%', '#_', '%', '_'], $v->author);
            $whereIdx[]              = "p.poster {$like} ?s:author ESCAPE '#'";
        }

        $this->model->showAs         = $v->show_as;

        switch ($v->serch_in) {
            case 1:
                $whereIdx[]          = 'sm.subject_match=0';
                $whereCJK[]          = "p.message {$like} ?s:word";
                $usePCJK             = true;

                if (isset($vars[':author'])) {
                    $whereCJK[]      = "p.poster {$like} ?s:author ESCAPE '#'";
                }

                break;
            case 2:
                $whereIdx[]          = 'sm.subject_match=1';
                $whereCJK[]          = "t.subject {$like} ?s:word";
                $useTCJK             = true;

                if (isset($vars[':author'])) {
                    $whereCJK[]      = "t.poster {$like} ?s:author ESCAPE '#'";
                }
                // при поиске в заголовках результат только в виде списка тем
                $this->model->showAs = 1;

                break;
            default:
                if (isset($vars[':author'])) {
                    $whereCJK[]      = "((p.message {$like} ?s:word AND p.poster {$like} ?s:author ESCAPE '#') OR (t.subject {$like} ?s:word AND t.first_post_id=p.id AND t.poster {$like} ?s:author ESCAPE '#'))";
                } else {
                    $whereCJK[]      = "(p.message {$like} ?s:word OR (t.subject {$like} ?s:word AND t.first_post_id=p.id))";
                }

                $usePCJK             = true;
                $useTCJK             = true;

                break;
        }

        if (1 === $this->model->showAs) {
            $usePIdx                 = true;
            $selectFIdx              = 'DISTINCT p.topic_id';
            $selectFCJK              = 'DISTINCT t.id';
            $useTCJK                 = true;
        } else {
            $selectFIdx              = 'sm.post_id';
            $selectFCJK              = 'p.id';
            $usePCJK                 = true;
        }

        switch ($v->sort_by) {
            case 1:
                if (1 === $this->model->showAs) {
                    $sortIdx         = 't.poster';
                    $sortCJK         = 't.poster';
                    $useTIdx         = true;
                    $useTCJK         = true;
                } else {
                    $sortIdx         = 'p.poster';
                    $sortCJK         = 'p.poster';
                    $usePIdx         = true;
                    $usePCJK         = true;
                }

                $this->sortType      = \SORT_STRING;

                break;
            case 2:
                $sortIdx             = 't.subject';
                $sortCJK             = 't.subject';
                $useTIdx             = true;
                $useTCJK             = true;
                $this->sortType      = \SORT_STRING;

                break;
            case 3:
                $sortIdx             = 't.forum_id';
                $sortCJK             = 't.forum_id';
                $useTIdx             = true;
                $useTCJK             = true;
                $this->sortType      = \SORT_NUMERIC;

                break;
            default:
                if (1 === $this->model->showAs) {
                    $sortIdx         = 't.last_post';
                    $sortCJK         = 't.last_post';
                    $useTIdx         = true;
                    $useTCJK         = true;
                } else {
                    $sortIdx         = 'sm.post_id';
                    $sortCJK         = 'p.id';
                    $usePCJK         = true;
                }

                $this->sortType      = \SORT_NUMERIC;

                break;
        }

        $usePIdx  = $usePIdx || $useTIdx ? 'INNER JOIN ::posts AS p ON p.id=sm.post_id '   : '';
        $useTIdx  = $useTIdx             ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '';
        $whereIdx = empty($whereIdx)     ? '' : ' AND ' . \implode(' AND ', $whereIdx);

        $this->queryIdx = "SELECT {$selectFIdx}, {$sortIdx} FROM ::search_words AS sw " .
                          'INNER JOIN ::search_matches AS sm ON sm.word_id=sw.id ' .
                          $usePIdx .
                          $useTIdx .
                          'WHERE sw.word LIKE ?s:word' . $whereIdx; // ILIKE не нужен, слово в ниж.регистре

        if ($usePCJK) {
            $this->queryCJK = "SELECT {$selectFCJK}, {$sortCJK} FROM ::posts AS p " .
                              ($useTCJK ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '') .
                              'WHERE ' . \implode(' AND ', $whereCJK);
        } else {
            $this->queryCJK = "SELECT {$selectFCJK}, {$sortCJK} FROM ::topics AS t " .
                              'WHERE ' . \implode(' AND ', $whereCJK);
        }

        return $vars;
    }
}
