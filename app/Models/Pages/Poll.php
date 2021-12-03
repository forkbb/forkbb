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
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\Poll\Poll as PollModel;
use function \ForkBB\__;

class Poll extends Page
{
    /**
     * Голосование
     */
    public function vote(array $args, string $method): Page
    {
        $tid   = $args['tid'];
        $topic = $this->c->topics->load($tid);

        if (
            ! $topic instanceof Topic
            || ! $topic->poll instanceof PollModel
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('poll');

        $v = $this->c->Validator->reset()
        ->addValidators([
        ])->addRules([
            'token'         => 'token:Poll',
            'poll_vote.*.*' => 'required|integer',
            'vote'          => 'required|string',
        ])->addAliases([
        ])->addArguments([
            'token'       => $args,
        ])->addMessages([
            'poll_vote.*.*' => 'The poll structure is broken',
        ]);

        if (! $v->validation($_POST)) {
            $message = $this->c->Message;
            $message->fIswev = $v->getErrors();

            return $message->message('');
        } elseif (null !== ($result = $topic->poll->vote($v->poll_vote))) {
            return $this->c->Message->message($result);
        } else {
            return $this->c->Redirect->url($topic->link)->message('You voted');
        }
    }
}
