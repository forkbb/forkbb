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
use function \ForkBB\__;

class Reaction extends Page
{
    protected function responseAsJSON(array $response, string $position = 'info-400'): Page
    {
        $this->nameTpl      = 'layouts/plain_raw';
        $this->onlinePos    = $position;
        $this->onlineDetail = null;
        $this->httpStatus   = 200;
        $this->plainRaw     = \json_encode($response, FORK_JSON_ENCODE);

        $this->header('Content-type', 'application/json', true);

        return $this;

    }

    /**
     * Обрабатывает реакцию на сообщение
     */
    public function reaction(array $args, string $method): Page
    {
        $responseAsJSON = ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';

        if (! $this->c->Csrf->verify($args['token'], 'Reaction', $args)) {
            if (true === $responseAsJSON) {
                return $this->responseAsJSON(['error' => __($this->c->Csrf->getError())]);
            } else {
                return $this->c->Message->message($this->c->Csrf->getError());
            }
        }

        $nameKey = [];
        $rules   = [];

        foreach ($this->c->config->a_reaction_types as $key => $type) {
            if (true !== $type[1]) {
                continue;
            }

            $nameKey[$type[0]] = $key;
            $rules[$type[0]]   = "string|in:{$type[0]}";
        }

        $v = $this->c->Validator->reset()->addRules($rules);

        if (
            ! $v->validation($_POST)
            || 1 !== \count($result = $v->getData())
        ) {
            if (true === $responseAsJSON) {
                return $this->responseAsJSON(['error' => __('Bad request')]);
            } else {
                return $this->c->Message->message('Bad request');
            }
        }

        $post = $this->c->posts->load($args['id']);
        $name = \array_key_first($result);

        $result = $this->c->reactions->reaction($post, $nameKey[$name]);
        $status = match ($result) {
            true    => FORK_MESS_SUCC,
            false   => FORK_MESS_ERR,
            default => FORK_MESS_WARN,
        };

        if (true === $responseAsJSON) {
            $post->__selectedReaction = $name;

            $this->nameTpl      = 'reaction_for_json';
            $this->onlinePos    = null;
            $this->onlineDetail = null;
            $this->post         = $post;

            $tpl = $this->c->View->rendering($this, false);

            \preg_match('%<form[^>]*+>(.+)</form>%is', $tpl, $matches);

            return $this->responseAsJSON(['status' => $status, 'reactions' => $matches[1] ?? ''], 'topic-' . $post->parent->id);
        } else {
            return $this->c->Redirect->url($post->link)->message(":{$name}:", $status);
        }
    }
}
