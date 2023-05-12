<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\Pages\RegLogTrait;
use ForkBB\Models\User\User;
use function \ForkBB\__;
use function \ForkBB\num;
use function \ForkBB\size;

class OAuth extends Profile
{
    use RegLogTrait;

    /**
     * Подготавливает данные для шаблона списка аккаунтов
     */
    public function list(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->configureOAuth
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_providers');

        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('EditUserOAuth', $args),
                'OAuth accounts',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form       = $this->formList($args);
        $this->formOAuth  = $this->reglogForm();
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Создает массив данных для формы аккаунтов
     */
    protected function formList(array $args): array
    {
        $data = $this->c->providerUser->loadUserData($this->curUser);

        if (0 === \count($data)) {
            $this->fIswev = ['i', 'No linked accounts'];

            return [];
        }

        $fields = [];

        foreach ($data as $cur) {
            $key          = $cur['name'] . '-' . $cur['userId'];
            $args['key']  = $key;
            $value        = __($cur['name']);
            $title        = $value . " ({$cur['userId']})";
            $fields[$key] = [
                'type'  => 'btn',
                'class' => ['oauth-acc-btn'],
                'value' => $value,
                'title' => $title,
                'link'  => $this->c->Router->link('EditUserOAuthAction', $args),
            ];
        }

        return [
            'action' => null,
            'sets'   => [
                'oauth-accounts' => [
                    'class'  => ['account-links'],
                    'legend' => 'Linked accounts',
                    'fields' => $fields,
                ],
            ],
            'btns'   => null,
        ];
    }
}
