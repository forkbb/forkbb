<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Markread extends Action
{
    /**
     * Пометка всех тем/разделов прочитанными
     *
     * @param Forum $forum
     *
     * @throws RuntimeException
     *
     * @return Forum
     */
    public function markread(Forum $forum, User $user)
    {
        if ($user->isGuest) {
            throw new RuntimeException('Expected user, not guest');
        }

        if (0 === $forum->id) {
            $user->u_mark_all_read = \time();

            $this->c->users->update($user);

            $vars = [
                ':uid' => $user->id,
            ];
            $sql = 'DELETE FROM ::mark_of_topic WHERE uid=?i:uid';
            $this->c->DB->exec($sql, $vars);

            $sql = 'DELETE FROM ::mark_of_forum WHERE uid=?i:uid';
            $this->c->DB->exec($sql, $vars);
        } elseif ($forum->id > 0) {
            $vars = [
                ':uid'  => $user->id,
                ':fid'  => $forum->id,
                ':mark' => \time(),
            ];
            $sql = 'DELETE FROM ::mark_of_topic
                    WHERE uid=?i:uid AND tid IN (
                        SELECT id
                        FROM ::topics
                        WHERE forum_id=?i:fid
                    )';
            $this->c->DB->exec($sql, $vars);

            if ($user->mf_mark_all_read) {                                           // ????
                $sql = 'UPDATE ::mark_of_forum
                        SET mf_mark_all_read=?i:mark
                        WHERE uid=?i:uid AND fid=?i:fid';
            } else {                                                                 // ????
                $sql = 'INSERT INTO ::mark_of_forum (uid, fid, mf_mark_all_read)
                        VALUES (?i:uid, ?i:fid, ?i:mark)';
            }
            $this->c->DB->exec($sql, $vars);
        } else {
            throw new RuntimeException('The model does not have ID');
        }
    }
}
