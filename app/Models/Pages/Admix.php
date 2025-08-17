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
     * Возвращает ответ в виде json
     */
    protected function returnJson(array $data): Page
    {
        $this->nameTpl      = "layouts/plain_raw";
        $this->onlinePos    = null;
        $this->onlineDetail = null;
        $this->plainRaw     = \json_encode($data, FORK_JSON_ENCODE);

        $this->header('Content-type', 'application/json; charset=utf-8', true);

        return $this;
    }

    /**
     * Точка входа для запросов admix
     */
    public function admix(array $args): Page
    {
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
