<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Action;
use ForkBB\Models\Poll\Poll;
use ForkBB\Models\Topic\Topic;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет опрос в БД
     */
    public function update(Poll $poll): Poll
    {
        $poll->itWasModified = false;

        if (true !== $this->manager->revision($poll, true)) {
            throw new RuntimeException('The poll model has errors');
        }

        $old = $this->manager->Load->load($poll->tid);

        if (! $old instanceof Poll) {
            throw new RuntimeException('No such poll found');
        }

        $vars = [
            ':tid' => $poll->tid,
        ];
        $queryIn = 'INSERT INTO ::poll (tid, question_id, field_id, qna_text)
            VALUES (?i:tid, ?i:qid, ?i:fid, ?s:qna)';
        $queryU1 = 'UPDATE ::poll
            SET qna_text=?s:qna
            WHERE tid=?i:tid AND question_id=?i:qid AND field_id=?i:fid';
        $queryD1 = 'DELETE FROM ::poll
            WHERE tid=?i:tid AND question_id IN (?ai:qids)';
        $queryD2 = 'DELETE FROM ::poll
            WHERE tid=?i:tid AND question_id=?i:qid AND field_id IN (?ai:fid)';

        $modified    = false;
        $oldQuestion = $old->question;
        $oldType     = $old->type;

        foreach ($poll->question as $qid => $qna) {
            $vars[':qid'] = $qid;
            $vars[':fid'] = 0;
            $vars[':qna'] = $poll->type[$qid] . '|' . $qna;

            if (! isset($oldQuestion[$qid])) {
                $modified = true;

                $this->c->DB->exec($queryIn, $vars);

            } elseif (
                $qna !== $oldQuestion[$qid]
                || $poll->type[$qid] !== $oldType[$qid]
            ) {
                $modified = true;

                $this->c->DB->exec($queryU1, $vars);
            }

            $oldAnswer = $old->answer[$qid] ?? [];

            foreach ($poll->answer[$qid] as $fid => $qna) {
                $vars[':fid'] = $fid;
                $vars[':qna'] = $qna;

                if (! isset($oldAnswer[$fid])) {
                    $modified = true;

                    $this->c->DB->exec($queryIn, $vars);

                } elseif ($qna !== $oldAnswer[$fid]) {
                    $modified = true;

                    $this->c->DB->exec($queryU1, $vars);
                }

                unset($oldAnswer[$fid]);
            }

            if (! empty($oldAnswer)) {
                $modified     = true;
                $vars[':fid'] = \array_keys($oldAnswer);

                $this->c->DB->exec($queryD2, $vars);
            }

            unset($oldQuestion[$qid]);
        }

        if (! empty($oldQuestion)) {
            $modified     = true;
            $vars[':qid'] = \array_keys($oldQuestion);

            $this->c->DB->exec($queryD1, $vars);
        }

        $poll->itWasModified = $modified;

        $poll->resModified();

        return $poll;
    }

    /**
     * Добавляет новый опрос в БД
     */
    public function insert(Poll $poll): int
    {
        if (true !== $this->manager->revision($poll, true)) {
            throw new RuntimeException('The poll model has errors');
        }

        if (null !== $this->manager->Load->load($poll->tid)) {
            throw new RuntimeException('Such the poll already exists');
        }

        $vars = [
            ':tid' => $poll->tid,
        ];
        $query = 'INSERT INTO ::poll (tid, question_id, field_id, qna_text)
            VALUES (?i:tid, ?i:qid, ?i:fid, ?s:qna)';

        foreach ($poll->question as $qid => $qna) {
            $vars[':qid'] = $qid;
            $vars[':fid'] = 0;
            $vars[':qna'] = $poll->type[$qid] . '|' . $qna;

            $this->c->DB->exec($query, $vars);

            foreach ($poll->answer[$qid] as $fid => $qna) {
                $vars[':fid'] = $fid;
                $vars[':qna'] = $qna;

                $this->c->DB->exec($query, $vars);
            }
        }

        $poll->resModified();

        return $poll->tid;
    }
}
