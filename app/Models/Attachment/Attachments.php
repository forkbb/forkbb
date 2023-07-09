<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Attachment;

use ForkBB\Core\File;
use ForkBB\Core\Image;
use ForkBB\Models\Manager;
use ForkBB\Models\Post\Post;
use ForkBB\Models\PM\PPost;
use PDO;
use RuntimeException;

class Attachments extends Manager
{
    const HTML_CONT = '<!DOCTYPE html><html lang="en"><head><title>.</title></head><body>.</body></html>';
    const BAD_EXTS  = '%^(?:php.*|phar|[ps]?html?|jsp?|htaccess|htpasswd|f?cgi|)$%i';
    const FOLDER    = '/upload/';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Attachments';

    /**
     * Сохраняет загруженный файл
     */
    public function addFile(File $file): ?array
    {
        $ext  = $file->ext();

        if (\preg_match(self::BAD_EXTS, $ext)) {
            return null;
        }

        $uid  = $this->c->user->id;
        $now  = \time();
        $name = $this->c->Files->filterName($file->name());

        $vars = [
            'uid'     => $uid,
            'created' => $now,
            'uip'     => $this->c->user->ip,
        ];
        $query = 'INSERT INTO ::attachments (uid, created, uip) VALUES (?i:uid, ?i:created, ?s:uip)';

        $this->c->DB->exec($query, $vars);

        $id = (int) $this->c->DB->lastInsertId();

        $p1 = \date('ym');
        $p2 = (int) ($id / 1000);
        $p3 = \substr($name, 0, 235 - \strlen($ext)) . '_' . \sprintf("%03d", $id - $p2);

        $path     = "{$p1}/{$p2}/{$p3}.{$ext}";
        $location = $this->c->DIR_PUBLIC . self::FOLDER . $path;

        if (
            ! \is_dir($dir = \implode('/', \explode('/', $location, -1)))
            && \mkdir($dir, 0755, true)
        ) {
            \file_put_contents("{$dir}/index.html", self::HTML_CONT);
        }

        $file->rename(false)->rewrite(false);

        if ($file instanceof Image) {
            $file->setQuality($this->c->config->i_upload_img_quality ?? 75)
                ->resize($this->c->config->i_upload_img_axis_limit, $this->c->config->i_upload_img_axis_limit);
        }

        $status = $file->toFile($location);

        if (true !== $status) {
            $this->c->Log->warning("Attachments Failed processing {$path}", [
                'user'    => $this->user->fLog(),
                'error'   => $file->error(),
            ]);

            $vars = [
                ':id' => $id,
            ];
            $query = "DELETE FROM ::attachments WHERE id=?i:id";

            $this->c->DB->exec($query, $vars);

            return null;
        }

        $size = $this->c->Files->size(\filesize($location), 'K');
        $vars = [
            ':id'      => $id,
            ':path'    => $path,
            ':size_kb' => $size,
        ];
        $query = 'UPDATE ::attachments SET size_kb=?i:size_kb, path=?s:path WHERE id=?i:id';

        $this->c->DB->exec($query, $vars);

        return [
            'id'       => $id,
            'uid'      => $uid,
            'created'  => $now,
            'size_kb'  => $size,
            'path'     => $path,
            'location' => $location,
            'url'      => $this->c->PUBLIC_URL . self::FOLDER . $path,
            'image'    => $file instanceof Image,
        ];
    }

    /**
     * Синхронизирует информацию о вложениях в постах и лс
     */
    public function syncWithPost(Post|PPost $post, bool $editPost = false)
    {
        if ($post->id < 1) {
            return;
        }

        \preg_match_all('%' . self::FOLDER . '((\d{4})/(\d+)/[\w-]+?_(\d{3})\.[\w-]+)\b%', $post->message, $matches, \PREG_SET_ORDER); // ???? проверять html?

        $attInPost = [];

        foreach ($matches as $match) {
            $attInPost[1000 * $match[3] + $match[4]] = $match[1];
        }

        $table = $post instanceof PPost ? '::attachments_pos_pm' : '::attachments_pos';

        if (empty($attInPost)) {
            if (true === $ditPost) {
                $vars = [
                    ':pid' => $post->id
                ];
                $query = "DELETE FROM {$table} WHERE pid=?i:pid";

                $this->c->DB->exec($query, $vars);
            }
        } else {
            $ids  = \array_keys($attInPost);
            $vars = [
                ':ids' => $ids,
            ];
            $query = 'SELECT id, path FROM ::attachments WHERE id IN (?ai:ids)';
            $attInDB = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_KEY_PAIR);
            $ids = [];

            foreach ($attInDB as $id => $path) {
                if ($path === $attInPost[$id]) {
                    $ids[$id] = $id;
                } else {
                    $this->c->Log->warning("Attachments Sync Path do not match id={$id}", [
                        'user'       => $this->user->fLog(),
                        'pathInDB'   => $path,
                        'pathInPost' => $attInPost[$id],
                    ]);
                }

                unset($attInPost[$id]);
            }

            if (! empty($attInPost)) {
                $this->c->Log->warning("Attachments Sync Unknown paths id={$id}", [
                    'user'       => $this->user->fLog(),
                    '$attInPost' => $attInPost,
                ]);
            }

            switch ($this->c->DB->getType()) {
                case 'mysql':
                    $query = "INSERT IGNORE INTO {$table} (id, pid)
                        VALUES (?i:id, ?i:pid)";

                    break;
                case 'sqlite':
                case 'pgsql':
                    $query = "INSERT INTO {$table} (id, pid)
                        VALUES (?i:id, ?i:pid)
                        ON CONFLICT(id, pid) DO NOTHING";

                    break;
                default:
                    $query = "INSERT INTO {$table} (id, pid)
                        SELECT tmp.*
                        FROM (SELECT ?i:id AS f1, ?i:pid AS f2) AS tmp
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM {$table}
                            WHERE id=?i:id AND pid=?i:pid
                        )";

                    break;
            }

            foreach ($ids as $id) {
                $vars = [
                    ':id'  => $id,
                    ':pid' => $post->id,
                ];

                $this->c->DB->exec($query, $vars);
            }

            if (true === $editPost) {
                $vars = [
                    ':pid' => $post->id,
                    ':ids' => $ids,
                ];
                $query = "DELETE FROM {$table} WHERE pid=?i:pid AND id NOT IN (?ai:ids)";

                $this->c->DB->exec($query, $vars);
            }
        }

        return;
    }
}
