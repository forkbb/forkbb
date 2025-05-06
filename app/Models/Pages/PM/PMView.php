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
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use InvalidArgumentException;
use function \ForkBB\__;

class PMView extends AbstractPM
{
    /**
     * Списки новых, текущих и архивных приватных топиков
     */
    public function view(array $args, string $method): Page
    {
        $this->args      = $args;
        $this->pms->page = $args['more1'] ?? 1;

        if (
            isset($args['more2'])
            || ! $this->pms->hasPage()
        ) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->pmIndex = $this->pms->area;

        if ('POST' === $method) {
            $this->c->Lang->load('validator');

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'action_process' => [$this, 'vActionProcess'],
                ])->addRules([
                    'token'              => 'token:PMAction',
                    'ids'                => 'required|array',
                    'ids.*'              => 'required|integer|min:1|max:9999999999',
                    'confirm'            => 'integer',
                    Cnst::ACTION_ARCHIVE => 'string',
                    Cnst::ACTION_DELETE  => 'string',
                    'action'             => 'action_process',
                ])->addAliases([
                ])->addArguments([
                    'token'              => $this->args,
                ])->addMessages([
                    'ids'                => 'No dialogs',
                ]);

            if ($v->validation($_POST)) {
                if (null === $v->action) {
                    $this->nameTpl    = 'pm/form';
                    $this->form       = $this->formConfirm($v, $this->args);
                    $this->formClass  = 'post';
                    $this->formTitle  = Cnst::PT_ARCHIVE === $this->vStatus ? 'InfoSaveQt' : 'InfoDeleteQt';
                    $this->pmCrumbs[] = Cnst::PT_ARCHIVE === $this->vStatus ? 'InfoSaveQ' : 'InfoDeleteQ';

                    return $this;

                } elseif (1 !== $v->confirm) {
                    return $this->c->Redirect->page('PMAction', $this->args)->message('No confirm redirect', FORK_MESS_WARN);

                } else {
                    $topics = $this->pms->loadByIds(Cnst::PTOPIC, $v->ids);

                    foreach ($topics as $topic) {
                        $topic->status = $this->vStatus;
                    }

                    // удаление (при $topic->isFullDeleted) или обновление диалогов и пользователя
                    $this->pms->delete(...$topics); // ????

                    if (Cnst::PT_ARCHIVE === $this->vStatus) {
                        $message = 'Dialogues moved to archive redirect';

                        $args['action'] = Cnst::ACTION_ARCHIVE;

                    } else {
                        $message = 'Dialogues deleted redirect';

                        unset($args['second']);
                    }

                    unset($args['more1']);

                    return $this->c->Redirect->page('PMAction', $args)->message($message, FORK_MESS_SUCC);
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->identifier = ['pm', 'pm-view'];
        $this->nameTpl    = 'pm/view';
        $this->pmList     = $this->pms->pmListCurPage();
        $this->pagination = $this->pms->pagination;

        if ($this->pmList) {
            $this->form = $this->form($this->args);
        }

        return $this;
    }

    /**
     * Определяет действие
     */
    public function vActionProcess(Validator $v, $action)
    {
        if (! empty($v->getErrors())) {
            return $action;
        }

        if (! empty($v->{Cnst::ACTION_ARCHIVE}) ) {
            $this->vStatus = Cnst::PT_ARCHIVE;

            if ($this->user->g_pm_limit > 0) {
                if ($this->pms->totalArchive >= $this->user->g_pm_limit) {
                    $v->addError('Archive is full');

                    return $action;

                } elseif ($this->pms->totalArchive + \count($v->ids) > $this->user->g_pm_limit) {
                    $v->addError('Cannot be moved');

                    return $action;
                }
            }

        } elseif (! empty($v->{Cnst::ACTION_DELETE})) {
            $this->vStatus = Cnst::PT_DELETED;

        } else {
            $v->addError('Unknown action selected');

            return $action;
        }

        foreach ($v->ids as $id) {
            if (! $this->pms->accessTopic($id)) {
                $v->addError(['Dialogue %s is not yours', $id]);

                return $action;
            }
        }

        return $action;
    }

    /**
     * Создает массив данных для формы подтверждения
     */
    protected function formConfirm(Validator $v, array $args): array
    {
        $headers = [];

        foreach ($this->pms->loadByIds(Cnst::PTOPIC, $v->ids) as $topic) {
            $headers[] = __(['Dialogue %s', $topic->name]);
        }

        $btn  = Cnst::PT_ARCHIVE === $this->vStatus ? Cnst::ACTION_ARCHIVE : Cnst::ACTION_DELETE;
        $form = [
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token'  => $this->c->Csrf->create('PMAction', $args),
                'ids'    => $v->ids,
                'action' => $v->{$btn},
            ],
            'sets' => [
                'info' => [
                    'inform' => [
                        [
                            'html' => \implode('<br>', $headers),
                        ],
                    ],
                ],
                'action' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns' => [
                $btn => [
                    'type'  => 'submit',
                    'value' => __($v->{$btn}),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('PMAction', $args),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Создает массив данных для формы удалени/переноса в архив
     */
    protected function form(array $args): array
    {
        $form = [
            'id'     => 'id-form-pmview',
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('PMAction', $args),
            ],
            'sets'   => [],
            'btns'   => [],
        ];

        if (Cnst::ACTION_ARCHIVE !== $this->pms->area) {
            $form['btns'][Cnst::ACTION_ARCHIVE] = [
                'class' => ['origin'],
                'type'  => 'submit',
                'value' => __('To archive'),
            ];
        }

        $form['btns'][Cnst::ACTION_DELETE] = [
            'class' => ['origin'],
            'type'  => 'submit',
            'value' => __('Delete'),
        ];

        return $form;
    }
}
