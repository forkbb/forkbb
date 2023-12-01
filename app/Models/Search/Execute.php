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
    protected string $queryIndx;
    protected string $queryLike;
    protected int $sortType;
    protected array $words;
    protected ?DBStatement $stmtIndx;
    protected ?DBStatement $stmtLike;

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

        $delimiter      = \time() - $this->c->config->i_search_ttl;
        $this->words    = [];
        $this->stmtIndx = null;
        $this->stmtLike = null;
        $queryVars      = $this->buildSelect($v, $forumIdxs);

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
                0 !== $count
                && empty($ids)
                && 'OR' !== $type
            ) {
                continue;
            }

            if (
                \is_array($word)
                && ! isset($word['type'])
                && ! isset($word['word'])
            ) {
                $list = $this->exec($word, $vars);
            } else {
                $reqLike = false;

                if (
                    isset($word['type'])
                    && 'LIKE' === $word['type']
                ) {
                    $reqLike  = true;
                    $subWords = $this->model->words($word['word'], true);
                    $word     = '*' . \trim($word['word'], '*') . '*';
                }

                $word = \str_replace(['*', '?'], ['%', '_'], $word);

                if (isset($this->words[$word])) {
                    $list = $this->words[$word];
                } else {
                    $vars[':word'] = $word;

                    if ($reqLike) {
                        $list = [];

                        foreach ($subWords as $cur) {
                            if ($this->model->isCJKWord($cur)) {
                                $list[] = $cur;
                            } else {
                                $list[] = "{$cur}*";
                            }
                        }

                        if (! empty($list)) {
                            \usort($list, function ($a, $b) {
                                return \mb_strlen($b, 'UTF-8') <=> \mb_strlen($a, 'UTF-8');
                            });

                            $list = \array_keys($this->exec($list, $vars));
                        }

                        if (empty($list)) {
                            $this->words[$word] = [];
                        } elseif (\count($list) > 60000) {
                            $this->model->queryError = 'Too many coincidences';

                            return [];
                        } else {
                            $vars[':list'] = $list;

                            if (null === $this->stmtLike) {
                                $this->stmtLike = $this->c->DB->prepare($this->queryLike, $vars);
                                $this->stmtLike->execute();
                            } else {
                                $this->stmtLike->execute($vars);
                            }

                            $this->words[$word] = $list = $this->stmtLike->fetchAll(PDO::FETCH_KEY_PAIR);
                        }
                    } else {
                        $list = $this->c->DB->query('SELECT id FROM ::search_words WHERE word LIKE ?s', [$word])->fetchAll(PDO::FETCH_COLUMN);

                        if (empty($list)) {
                            $this->words[$word] = [];
                        } elseif (\count($list) > 60000) {
                            $this->model->queryError = 'Too many coincidences';

                            return [];
                        } else {
                            $vars[':list'] = $list;

                            if (null === $this->stmtIndx) {
                                $this->stmtIndx = $this->c->DB->prepare($this->queryIndx, $vars);
                                $this->stmtIndx->execute();
                            } else {
                                $this->stmtIndx->execute($vars);
                            }

                            $this->words[$word] = $list = $this->stmtIndx->fetchAll(PDO::FETCH_KEY_PAIR);
                        }
                    }
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

            ++$count;
        }

        return $ids;
    }

    /**
     * Создание sql запросов к поисковому индексу и к сообщениям/темам
     */
    protected function buildSelect(Validator $v, array $forumIdxs): array
    {
        $vars      = [];
        $whereIndx = [];
        $whereLike = [];
        $useTIndx  = false;
        $usePIndx  = false;
        $useTLike  = false;
        $usePLike  = false;
        $like      = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';

        if (
            '*' !== $v->forums
            || ! $this->c->user->isAdmin
        ) {
            $useTIndx                = true;
            $whereIndx[]             = 't.forum_id IN (?ai:forums)';
//            $whereLike[]             = 't.forum_id IN (?ai:forums)';
//            $useTLike                = true;
            $vars[':forums']         = '*' === $v->forums ? $forumIdxs : \explode('.', $v->forums);
        }

        //???? нужен индекс по авторам сообщений/тем?
        if ('*' !== $v->author) {
            $usePIndx                = true;
            $vars[':author']         = \str_replace(['#', '%', '_', '*', '?'], ['##', '#%', '#_', '%', '_'], $v->author);
            $whereIndx[]             = "p.poster {$like} ?s:author ESCAPE '#'";
        }

        $this->model->showAs         = $v->show_as;

        switch ($v->serch_in) {
            case 1:
                $whereIndx[]         = 'sm.subject_match=0';
                $whereLike[]         = "p.message {$like} ?s:word";
                $usePLike            = true;

                if (isset($vars[':author'])) {
                    $whereLike[]     = "p.poster {$like} ?s:author ESCAPE '#'";
                }

                break;
            case 2:
                $whereIndx[]         = 'sm.subject_match=1';
                $whereLike[]         = "t.subject {$like} ?s:word";
                $useTLike            = true;

                if (isset($vars[':author'])) {
                    $whereLike[]     = "t.poster {$like} ?s:author ESCAPE '#'";
                }
                // при поиске в заголовках результат только в виде списка тем
                $this->model->showAs = 1;

                break;
            default:
                if (isset($vars[':author'])) {
                    $whereLike[]     = "((p.message {$like} ?s:word AND p.poster {$like} ?s:author ESCAPE '#') OR (t.subject {$like} ?s:word AND t.first_post_id=p.id AND t.poster {$like} ?s:author ESCAPE '#'))";
                } else {
                    $whereLike[]     = "(p.message {$like} ?s:word OR (t.subject {$like} ?s:word AND t.first_post_id=p.id))";
                }

                $usePLike            = true;
                $useTLike            = true;

                break;
        }

        if (1 === $this->model->showAs) {
            $usePIndx                = true;
            $selectFIndx             = 'DISTINCT p.topic_id';
            $selectFLike             = 'DISTINCT t.id';
            $useTLike                = true;
            $whereLike[]             = 't.id IN (?ai:list)';
        } else {
            $selectFIndx             = 'sm.post_id';
            $selectFLike             = 'p.id';
            $usePLike                = true;
            $whereLike[]             = 'p.id IN (?ai:list)';
        }

        switch ($v->sort_by) {
            case 1:
                if (1 === $this->model->showAs) {
                    $sortIndx        = 't.poster';
                    $sortLike        = 't.poster';
                    $useTIndx        = true;
                    $useTLike        = true;
                } else {
                    $sortIndx        = 'p.poster';
                    $sortLike        = 'p.poster';
                    $usePIndx        = true;
                    $usePLike        = true;
                }

                $this->sortType      = \SORT_STRING;

                break;
            case 2:
                $sortIndx            = 't.subject';
                $sortLike            = 't.subject';
                $useTIndx            = true;
                $useTLike            = true;
                $this->sortType      = \SORT_STRING;

                break;
            case 3:
                $sortIndx            = 't.forum_id';
                $sortLike            = 't.forum_id';
                $useTIndx            = true;
                $useTLike            = true;
                $this->sortType      = \SORT_NUMERIC;

                break;
            default:
                if (1 === $this->model->showAs) {
                    $sortIndx        = 't.last_post';
                    $sortLike        = 't.last_post';
                    $useTIndx        = true;
                    $useTLike        = true;
                } else {
                    $sortIndx        = 'sm.post_id';
                    $sortLike        = 'p.id';
                    $usePLike        = true;
                }

                $this->sortType      = \SORT_NUMERIC;

                break;
        }

        $usePIndx  = $usePIndx || $useTIndx ? 'INNER JOIN ::posts AS p ON p.id=sm.post_id '   : '';
        $useTIndx  = $useTIndx              ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '';
        $whereIndx = empty($whereIndx)      ? '' : ' AND ' . \implode(' AND ', $whereIndx);

        $this->queryIndx = "SELECT {$selectFIndx}, {$sortIndx} FROM ::search_matches AS sm " .
                           $usePIndx .
                           $useTIndx .
                           'WHERE sm.word_id IN (?ai:list)' . $whereIndx; // ILIKE не нужен, слово в ниж.регистре

        if ($usePLike) {
            $this->queryLike = "SELECT {$selectFLike}, {$sortLike} FROM ::posts AS p " .
                               ($useTLike ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '') .
                               'WHERE ' . \implode(' AND ', $whereLike);
        } else {
            $this->queryLike = "SELECT {$selectFLike}, {$sortLike} FROM ::topics AS t " .
                               'WHERE ' . \implode(' AND ', $whereLike);
        }

        return $vars;
    }
}
