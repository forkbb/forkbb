<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\PM\Cnst;
use function \ForkBB\__;

class PMConfig extends AbstractPM
{
    /**
     * Конфигурирование ЛС
     */
    public function config(array $args, string $method): Page
    {
        if (
            isset($args['more1'])
            || isset($args['more2'])
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->args = $args;

        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'       => 'token:PMAction',
                    'u_pm'        => 'required|integer|in:0,1',
                    'u_pm_notify' => 'required|integer|in:0,1',
                    'save'        => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if ($v->validation($_POST)) {
                $this->user->u_pm        = $v->u_pm;
                $this->user->u_pm_notify = $v->u_pm_notify;

                $this->c->users->update($this->user);

                return $this->c->Redirect->page('PMAction', $args)->message('PM Config redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->identifier   = ['pm', 'pm-config'];
        $this->nameTpl      = 'pm/form';
        $this->onlineDetail = null;
        $this->pmIndex      = Cnst::ACTION_CONFIG;
        $this->formTitle    = 'PM Config title';
        $this->formClass    = 'pmconfig';
        $this->form         = $this->formConfig($args);
        $this->pmCrumbs[]   = [$this->c->Router->link('PMAction', $args), 'PM Config'];

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formConfig(array $args): array
    {
        $yn = [1 => __('Yes'), 0 => __('No')];

        return [
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('PMAction', $args),
            ],
            'sets'   => [
                'config' => [
                    'legend' => 'PM Config legend',
                    'fields' => [
                        'u_pm' => [
                            'type'    => 'radio',
                            'value'   => $this->user->u_pm,
                            'values'  => $yn,
                            'caption' => 'Use PM label',
                            'help'    => 'Use PM help',
                        ],
                        'u_pm_notify' => [
                            'type'    => 'radio',
                            'value'   => $this->user->u_pm_notify,
                            'values'  => $yn,
                            'caption' => 'Email notification label',
                            'help'    => 'Email notification help',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'save'  => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];
    }
}
