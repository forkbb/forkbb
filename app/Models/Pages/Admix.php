<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Admix extends Page
{
    /**
     * Метод admix => время действия токена
     */
    protected array $actions = [
        'upload' => 3600,
    ];

    /**
     * Выход
     */
    protected function exitPoint(string $cType): Page
    {
        $this->c->curReqVisible = 0;
        $this->c->DEBUG         = 0;
        $this->nameTpl          = 'layouts/plain_raw';
        $this->onlinePos        = null;
        $this->onlineDetail     = null;

        $this->header('Content-type', $cType);

        return $this;
    }

    /**
     * Возвращает json
     */
    protected function returnJson(array $data): Page
    {
        $this->plainRaw = \json_encode($data, FORK_JSON_ENCODE);

        return $this->exitPoint('application/json; charset=utf-8');
    }

    /**
     * Возвращает css
     */
    public function style(): Page
    {
        $this->c->Online->flags('style');

        $this->plainRaw = '#fork::after{position:absolute;width:0;height:0;overflow:hidden;z-index:-1;content:url(img.gif);}';

        return $this->exitPoint('text/css; charset=utf-8');
    }

    /**
     * Возвращает img
     */
    public function img(): Page
    {
        $this->c->Online->flags('img');

        $this->plainRaw = "\x47\x49\x46\x38\x37\x61\x1\x0\x1\x0\x80\x0\x0\xfc\x6a\x6c\x0\x0\x0\x2c\x0\x0\x0\x0\x1\x0\x1\x0\x0\x2\x2\x44\x1\x0\x3b";

        return $this->exitPoint('image/gif');
    }

    /**
     * Точка входа для запросов admix
     */
    public function admix(array $args): Page
    {
        $this->c->curReqVisible = 0;

        if (empty($this->actions[$args['action']])) {
            return $this->returnJson(['error' => 'Bad action']);

        } elseif (! $this->c->Csrf->verify($args['token'], 'Admix', $args, $this->actions[$args['action']])) {
            return $this->returnJson(['error' => $this->c->Csrf->getError()]);

        } else {
            $method = 'admix' . \ucfirst($args['action']);

            return $this->$method();
        }
    }

    /**
     * Проверка вложений
     */
    public function vCheckAttach(Validator $v, array $files): array
    {
        $exts   = \array_flip(\explode(',', $this->c->user->g_up_ext));
        $result = [];

        foreach ($files as $file) {
            if (isset($exts[$file->ext()])) {
                $result[] = $file;

            } else {
                $v->addError(['The %s extension is not allowed', $file->ext()]);
            }
        }

        return $result;
    }

    /**
     * Действие: upload
     */
    protected function admixUpload(): Page
    {
        if (! $this->c->userRules->useUpload) {
            return $this->returnJson(['error' => 'No rights to upload']);
        }

        $v = $this->c->Validator->reset()
            ->addValidators([
                'check_attach' => [$this, 'vCheckAttach'],
            ])->addRules([
                'files' => "required|file:multiple|max:{$this->c->user->g_up_size_kb}|check_attach",
            ])->addAliases([
            ])->addArguments([
            ])->addMessages([
            ]);

        if (! $v->validation($_FILES)) {
            $this->c->Lang->load('validator');

            $e = $v->getErrors();
            $e = \array_shift($e);
            $e = \array_shift($e);

            return $this->returnJson(['error' => __($e)]);
        }

        $text    = '';
        $uploads = [];

        foreach ($v->files as $file) {
            $data = $this->c->attachments->addFile($file);

            if (\is_array($data)) {
                $cur = [
                    'name' => $file->name(),
                    'url'  => $data['url'],
                    'img'  => $data['image'] ? true : false,
                ];

                if ($cur['img']) {
                    $text .= "\n[img]{$cur['url']}[/img]";

                } else {
                    $text .= "\n[url={$cur['url']}]{$cur['name']}[/url]";
                }

                $uploads[] = $cur;
            }
        }

        return $this->returnJson([
            'text'    => $text,
            'uploads' => $uploads,
        ]);
    }
}
