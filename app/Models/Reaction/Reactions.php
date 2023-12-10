<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Reaction;

use ForkBB\Models\Manager;
use ForkBB\Models\Post\Post;
use PDO;
use RuntimeException;

class Reactions extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Reactions';

    /**
     * Генериует массив данных для отображения реакции (и формы для неё) на сообщение
     */
    public function generateForm(Post $post, bool $allow = true): ?array
    {
        $types  = $this->c->config->a_reaction_types;
        $result = [
            'action'  => null,
            'visible' => [],
            'hidden'  => [],
        ];

        if (! empty($post->reactions)) {
            foreach (\explode('|', $post->reactions) as $keyval) {
                list($key, $val) = \explode(':', $keyval);

                $result['visible'][$types[$key][0]] = [$val, $allow && $post->useReaction && $types[$key][1]];
            }
        }

        if (
            true === $allow
            && $post->useReaction
        ) {
            $result['action'] = $this->c->Router->link('Reaction', ['id' => $post->id]);

            foreach ($types as $type) {
                if (
                    true === $type[1]
                    && ! isset($result['visible'][$type[0]])
                ) {
                    $result['hidden'][$type[0]] = [0, true];
                }
            }
        }

        return empty($result['visible']) && empty($result['hidden']) ? null : $result;
    }

    /**
     * Вносит изменения в реакцию на сообщение
     */
    public function reaction(Post $post, int $type): ?bool
    {
        $vars = [
            ':pid'  => $post->id,
            ':uid'  => $this->c->user->id,
            ':type' => $type,
        ];
        $query = 'SELECT reaction FROM ::reactions WHERE pid=?i:pid AND uid=?i:uid';

        $old = (int) $this->c->DB->query($query, $vars)->fetchColumn();

        if ($old === $type) {
            $result = false;
            $query  = 'DELETE FROM ::reactions WHERE pid=?i:pid AND uid=?i:uid';
        } elseif ($old > 0) {
            $result = null;
            $query  = 'UPDATE ::reactions SET reaction=?i:type WHERE pid=?i:pid AND uid=?i:uid';
        } else {
            $result = true;
            $query  = match ($this->c->DB->getType()) {
                'mysql' => 'INSERT IGNORE INTO ::reactions (pid, uid, reaction)
                    VALUES (?i:pid, ?i:uid, ?i:type)',

                'sqlite', 'pgsql' => 'INSERT INTO ::reactions (pid, uid, reaction)
                    VALUES (?i:pid, ?i:uid, ?i:type)
                    ON CONFLICT(pid, uid) DO NOTHING',

                default => 'INSERT INTO ::reactions (pid, uid, reaction)
                    SELECT tmp.*
                    FROM (SELECT ?i:pid AS f1, ?i:uid AS f2, ?i:type AS f3) AS tmp
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM ::reactions
                        WHERE pid=?i:pid AND uid=?i:uid
                    )',
            };
        }

        $this->c->DB->exec($query, $vars);

        $this->recalcReactions($post);

        return $result;
    }

    /**
     * Генерирует данные в поле reactions сообщения на основе БД
     */
    public function recalcReactions(Post $post): bool
    {
        $vars = [
            ':pid'  => $post->id,
        ];
        $query = 'SELECT reaction, COUNT(reaction) FROM ::reactions WHERE pid=?i:pid GROUP BY reaction';

        $reactions = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_KEY_PAIR);

        \arsort($reactions, \SORT_NUMERIC);

        $result = '';
        $delim  = '';

        foreach ($reactions as $key => $value) {
            $result .= $delim . $key . ':' . $value;
            $delim   = '|';
        }

        $post->reactions = $result;

        $this->c->posts->update($post);

        return true;
    }
}
