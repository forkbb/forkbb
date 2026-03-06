<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Post\Post;
use PDO;

class Index extends Method
{
    /**
     * Индексация сообщения/темы
     */
    public function index(Post $post, string $mode = 'add'): void
    {
        //???? пост после валидации должен иметь дерево тегов
        $mesgWords = $this->model->words(\mb_strtolower($this->c->Parser->getText(), 'UTF-8'), true);

        if ($post->id === $post->parent->first_post_id) {
            $subj = $post->parent->subject;

            if (
                1 === $this->c->config->b_topic_hashtags
                && ! empty($post->parent->hashtags)
            ) {
                $subj .= ' #' . \str_replace(' ', ' #', $post->parent->hashtags);
            }

            $subjWords = $this->model->words(\mb_strtolower($subj, 'UTF-8'), true);

        } else {
            $subjWords = [];
        }

        if ('add' !== $mode) {
            $vars = [
                ':pid' => $post->id,
            ];
            $query = 'SELECT sw.id, sw.word, sm.subject_match
                FROM ::search_words AS sw
                INNER JOIN ::search_matches AS sm ON sw.id=sm.word_id
                WHERE sm.post_id=?i:pid';

            $stmt = $this->c->DB->query($query, $vars);

            $mesgCurWords = [];
            $subjCurWords = [];

            while ($row = $stmt->fetch()) {
                if ($row['subject_match']) {
                    $subjCurWords[$row['word']] = $row['id'];

                } else {
                    $mesgCurWords[$row['word']] = $row['id'];
                }
            }
        }

        $words = [];

        if ('edit' === $mode) {
            $words['add']['p'] = \array_diff($mesgWords, \array_keys($mesgCurWords));
            $words['add']['s'] = \array_diff($subjWords, \array_keys($subjCurWords));
            $words['del']['p'] = \array_diff_key($mesgCurWords, \array_flip($mesgWords));
            $words['del']['s'] = \array_diff_key($subjCurWords, \array_flip($subjWords));

        } elseif ('merge' === $mode) {
            $words['add']['p'] = \array_diff($mesgWords, \array_keys($mesgCurWords));
            $words['add']['s'] = \array_diff($subjWords, \array_keys($subjCurWords));
            $words['del']['p'] = [];
            $words['del']['s'] = [];

        } else {
            $words['add']['p'] = $mesgWords;
            $words['add']['s'] = $subjWords;
            $words['del']['p'] = [];
            $words['del']['s'] = [];
        }

        if (empty($words['add']['s'])) {
            $allWords = $words['add']['p'];

        } else {
            $allWords = \array_unique(\array_merge($words['add']['p'], $words['add']['s']));
        }

        if (! empty($allWords)) {
            $vars = [
                ':words' => $allWords,
            ];
            $query = 'SELECT sw.word
                FROM ::search_words AS sw
                WHERE sw.word IN (?as:words)';

            $oldWords = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
            $newWords = \array_diff($allWords, $oldWords);

            if (! empty($newWords)) {
                $query = 'INSERT INTO ::search_words (word)
                    VALUES(?s:word)';
                $stmt  = null;

                foreach ($newWords as $word) {
                    if (null === $stmt) {
                        $stmt = $this->c->DB->prepare($query, [':word' => $word]);

                        $stmt->execute();

                    } else {
                        $stmt->execute([':word' => $word]);
                    }
                }

                $stmt  = null;
            }
        }

        foreach ($words['del'] as $key => $list) {
            if (empty($list)) {
                continue;
            }

            if (\count($list) > 1) {
                \sort($list, \SORT_NUMERIC);
            }

            $vars = [
                ':pid'  => $post->id,
                ':subj' => 's' === $key ? 1 : 0,
                ':ids'  => $list,
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE word_id IN (?ai:ids) AND post_id=?i:pid AND subject_match=?i:subj';

            $this->c->DB->exec($query, $vars);
        }

        foreach ($words['add'] as $key => $list)
        {
            if (empty($list)) {
                continue;
            }

            $vars = [
                ':pid'   => $post->id,
                ':subj'  => 's' === $key ? 1 : 0,
                ':words' => $list,
            ];
            $query = 'INSERT INTO ::search_matches (post_id, word_id, subject_match)
                SELECT ?i:pid, id, ?i:subj
                FROM ::search_words
                WHERE word IN (?as:words)';

            $this->c->DB->exec($query, $vars);
        }
    }
}
