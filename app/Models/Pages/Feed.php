<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use RuntimeException;
use function \ForkBB\__;

class Feed extends Page
{
    const MAX_LEN_CONT = 1000;

    /**
     * Возвращает шаблон с простым текстом
     */
    protected function exit(string $message, int $status = 404): Page
    {
        $this->plainText    = __($message);
        $this->nameTpl      = 'layouts/plain';
        $this->httpStatus   = \max(200, $status);
        $this->onlinePos    = 'feed';
        $this->onlineDetail = null;

        $this->header('Content-type', 'text/plain; charset=utf-8');

        return $this;
    }

    /**
     * Подготовка данных для шаблона
     */
    public function view(array $args, string $method): Page
    {
        $this->c->DEBUG = 0;

        if ($this->c->config->i_feed_type < 1) {
            return $this->exit('Bad request');
        }

        $fid = $args['fid'] ?? 0;
        $tid = $args['tid'] ?? 0;

        if ($fid > 0 && $tid > 0) {
            return $this->exit('Bad request');
        }

        if ($tid) {
            $topic = $this->c->topics->load($tid);

            if (! $topic instanceof Topic) {
                return $this->exit('Bad request');
            }

            $feed = [
                'id'            => $this->c->Router->link('Feed', $args),
                'title'         => $this->c->config->o_board_title . __('Title separator') . $topic->subject,
                'link'          => $topic->link,
                'description'   => __(['The most recent posts in %s topic', $topic->subject]),
                'updated'       => $topic->last_post,
                'items'         => [],
            ];

            $items = $this->c->posts->feed($topic);
            if (! empty($items)) {
                foreach ($items as $cur) {
                    $item  = [
                        'id'        => $this->c->Router->link('ViewPost', ['id' => $cur['pid']]),
                        'title'     => $topic->subject,
                        'updated'   => $cur['edited'] > $cur['posted'] ? $cur['edited'] : $cur['posted'],
                        'link'      => $this->c->Router->link('ViewPost', ['id' => $cur['pid']]),
                        'author'    => $cur['username'],
                        'content'   => $this->c->Parser->parseMessage($this->trimContent($cur['content']), (bool) $cur['hide_smilies']),
                        'published' => $cur['posted'],
                    ];

                    $feed['items'][] = $item;
                }
            }

        } else {
            if ($this->c->config->i_feed_ttl > 0) {
                $cacheId = 'feed' . \sha1("{$this->user->group_id}|{$this->user->language}|{$fid}");

            } else {
                $cacheId = null;
            }

            if (null !== $cacheId && $this->c->Cache->has($cacheId)) {
                $feed = $this->c->Cache->get($cacheId);

            } else {
                $forum = $this->c->forums->loadTree($fid);

                if (! $forum instanceof Forum) {
                    return $this->exit('Bad request');
                }

                $feed = [
                    'id'            => $this->c->Router->link('Feed', $args),
                    'title'         => $this->c->config->o_board_title,
                    'link'          => $forum->link,
                    'updated'       => $forum->tree->last_post,
                    'items'         => [],
                ];

                if (0 === $fid) {
                    $feed['description'] = __(['The most recent posts at %s board', $this->c->config->o_board_title]);

                } else {
                    $feed['description'] = __(['The most recent posts in %s forum', $forum->forum_name]);
                    $feed['title']      .= __('Title separator') . $forum->forum_name;
                }

                $items = $this->c->posts->feed($forum);

                if (! empty($items)) {
                    foreach ($items as $cur) {
                        $fName = $this->c->forums->get($cur['fid'])->forum_name;
                        $item  = [
                            'id'        => $this->c->Router->link('ViewPost', ['id' => $cur['pid']]),
                            'title'     => $fName . __('Title separator') . $cur['topic_name'],
                            'updated'   => $cur['edited'] > $cur['posted'] ? $cur['edited'] : $cur['posted'],
                            'link'      => $this->c->Router->link('ViewPost', ['id' => $cur['pid']]),
                            'author'    => $cur['username'],
                            'content'   => $this->c->Parser->parseMessage($this->trimContent($cur['content']), (bool) $cur['hide_smilies']),
                            'published' => $cur['posted'],
                        ];

                        $feed['items'][] = $item;
                    }
                }


                if (null !== $cacheId) {
                    if (true !== $this->c->Cache->set($cacheId, $feed, 60 * $this->c->config->i_feed_ttl)) {
                        throw new RuntimeException('Unable to write value to cache - feed');
                    }
                }
            }
        }

        $this->nameTpl      = "feed_{$args['type']}";
        $this->onlinePos    = 'feed';
        $this->onlineDetail = null;
        $this->feed         = $feed;

        $this->header('Content-type', "application/{$args['type']}+xml; charset=utf-8");

        return $this;
    }

    /**
     * Сокращает длину сообщения
     */
    protected function trimContent(string $text): string
    {
        if (\mb_strlen($text, 'UTF-8') > self::MAX_LEN_CONT) {
            $result = \mb_substr($text, 0, self::MAX_LEN_CONT, 'UTF-8');
            $length = \strrpos($result, ' ');

            if (\is_int($length)) {
                $result = \substr($result, 0, $length);
            }

            $result = \rtrim($result, "!,.-\n\t ");

            if (isset($result[0])) {
                $text = $result . '…';
            }
        }

        return $text;
    }
}
