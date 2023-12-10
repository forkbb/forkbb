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
    /**
     * Обрабатывает реакцию на сообщение
     */
    public function reaction(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'Reaction', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
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
            return $this->c->Message->message('Bad request');
        }

        $post = $this->c->posts->load($args['id']);
        $name = \array_key_first($result);

        $result = $this->c->reactions->reaction($post, $nameKey[$name]);
        $status = match ($result) {
            true    => FORK_MESS_SUCC,
            false   => FORK_MESS_ERR,
            default => FORK_MESS_WARN,
        };

        return $this->c->Redirect->url($post->link)->message(":{$name}:", $status);
    }
}
