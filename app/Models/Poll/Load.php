<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Action;
use ForkBB\Models\Poll\Model as Poll;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Загружает опрос из БД
     */
    public function load(int $id): ?Poll
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive poll id');
        }

        $vars  = [
            ':tid' => $id,
        ];
        $query = 'SELECT question_id, field_id, qna_text, votes
            FROM ::poll
            WHERE tid=?i:tid
            ORDER BY question_id, field_id';

        $stmt = $this->c->DB->query($query, $vars);

        $i    = 0;
        $data = [
            'id'       => $id,
            'question' => [],
            'answer'   => [],
            'vote'     => [],
            'type'     => [],
        ];

        while ($row = $stmt->fetch()) {
            $qid = $row['question_id'];
            $fid = $row['field_id'];

            if (0 === $fid) {
                $data['question'][$qid]     = $row['qna_text'];
                $data['type'][$qid]         = $row['votes'];
            } else {
                $data['answer'][$qid][$fid] = $row['qna_text'];
                $data['vote'][$qid][$fid]   = $row['votes'];
            }

            ++$i;
        }

        if (0 === $i) {
            return null;
        } else {
            return $this->manager->create($data);
        }
    }
}
