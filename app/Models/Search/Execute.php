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
use ForkBB\Models\Method;
use PDO;
use RuntimeException;

class Execute extends Method
{
    protected int $sortType;
    protected array $wordsCache = [];

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

        $delimiter = \time() - $this->c->config->i_search_ttl;
        $structure = $this->buildSelect($v, $forumIdxs);

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

        $ids = $this->exec($this->model->queryWords, $structure);

        if (1 === $v->sort_dir) {
            \asort($ids, $structure['sortType']);
        } else {
            \arsort($ids, $structure['sortType']);
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
    protected function exec(array $words, array $structure): array
    {
        $ids = $this->execRaw($words, $structure);

        if (
            ! empty($ids)
            && ! empty($structure[':forums'])
        ) {
            $vars = [
                ':forums' => $structure[':forums'],
                ':ids'    => \implode(',', \array_map('\\intval', $ids)),
            ];

            $ids = $this->c->DB->query($structure['queryForums'], $vars)->fetchAll(PDO::FETCH_COLUMN);
        }

        if (
            ! empty($ids)
            && ! empty($structure[':author'])
        ) {
            $vars = [
                ':author' => $structure[':author'],
                ':ids'    => \implode(',', \array_map('\\intval', $ids)),
            ];

            $ids = $this->c->DB->query($structure['queryAuthor'], $vars)->fetchAll(PDO::FETCH_COLUMN);
        }

        if (! empty($ids)) {
            if (null === $structure['queryResult']) {
                return \array_combine($ids, $ids);
            } else {
                $vars = [
                    ':ids' => \implode(',', \array_map('\\intval', $ids)),
                ];

                $ids = $this->c->DB->query($structure['queryResult'], $vars)->fetchAll(PDO::FETCH_KEY_PAIR);
            }
        }

        return $ids;
    }

    /**
     * Поиск по словам рекурсивного списка без ограничителей
     */
    protected function execRaw(array $words, array $structure): array
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

            // если до сих пор ни чего не найдено и тип операции не ИЛИ, то выполнять не надо(?)
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
                $list = $this->execRaw($word, $structure);
            } else {
                $reqLike = false;

                if (
                    isset($word['type'])
                    && 'LIKE' === $word['type']
                ) {
                    $reqLike  = true;
                    $subWords = $this->model->words($word['word'], true);
                    $word     = \str_replace(['#', '%', '_'], ['##', '#%', '#_'], $word['word']);
                    $word     = "%{$word}%";
                } else {
                    $word     = \str_replace(['*', '?'], ['%', '_'], $word);
                }

                if (isset($this->wordsCache[$word])) {
                    $list = $this->wordsCache[$word];
                } else {
                    if (true === $reqLike) {
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

                            $list = $this->execRaw($list, $structure);
                        }

                        if (empty($list)) {
                            $this->wordsCache[$word] = [];
                        } else {
                            $vars = [
                                ':list' => \implode(',', \array_map('\\intval', $list)),
                                ':word' => $word,
                            ];

                            $this->wordsCache[$word] = $list = $this->c->DB->query($structure['queryLikeRaw'], $vars)->fetchAll(PDO::FETCH_COLUMN);
                        }
                    } else {
                        $vars = [
                            ':word' => $word,
                        ];
                        $query = 'SELECT id FROM ::search_words WHERE word LIKE ?s:word';

                        $list = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

                        if (empty($list)) {
                            $this->wordsCache[$word] = [];
                        } else {
                            $vars = [
                                ':list' => \implode(',', \array_map('\\intval', $list)),
                            ];

                            $this->wordsCache[$word] = $list = $this->c->DB->query($structure['queryIndxRaw'], $vars)->fetchAll(PDO::FETCH_COLUMN);
                        }
                    }
                }
            }

            if (! $count) {
                $ids = \array_flip($list);
            } elseif ('AND' === $type) {
                $ids = \array_intersect_key($ids, \array_flip($list));
            } elseif ('OR' === $type) {
                $ids += \array_flip($list);
            } elseif ('NOT' === $type) {
                $ids = \array_diff_key($ids, \array_flip($list));
            }

            ++$count;
        }

        return \array_keys($ids);
    }

    /**
     * Создание sql запросов к поисковому индексу и к сообщениям/темам
     */
    protected function buildSelect(Validator $v, array $forumIdxs): array
    {
        $out  = [];
        $like = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';
        $useT = false;

        if (
            '*' !== $v->forums
            || ! $this->c->user->isAdmin
        ) {
            $out[':forums']     = '*' === $v->forums ? $forumIdxs : \explode('.', $v->forums);
            $out['queryForums'] = 'SELECT p.id FROM ::posts AS p ' .
                                  'INNER JOIN ::topics AS t ON t.id=p.topic_id ' .
                                  'WHERE p.id IN (?p:ids) AND t.forum_id IN (?ai:forums)';
        }

        //???? нужен индекс по авторам сообщений/тем?
        if ('*' !== $v->author) {
            $out[':author']     = \str_replace(['#', '%', '_', '*', '?'], ['##', '#%', '#_', '%', '_'], $v->author);
            $out['queryAuthor'] = "SELECT id FROM ::post WHERE id IN (?p:ids) AND poster {$like} ?s:author ESCAPE '#'";
        }

        $this->model->showAs = $v->show_as;

        switch ($v->serch_in) {
            case 1:
                $out['queryIndxRaw']  = 'SELECT post_id FROM ::search_matches WHERE word_id IN (?p:list) AND subject_match=0';
                $out['queryLikeRaw']  = "SELECT id FROM ::posts WHERE id IN (?p:list) AND message {$like} ?s:word ESCAPE '#'";

                break;
            case 2:
                $out['queryIndxRaw']  = 'SELECT post_id FROM ::search_matches WHERE word_id IN (?p:list) AND subject_match=1';
                $out['queryLikeRaw']  = "SELECT first_post_id FROM ::topics WHERE first_post_id IN (?p:list) AND subject {$like} ?s:word ESCAPE '#'";

                // при поиске в заголовках результат только в виде списка тем
                $this->model->showAs = 1;

                break;
            default:
                $out['queryIndxRaw']  = 'SELECT post_id FROM ::search_matches WHERE word_id IN (?p:list)';
                $out['queryLikeRaw']  = "SELECT id FROM ::posts WHERE id IN (?p:list) AND message {$like} ?s:word ESCAPE '#'" .
                                        ' UNION ' .
                                        "SELECT first_post_id FROM ::topics WHERE first_post_id IN (?p:list) AND subject {$like} ?s:word ESCAPE '#'";

                break;
        }

        if (1 === $this->model->showAs) {
            $key = 'DISTINCT p.topic_id';
        } else {
            $key = 'p.id';
        }

        switch ($v->sort_by) {
            case 1:
                $value           = 'p.poster';
                $out['sortType'] = \SORT_STRING;

                break;
            case 2:
                $value           = 't.subject';
                $useT            = true;
                $out['sortType'] = \SORT_STRING;

                break;
            case 3:
                $value           = 't.forum_id';
                $useT            = true;
                $out['sortType'] = \SORT_NUMERIC;

                break;
            default:
                if (1 === $this->model->showAs) {
                    $value       = 't.last_post';
                    $useT        = true;
                } else {
                    $value       = 'p.id';
                }

                $out['sortType'] = \SORT_NUMERIC;

                break;
        }

        if ($key === $value) {
            $out['queryResult'] = null;
        } else {
            $useT               = $useT ? 'INNER JOIN ::topics AS t ON t.id=p.topic_id ' : '';
            $out['queryResult'] = "SELECT {$key}, {$value} FROM ::posts AS p " .
                                  $useT .
                                  'WHERE p.id IN (?p:ids)';
        }

        return $out;
    }
}
