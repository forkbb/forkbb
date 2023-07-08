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
use ForkBB\Models\User\User;
use RuntimeException;

class Attachments extends Manager
{
    const HTML_CONT = '<!DOCTYPE html><html lang="en"><head><title>.</title></head><body>.</body></html>';
    const BAD_EXTS  = '%^(?:php.*|phar|phtml?|s?html?|jsp?|htaccess|htpasswd|f?cgi|)$%i';
    const FOLDER    = '/upload/';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Attachments';

    /**
     * Сохраняет файл
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
        ];
        $query = 'INSERT INTO ::attachments (uid, created) VALUES (?i:uid, ?i:created)';

        $this->c->DB->exec($query, $vars);

        $id = (int) $this->c->DB->lastInsertId();

        $p1 = \date('ym');
        $p2 = (int) ($id / 1000);
        $p3 = \substr($name, 0, 235 - \strlen($ext)) . '_' . \sprintf("%03d", $id - $p2);

        $path     = "{$p1}/{$p2}/{$p3}.{$ext}";
        $location = $this->c->DIR_PUBLIC . self::FOLDER . $path;

        $result = $file
            ->rename(false)
            ->rewrite(false)
            ->setQuality($this->c->config->i_upload_img_quality ?? 75)
            //->resize($this->c->config->i_avatars_width, $this->c->config->i_avatars_height)
            ->toFile($location);

        if (true !== $result) {
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
        ];
    }
}
