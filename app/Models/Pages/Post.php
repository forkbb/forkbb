<?php

namespace ForkBB\Models\Pages;

class Post extends Page
{
    use UsersTrait;
    use OnlineTrait;
    use CrumbTrait;

    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'post';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'post';

    /**
     * Данные по текущей теме
     * @var array
     */
    protected $topic;

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
    public function newTopic(array $args)
    {
        list($fTree, $fDesc, $fAsc) = $this->c->forums;

        // раздел отсутствует в доступных
        if (empty($fDesc[$args['id']])) {
            return $this->c->Message->message('Bad request');
        }

        $parent = isset($fDesc[$args['id']][0]) ? $fDesc[$args['id']][0] : 0;
        $perm = $fTree[$parent][$args['id']];

        // раздел является ссылкой
        if (null !== $perm['redirect_url']) {
            return $this->c->Message->message('Bad request');
        }

        $vars = [':fid' => $args['id']];
        $sql = 'SELECT f.* FROM ::forums AS f WHERE f.id=?i:fid';

        $forum = $this->c->DB->query($sql, $vars)->fetch();
        $user = $this->c->user;

        $moders = empty($forum['moderators']) ? [] : array_flip(unserialize($forum['moderators']));

        if (! $user->isAdmin
            && (! $user->isAdmMod || ! isset($moders[$user->id]))
            && (null === $perm['post_topics'] && $user->g_post_topics == '0' || $perm['post_topics'] == '0')
        ) {
            return $this->c->Message->message('Bad request');
        }

        return $this;
    }
}
