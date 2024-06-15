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
use ForkBB\Models\Model;
use ForkBB\Models\Post\Post;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\User\User;
use PDO;
use RuntimeException;

class Attachments extends Model
{
    const HTML_CONT = '<!DOCTYPE html><html lang="en"><head><title>.</title></head><body>.</body></html>';
    const BAD_EXTS  = '%^(?:php.*|pl|phar|pht|[ps]?html?|jsp?|htaccess|htpasswd|f?cgi|svg|)$%i';
    const FOLDER    = '/upload/';
    const PER_PAGE  = 20;

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

        $dir = $this->c->DIR_PUBLIC . self::FOLDER . "{$p1}/{$p2}";

        if (
            ! \is_dir($dir)
            && \mkdir($dir, 0755, true)
        ) {
            \file_put_contents("{$dir}/index.html", self::HTML_CONT);
        }

        $file->rename(false)->rewrite(false);

        if ($file instanceof Image) {
            $file->setQuality($this->c->config->i_upload_img_quality ?? 75)
                ->resize($this->c->config->i_upload_img_axis_limit, $this->c->config->i_upload_img_axis_limit);

            if (! empty($this->c->config->s_upload_img_outf)) {
                $ext = '(' . \strtr($this->c->config->s_upload_img_outf, [',' => '|']) . ')';
            }
        }

        $status = $file->toFile("{$dir}/{$p3}.{$ext}");

        if (true !== $status) {
            $this->c->Log->warning("Attachments Failed processing {$p1}/{$p2}/{$p3}.{$ext}", [
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

        $location = $file->path();
        $path     = "{$p1}/{$p2}/{$file->name()}.{$file->ext()}";
        $size     = $this->c->Files->size(\filesize($location), 'K');

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
            if (true === $editPost) {
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

            $query = match ($this->c->DB->getType()) {
                'mysql' => "INSERT IGNORE INTO {$table} (id, pid)
                    VALUES (?i:id, ?i:pid)",

                'sqlite', 'pgsql' => "INSERT INTO {$table} (id, pid)
                    VALUES (?i:id, ?i:pid)
                    ON CONFLICT(id, pid) DO NOTHING",

                default => "INSERT INTO {$table} (id, pid)
                    SELECT tmp.*
                    FROM (SELECT ?i:id AS f1, ?i:pid AS f2) AS tmp
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM {$table}
                        WHERE id=?i:id AND pid=?i:pid
                    )",
            };

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

    /**
     * Пересчитывает объём файлов пользователя
     */
    public function recalculate(User $user): void
    {
        $vars = [
            ':uid' => $user->id,
        ];
        $query = 'SELECT SUM(size_kb) FROM ::attachments WHERE uid=?i:uid';

        $user->u_up_size_mb = (int) round($this->c->DB->query($query, $vars)->fetchColumn() / 1024);

        $this->c->users->update($user); //???? оптимизировать?
    }

    /**
     * Количество страниц для просмотра файлов
     */
    protected function getnumPages(): int
    {
        $query = 'SELECT COUNT(id) FROM ::attachments';

        $this->fileCount = (int) $this->c->DB->query($query)->fetchColumn();

        return (int) \ceil(($this->fileCount ?: 1) / self::PER_PAGE);
    }

    /**
     * Статус наличия установленной страницы
     */
    public function hasPage(): bool
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Массив страниц
     */
    protected function getpagination(): array
    {
        return $this->c->Func->paginate(
            $this->numPages,
            $this->page,
            'AdminUploads',
            ['#' => 'filelist']
        );
    }

    /**
     * Возвращает массив данных с установленной страницы
     */
    public function pageData(): array
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        if (empty($this->fileCount)) {
            $this->idsList = [];

            return [];
        }

        $vars = [
            ':offset' => ($this->page - 1) * self::PER_PAGE,
            ':rows'   => self::PER_PAGE,
        ];
        $query = "SELECT id
            FROM ::attachments
            ORDER BY id DESC
            LIMIT ?i:rows OFFSET ?i:offset";

        $this->idsList = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        if (empty($this->idsList)) {
            return [];
        }

        $vars = [
            ':ids' => $this->idsList,
        ];
        $query = 'SELECT * FROM ::attachments WHERE id IN (?ai:ids)';

        $stmt = $this->c->DB->query($query, $vars);
        $data = [];

        while ($row = $stmt->fetch()) {
            $data[$row['id']] = $row;
        }

        return $data;
    }

    /**
     * Возвращает массив данных о файле по его номеру или null
     */
    public function fileInfo(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $vars  = [
            'id' => $id,
        ];
        $query = 'SELECT * FROM ::attachments WHERE id=?i:id';
        $info  = $this->c->DB->query($query, $vars)->fetch();

        if (empty($info)) {
            return null;
        }

        $query = 'SELECT pid FROM ::attachments_pos WHERE id=?i:id';
        $pids  = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $query = 'SELECT pid FROM ::attachments_pos_pm WHERE id=?i:id';
        $pmids = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return [
            'id'       => $id,
            'uid'      => $info['uid'],
            'created'  => $info['created'],
            'size_kb'  => $info['size_kb'],
            'path'     => $info['path'],
            'url'      => $this->c->PUBLIC_URL . self::FOLDER . $info['path'],
            'location' => $this->c->DIR_PUBLIC . self::FOLDER . $info['path'],
            'pids'     => $pids,
            'pmids'    => $pmids,
        ];
    }

    /**
     * Удаляет файл
     */
    public function deleteFile(int $id): bool
    {
        $info = $this->fileInfo($id);

        if (empty($info)) {
            return false;
        }

        if (
            ! \is_file($info['location'])
            || \unlink($info['location'])
        ) {
            $vars  = [
                'id' => $id,
            ];

            if (! empty($info['pids'])) {
                $query = 'DELETE FROM ::attachments_pos WHERE id=?i:id';

                $this->c->DB->exec($query, $vars);
            }

            if (! empty($info['pmids'])) {
                $query = 'DELETE FROM ::attachments_pos_pm WHERE id=?i:id';

                $this->c->DB->exec($query, $vars);
            }

            $query = 'DELETE FROM ::attachments WHERE id=?i:id';

            $this->c->DB->exec($query, $vars);

            $user = $this->c->users->load($info['uid']);

            if (
                $user instanceof User
                && ! $user->isGuest
            ) {
                $this->recalculate($user);
            }

            return true;
        } else {
            return false;
        }
    }
}
