<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\BBCodeList\Structure;
use ForkBB\Models\Pages\Admin\Parser;
use function \ForkBB\__;

class BBCode extends Parser
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->AdminBBCodeUrl = $this->c->Router->link('AdminBBCode');
    }

    /**
     * Редактирование натроек bbcode
     */
    public function view(array $args, string $method): Page
    {
        $this->c->bbcode->load();

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'           => 'token:AdminBBCode',
                    'bbcode.*.in_mes' => 'required|integer|min:0|max:2',
                    'bbcode.*.in_sig' => 'required|integer|min:0|max:2',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $mesClear  = true;
                $sigClear  = true;
                $white_mes = [];
                $black_mes = [];
                $white_sig = [];
                $black_sig = [];

                foreach ($this->c->bbcode->bbcodeTable as $id => $tagData) {
                    $tag    = $tagData['bb_tag'];
                    $bbcode = $v->bbcode;

                    if ('ROOT' === $tag) {
                        continue;
                    }

                    if (! isset($bbcode[$tag]['in_mes'], $bbcode[$tag]['in_sig'])) {
                        $mesClear  = false;
                        $sigClear  = false;

                        continue;
                    }

                    switch ($bbcode[$tag]['in_mes']) {
                        case 2:
                            $white_mes[] = $tag;

                            break;
                        case 0:
                            $black_mes[] = $tag;
                        default:
                            $mesClear  = false;

                            break;
                    }

                    switch ($bbcode[$tag]['in_sig']) {
                        case 2:
                            $white_sig[] = $tag;

                            break;
                        case 0:
                            $black_sig[] = $tag;
                        default:
                            $sigClear  = false;

                            break;
                    }
                }

                $this->c->config->a_bb_white_mes = $mesClear ? [] : $white_mes;
                $this->c->config->a_bb_black_mes = $mesClear ? [] : $black_mes;
                $this->c->config->a_bb_white_sig = $sigClear ? [] : $white_sig;
                $this->c->config->a_bb_black_sig = $sigClear ? [] : $black_sig;

                $this->c->config->save();

                return $this->c->Redirect->url($this->AdminBBCodeUrl)->message('Parser settings updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aCrumbs[] = [$this->AdminBBCodeUrl, 'BBCode management'];
        $this->form      = $this->formView();
        $this->titleForm = 'BBCode head';
        $this->classForm = ['bbcode'];

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function formView(): array
    {
        $form = [
            'action' => $this->AdminBBCodeUrl,
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminBBCode'),
            ],
            'sets' => [
                'bbcode-legend' => [
                    'class'  => ['bbcode-legend'],
                    'legend' => 'BBCode list subhead',
                    'fields' => [],
                ],
            ],
            'btns'   => [
                'new' => [
                    'type'  => 'btn',
                    'value' => __('New BBCode'),
                    'link'  => $this->c->Router->link('AdminBBCodeNew'),
                ],
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $selectList = [
            2 => __('BBCode allowed'),
            1 => __('BBCode display only'),
            0 => __('BBCode not allowed'),
        ];

        foreach ($this->c->bbcode->bbcodeTable as $id => $tagData) {
            $fields = [];
            $tag    = $tagData['bb_tag'];

            $fields["bbcode{$id}-tag"] = [
                'class'     => ['bbcode', 'tag'],
                'type'      => $tagData['bb_edit'] > 0 ? 'link' : 'str',
                'value'     => $tag,
                'caption'   => 'BBCode tag label',
                'title'     => __('BBCode tag title'),
                'href'      => 1 === $tagData['bb_edit']
                    ? $this->c->Router->link('AdminBBCodeEdit', ['id' => $id])
                    : null,
            ];
            $fields["bbcode[{$tag}][in_mes]"] = [
                'class'     => ['bbcode', 'in_mes'],
                'type'      => 'select',
                'options'   => $selectList,
                'value'     => $this->getValue($tag, $this->c->config->a_bb_white_mes, $this->c->config->a_bb_black_mes),
                'caption'   => 'BBCode mes label',
                'disabled'  => 'ROOT' === $tag,
            ];
            $fields["bbcode[{$tag}][in_sig]"] = [
                'class'     => ['bbcode', 'in_sig'],
                'type'      => 'select',
                'options'   => $selectList,
                'value'     => $this->getValue($tag, $this->c->config->a_bb_white_sig, $this->c->config->a_bb_black_sig),
                'caption'   => 'BBCode sig label',
                'disabled'  => 'ROOT' === $tag,
            ];
            $fields["bbcode{$id}-del"] = [
                'class'     => ['bbcode', 'delete'],
                'type'      => 'btn',
                'value'     => '❌',
                'caption'   => 'Delete',
                'title'     => __('Delete'),
                'link'      => 1 === $tagData['bb_delete']
                    ? $this->c->Router->link('AdminBBCodeDelete', ['id' => $id])
                    : null,
                'disabled'  => 1 !== $tagData['bb_delete'],
            ];

            $form['sets']["bbcode{$id}"] = [
                'class'  => ['bbcode'],
                'legend' => ['BBCode %s', $tag],
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Вычисляет значение для select на основе белого и черного списков bbcode
     */
    protected function getValue(string $tag, array $white, array $black): int
    {
        if ('ROOT' === $tag) {
            return 1;
        } elseif (empty($white) && empty($black)) {
            return 2;
        } elseif (\in_array($tag, $black)) {
            return 0;
        } elseif (\in_array($tag, $white)) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * Удаляет bbcode
     */
    public function delete(array $args, string $method): Page
    {
        $this->c->bbcode->load();

        $tagData = $this->c->bbcode->bbcodeTable[$args['id']] ?? null;

        if (
            empty($tagData['bb_delete'])
            || 1 !== $tagData['bb_delete']
        ) {
            return $this->c->Message->message('Bad request');
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:AdminBBCodeDelete',
                    'confirm' => 'checkbox',
                    'delete'  => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->url($this->AdminBBCodeUrl)->message('No confirm redirect', FORK_MESS_WARN);
            }

            $this->c->bbcode->delete($args['id']);

            return $this->c->Redirect->url($this->AdminBBCodeUrl)->message('BBCode deleted redirect', FORK_MESS_SUCC);
        }

        $formAction      = $this->c->Router->link('AdminBBCodeDelete', $args);

        $this->nameTpl   = 'admin/form';
        $this->classForm = ['deletebbcode'];
        $this->titleForm = 'Delete bbcode head';
        $this->form      = $this->formDelete($args, $formAction, $tagData['bb_tag']);

        $this->aCrumbs[] = [$formAction, $this->titleForm];
        $this->aCrumbs[] = [null, ['"%s"', $tagData['bb_tag']]];
        $this->aCrumbs[] = [$this->AdminBBCodeUrl, 'BBCode management'];

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function formDelete(array $args, string $formAction, string $name): array
    {
        return [
            'action' => $formAction,
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminBBCodeDelete', $args),
            ],
            'sets'   => [
                'info' => [
                    'inform' => [
                        [
                            'message' => ['BBCode %s', $name],
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'  => 'submit',
                    'value' => __('Delete bbcode btn'),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $this->AdminBBCodeUrl,
                ],
            ],
        ];
    }

    /**
     * Редактирование/добавление нового bbcode
     */
    public function edit(array $args, string $method): Page
    {
        $this->c->bbcode->load();

        $structure = $this->c->BBStructure;
        $id        = $args['id'] ?? 0;

        if ($id > 0) {
            if (
                empty($this->c->bbcode->bbcodeTable[$id])
                || 1 !== $this->c->bbcode->bbcodeTable[$id]['bb_edit']
            ) {
                return $this->c->Message->message('Bad request');
            }

            $structure = $structure->fromString($this->c->bbcode->bbcodeTable[$id]['bb_structure']);
        }

        $bbTypes = [];
        $bbNames = [];

        foreach ($this->c->bbcode->bbcodeTable as $cur) {
            $type = $this->c->BBStructure->fromString($cur['bb_structure'])->type;
            $bbTypes[$type] = $type;
            $bbNames[$cur['bb_tag']] = $cur['bb_tag'];
        }

        $this->bbTypes = $bbTypes;

        if ($id > 0) {
            $title            = 'Edit bbcode head';
            $page             = 'AdminBBCodeEdit';
            $pageArgs         = ['id' => $id];
        } else {
            $title            = 'Add bbcode head';
            $page             = 'AdminBBCodeNew';
            $pageArgs         = [];
        }

        $this->formAction = $this->c->Router->link($page, $pageArgs);
        $this->formToken  = $this->c->Csrf->create($page, $pageArgs);

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_all'                 => [$this, 'vCheckAll'],
                ])->addRules([
                    'token'                     => 'token:' . $page,
                    'tag'                       => $id > 0 ? 'absent' : 'required|string:trim|regex:%^[a-z\*][a-z\d-]{0,10}$%|not_in:' . \implode(',', $bbNames),
                    'type'                      => 'required|string|in:' . \implode(',', $bbTypes),
                    'type_new'                  => 'exist|string:trim,empty|regex:%^[a-z][a-z\d-]{0,19}$%',
                    'parents.*'                 => 'required|string|in:' . \implode(',', $bbTypes),
                    'handler'                   => 'exist|string:trim|max:65535',
                    'text_handler'              => 'exist|string:trim|max:65535',
                    'recursive'                 => 'required|integer|in:0,1',
                    'text_only'                 => 'required|integer|in:0,1',
                    'tags_only'                 => 'required|integer|in:0,1',
                    'pre'                       => 'required|integer|in:0,1',
                    'single'                    => 'required|integer|in:0,1',
                    'auto'                      => 'required|integer|in:0,1',
                    'self_nesting'              => 'required|integer|min:0|max:10',
                    'no_attr.allowed'           => 'required|integer|in:0,1',
                    'no_attr.body_format'       => 'exist|string:trim|max:1024',
                    'no_attr.text_only'         => 'required|integer|in:0,1',
                    'def_attr.allowed'          => 'required|integer|in:0,1',
                    'def_attr.required'         => 'required|integer|in:0,1',
                    'def_attr.format'           => 'exist|string:trim|max:1024',
                    'def_attr.body_format'      => 'exist|string:trim|max:1024',
                    'def_attr.text_only'        => 'required|integer|in:0,1',
                    'new_attr.name'             => 'exist|string:trim,empty|regex:%^[a-z-]{2,15}$%',
                    'new_attr.allowed'          => 'required|integer|in:0,1',
                    'new_attr.required'         => 'required|integer|in:0,1',
                    'new_attr.format'           => 'exist|string:trim|max:1024',
                    'new_attr.body_format'      => 'exist|string:trim|max:1024',
                    'new_attr.text_only'        => 'required|integer|in:0,1',
                ])->addAliases([
                ])->addArguments([
                    'token'                    => $pageArgs,
                    'save'                     => $structure,
                ])->addMessages([
                ]);

                if ($structure->other_attrs) {
                    $v->addRules([
                        'other_attrs.*.allowed'     => 'required|integer|in:0,1',
                        'other_attrs.*.required'    => 'required|integer|in:0,1',
                        'other_attrs.*.format'      => 'exist|string:trim|max:1024',
                        'other_attrs.*.body_format' => 'exist|string:trim|max:1024',
                        'other_attrs.*.text_only'   => 'required|integer|in:0,1',
                    ]);
                }

                $v->addRules([
                    'save' => 'required|check_all',
                ]);

                if ($v->validation($_POST)) {
                    if ($id > 0) {
                        $this->c->bbcode->update($id, $structure);
                        $message = 'BBCode updated redirect';
                    } else {
                        $id = $this->c->bbcode->insert($structure);
                        $message = 'BBCode added redirect';
                    }

                    return $this->c->Redirect->page('AdminBBCodeEdit', ['id' => $id])->message($message, FORK_MESS_SUCC);
                }

                $this->fIswev = $v->getErrors();
        }

        $this->aCrumbs[] = [$this->formAction, $title];

        if ($id > 0) {
            $this->aCrumbs[] = [null, ['"%s"', $this->c->bbcode->bbcodeTable[$id]['bb_tag']]];
        }

        $this->aCrumbs[] = [$this->AdminBBCodeUrl, 'BBCode management'];
        $this->form      = $this->formEdit($id, $structure);
        $this->titleForm = $title;
        $this->classForm = ['editbbcode'];
        $this->nameTpl   = 'admin/form';

        return $this;
    }

    /**
     * Проверяет данные bb-кода
     */
    public function vCheckAll(Validator $v, string $txt, $attrs, Structure $structure): string
    {
        if (! empty($v->getErrors())) {
            return $txt;
        }

        $data = $v->getData();

        unset($data['token'], $data['save']);

        foreach ($data as $key => $value) {
            if ('type_new' === $key) {
                if (isset($value[0])) {
                    $structure->type = $value;
                }
            } else {
                $structure->{$key} = $value;
            }
        }

        $error = $structure->getError();

        if (\is_array($error)) {
            if (1 === \count($error)) {
                $v->addError(\reset($error));
            } else {
                $v->addError($error);
            }
        }

        return $txt;
    }


    /**
     * Формирует данные для формы
     */
    protected function formEdit(int $id, Structure $structure): array
    {
        $form = [
            'action' => $this->formAction,
            'hidden' => [
                'token' => $this->formToken,
            ],
            'sets' => [],
            'btns'   => [
                'reset' => [
                    'class' => ['f-opacity'],
                    'type'  => 'btn',
                    'value' => __('Default structure'),
                    'link'  => $this->c->Router->link(
                        'AdminBBCodeDefault',
                        [
                            'id' => $id,
                        ]
                    ),
                ],
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save'),
                ],
            ],
        ];

        if (! $structure->isInDefault()) {
            unset($form['btns']['reset']);
        }

        $yn = [1 => __('Yes'), 0 => __('No')];

        $form['sets']['structure'] = [
            'class'  => ['structure'],
//            'legend' => ,
            'fields' => [
                'tag' => [
                    'type'      => $id > 0 ? 'str' : 'text',
                    'value'     => $structure->tag,
                    'caption'   => 'Tag label',
                    'help'      => 'Tag info',
                    'maxlength' => '11',
                    'pattern'   => '^[a-z\*][a-z\d-]{0,10}$',
                    'required'  => true,
                ],
                'type' => [
                    'type'      => 'select',
                    'options'   => $this->bbTypes,
                    'value'     => $structure->type,
                    'caption'   => 'Type label',
                    'help'      => 'Type info',
                ],
                'type_new' => [
                    'type'      => 'text',
                    'value'     => isset($this->bbTypes[$structure->type]) ? '' : $structure->type,
                    'caption'   => 'Type label',
                    'help'      => 'New type info',
                    'maxlength' => '20',
                    'pattern'   => '^[a-z][a-z\d-]{0,19}$',
                ],
                'parents' => [
                    'type'      => 'multiselect',
                    'options'   => $this->bbTypes,
                    'value'     => $structure->parents,
                    'caption'   => 'Parents label',
                    'help'      => 'Parents info',
                    'size'      => \min(15, \count($this->bbTypes)),
                    'required'  => true,
                ],
                'handler' => [
                    'class'     => ['handler'],
                    'type'      => 'textarea',
                    'value'     => $structure->handler,
                    'caption'   => 'Handler label',
                    'help'      => 'Handler info',
                ],
                'text_handler' => [
                    'class'     => ['handler'],
                    'type'      => 'textarea',
                    'value'     => $structure->text_handler,
                    'caption'   => 'Text handler label',
                    'help'      => 'Text handler info',
                ],
                'recursive' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->recursive ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Recursive label',
                    'help'    => 'Recursive info',
                ],
                'text_only' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->text_only ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Text only label',
                    'help'    => 'Text only info',
                ],
                'tags_only' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->tags_only ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Tags only label',
                    'help'    => 'Tags only info',
                ],
                'pre' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->pre ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Pre label',
                    'help'    => 'Pre info',
                ],
                'single' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->single ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Single label',
                    'help'    => 'Single info',
                ],
                'auto' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->auto ? 1 : 0,
                    'values'  => $yn,
                    'caption' => 'Auto label',
                    'help'    => 'Auto info',
                ],
                'self_nesting' => [
                    'type'    => 'number',
                    'value'   => $structure->self_nesting > 0 ? $structure->self_nesting : 0,
                    'min'     => '0',
                    'max'     => '10',
                    'caption' => 'Self nesting label',
                    'help'    => 'Self nesting info',
                ],
            ],
        ];

        $tagStr = $id > 0 ? $structure->tag : 'TAG';

        $form['sets']['no_attr'] = $this->formEditSub(
            $structure->no_attr,
            'no_attr',
            'no_attr',
            ['No attr subhead', $tagStr],
            'Allowed no_attr info'
        );

        $form['sets']['def_attr'] = $this->formEditSub(
            $structure->def_attr,
            'def_attr',
            'def_attr',
            ['Def attr subhead', $tagStr],
            'Allowed def_attr info'
        );

        foreach ($structure->other_attrs as $name => $attr) {
            $form['sets']["{$name}_attr"] = $this->formEditSub(
                $attr,
                $name,
                "{$name}_attr",
                ['Other attr subhead', $tagStr, $name],
                ['Allowed %s attr info', $name]
            );
        }

        $form['sets']['new_attr'] = $this->formEditSub(
            $structure->new_attr,
            'new_attr',
            'new_attr',
            'New attr subhead',
            'Allowed new_attr info'
        );

        return $form;
    }

    /**
     * Формирует данные для формы
     */
    protected function formEditSub(?array $data, string $name, string $class, string|array $legend, string|array $info): array
    {
        $yn     = [1 => __('Yes'), 0 => __('No')];
        $fields = [];
        $other  = \str_ends_with($name, '_attr');
        $key    = $other ? "other_attrs[{$name}]" : $name;

        if ('new_attr' === $name) {
            $fields["{$key}[name]"] = [
                'type'      => 'text',
                'value'     => $data['name'] ?? '',
                'caption'   => 'Attribute name label',
                'help'      => 'Attribute name info',
                'maxlength' => '15',
                'pattern'   => '^[a-z-]{2,15}$',
            ];
        }

        $fields["{$key}[allowed]"] = [
            'type'    => 'radio',
            'value'   => null === $data ? 0 : 1,
            'values'  => $yn,
            'caption' => 'Allowed label',
            'help'    => $info,
        ];

        if ('no_attr' !== $name) {
            $fields["{$key}[required]"] = [
                'type'    => 'radio',
                'value'   => empty($data['required']) ? 0 : 1,
                'values'  => $yn,
                'caption' => 'Required label',
                'help'    => 'Required info',
            ];
            $fields["{$key}[format]"] = [
                'class'     => ['format'],
                'type'      => 'text',
                'value'     => $data['format'] ?? '',
                'caption'   => 'Format label',
                'help'      => 'Format info',
            ];
        }
        $fields["{$key}[body_format]"] = [
            'class'     => ['format'],
            'type'      => 'text',
            'value'     => $data['body_format'] ?? '',
            'caption'   => 'Body format label',
            'help'      => 'Body format info',
        ];
        $fields["{$key}[text_only]"] = [
            'type'    => 'radio',
            'value'   => empty($data['text_only']) ? 0 : 1,
            'values'  => $yn,
            'caption' => 'Text only label',
            'help'    => 'Text only info',
        ];

        return [
            'class'  => ['attr', $class],
            'legend' => $legend,
            'fields' => $fields,
        ];
    }

    /**
     * Устанавливает структуру bb-кода по умолчанию
     */
    public function default(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminBBCodeDefault', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $id = $args['id'];

        $structure = $this->c->BBStructure
            ->fromString($this->c->bbcode->load()->bbcodeTable[$id]['bb_structure'])
            ->setDefault();

        $this->c->bbcode->update($id, $structure);

        return $this->c->Redirect->page('AdminBBCodeEdit', ['id' => $id])->message('BBCode updated redirect', FORK_MESS_SUCC);
    }
}
