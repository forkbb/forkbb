<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Action;
use ForkBB\Models\Poll\Poll;
use InvalidArgumentException;

class Revision extends Action
{
    protected string|array|null $error;
    protected array $question;
    protected array $answer;
    protected array $type;

    /**
     * Проверяет/нормализует опрос
     */
    public function revision(Poll $poll, bool $normalize = false): string|array|bool
    {
        $this->error    = null;
        $this->question = [];
        $this->answer   = [];
        $this->type     = [];


        if (
            empty($poll->question)
            || ! \is_array($poll->question)
        ) {
            $this->error = 'The poll structure is broken';
        } else {
            $this->test($poll);
        }

        if (
            $normalize
            && null === $this->error
        ) {
            $poll->__question = $this->question;
            $poll->__answer   = $this->answer;
            $poll->__type     = $this->type;
        }

        return $this->error ?? true;
    }

    protected function test(Poll $poll): void
    {
        $questions = $poll->question;
        $answers   = $poll->answer;
        $types     = $poll->type;

        $countQ = 0;
        $emptyQ = false;

        for ($qid = 1; $qid <= $this->c->config->i_poll_max_questions; $qid++) {
            if (
                ! isset($questions[$qid])
                || '' == $questions[$qid]
            ) {
                $emptyQ = true;

                unset($questions[$qid], $answers[$qid]);

                continue;
            } elseif ($emptyQ) {
                $this->error = ['Question number %s is preceded by an empty question', $qid];

                return;
            }

            if (
                empty($answers[$qid])
                || ! \is_array($answers[$qid])
            ) {
                $this->error = ['For question number %s, the structure of answers is broken', $qid];

                return;
            }

            $countA = 0;
            $emptyA = false;

            for ($fid = 1; $fid <= $this->c->config->i_poll_max_fields; $fid++) {
                if (
                    ! isset($answers[$qid][$fid])
                    || '' == $answers[$qid][$fid]
                ) {
                    $emptyA = true;

                    unset($answers[$qid][$fid]);

                    continue;
                } elseif ($emptyA) {
                    $this->error = ['Answer number %1$s is preceded by an empty answer (question number %2$s)', $fid, $qid];

                    return;
                }

                ++$countA;
                $this->answer[$qid][$fid] = $answers[$qid][$fid];

                unset($answers[$qid][$fid]);
            }

            if (! empty($answers[$qid])) {
                $this->error = ['For question number %s, the structure of answers is broken', $qid];

                return;
            } elseif ($countA < 2) {
                $this->error = ['Requires at least two answers per question (%s)', $qid];

                return;
            } elseif (! isset($types[$qid])) {
                $this->error = ['For question number %s, there is no value for the maximum number of answers for voting', $qid];

                return;
            } elseif ($types[$qid] > $countA) {
                $this->error = ['For question number %s, the maximum number of answers for voting more answers', $qid];

                return;
            }

            ++$countQ;
            $this->question[$qid] = $questions[$qid];
            $this->type[$qid]     = $types[$qid];

            unset($questions[$qid], $answers[$qid]);
        }

        if (
            0 === $countQ
            || ! empty($questions)
        ) {
            $this->error = 'The poll structure is broken';
        }
    }
}
