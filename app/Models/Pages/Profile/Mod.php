<?php

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Forum\Manager as ForumManager;

class Mod extends Profile
{
    /**
     * Подготавливает данные для шаблона конфигурации прав модератора
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function moderation(array $args, $method)
    {
        if (false === $this->initProfile($args['id']) || ! $this->rules->confModer) {
            return $this->c->Message->message('Bad request');
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'       => 'token:EditUserModeration',
                    'moderator.*' => 'integer|in:' . \implode(',', \array_keys($this->curForums)),
                ])->addAliases([
                ])->addArguments([
                    'token'       => ['id' => $this->curUser->id],
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                foreach ($this->c->forums->get(0)->descendants as $forum) {
                    if (isset($v->moderator[$forum->id]) && $v->moderator[$forum->id] === $forum->id) {
                        $forum->modAdd($this->curUser);
                    } else {
                        $forum->modDelete($this->curUser);
                    }
                    $this->c->forums->update($forum);
                }

                $this->c->Cache->delete('forums_mark');

                return $this->c->Redirect->page('EditUserModeration', ['id' => $this->curUser->id])->message('Update rights redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->crumbs     = $this->crumbs(
            [$this->c->Router->link('EditUserModeration', ['id' => $this->curUser->id]), \ForkBB\__('Moderator rights')],
            [$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), \ForkBB\__('Editing profile')]
        );
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Возвращает список доступных разделов для пользователя текущего профиля
     *
     * @return array
     */
    protected function getcurForums()
    {
        $forums = new ForumManager($this->c);
        $forums->init($this->c->groups->get($this->curUser->group_id));
        $root = $forums->get(0);

        return $root instanceof Forum ? $root->descendants : [];
    }

    /**
     * Создает массив данных для формы
     *
     * @return array
     */
    protected function form()
    {
        $form = [
            'action' => $this->c->Router->link('EditUserModeration', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserModeration', ['id' => $this->curUser->id]),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $root = $this->c->forums->get(0);

        if ($root instanceof Forum) {
            $list = $this->c->forums->depthList($root, 0);
            $cid  = null;

            foreach ($list as $forum) {
                if ($cid !== $forum->cat_id) {
                    $form['sets']["category{$forum->cat_id}-info"] = [
                        'info' => [
                            'info1' => [
                                'type'  => '', //????
                                'value' => $forum->cat_name,
                            ],
                        ],
                    ];
                    $cid = $forum->cat_id;
                }

                $fields = [];
                $fields["name{$forum->id}"] = [
                    'class'   => ['modforum', 'name', 'depth' . $forum->depth],
                    'type'    => 'str',
                    'value'   => $forum->forum_name,
                    'caption' => \ForkBB\__('Forum label'),
                ];
                $fields["moderator[{$forum->id}]"] = [
                    'class'    => ['modforum', 'moderator'],
                    'type'     => 'checkbox',
                    'value'    => $forum->id,
                    'checked'  => isset($this->curForums[$forum->id]) && $this->curUser->isModerator($forum),
                    'disabled' => ! isset($this->curForums[$forum->id]),
                    'caption'  => \ForkBB\__('Moderator label'),
                ];
                $form['sets']["forum{$forum->id}"] = [
                    'class'  => 'modforum',
                    'legend' => $forum->cat_name . ' / ' . $forum->forum_name,
                    'fields' => $fields,
                ];
            }
        }

        return $form;
    }
}
