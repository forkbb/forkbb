<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
        $mesWords = $this->words(\mb_strtolower($this->c->Parser->getText(), 'UTF-8'));
        $subWords = $post->id === $post->parent->first_post_id
            ? $this->words(\mb_strtolower($post->parent->subject, 'UTF-8'))
            : [];

        if ('add' !== $mode) {
            $vars = [
                ':pid' => $post->id,
            ];
            $query = 'SELECT sw.id, sw.word, sm.subject_match
                FROM ::search_words AS sw
                INNER JOIN ::search_matches AS sm ON sw.id=sm.word_id
                WHERE sm.post_id=?i:pid';

            $stmt = $this->c->DB->query($query, $vars);

            $mesCurWords = [];
            $subCurWords = [];

            while ($row = $stmt->fetch()) {
                if ($row['subject_match']) {
                    $subCurWords[$row['word']] = $row['id'];
                } else {
                    $mesCurWords[$row['word']] = $row['id'];
                }
            }
        }

        $words = [];

        if ('edit' === $mode) {
            $words['add']['p'] = \array_diff($mesWords, \array_keys($mesCurWords));
            $words['add']['s'] = \array_diff($subWords, \array_keys($subCurWords));
            $words['del']['p'] = \array_diff_key($mesCurWords, \array_flip($mesWords));
            $words['del']['s'] = \array_diff_key($subCurWords, \array_flip($subWords));
        } elseif ('merge' === $mode) {
            $words['add']['p'] = \array_diff($mesWords, \array_keys($mesCurWords));
            $words['add']['s'] = \array_diff($subWords, \array_keys($subCurWords));
            $words['del']['p'] = [];
            $words['del']['s'] = [];
        } else {
            $words['add']['p'] = $mesWords;
            $words['add']['s'] = $subWords;
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

    /**
     * Получение слов из текста для построения поискового индекса
     */
    protected function words(string $text): array
    {
        $text  = $this->model->cleanText($text, true);
        $words = [];

        foreach (\explode(' ', $text) as $word) {
            $word = $this->model->word($word, true);

            if (null !== $word) {
                $words[$word] = $word;
            }
        }

        return \array_values($words);
    }
}
