<?php

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\BBCodeList\Structure;
use ForkBB\Models\Pages\Admin\Parser;
use function \ForkBB\__;

class BBCode extends Parser
{
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

                return $this->c->Redirect->page('AdminBBCode')->message('Parser settings updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aCrumbs[] = [
            $this->c->Router->link('AdminBBCode'),
            __('BBCode management'),
        ];
        $this->form      = $this->formView();
        $this->titleForm = __('BBCode head');
        $this->classForm = 'bbcode';

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function formView(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminBBCode'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminBBCode'),
            ],
            'sets' => [
                'bbcode-legend' => [
                    'class'  => 'bbcode-legend',
                    'legend' => __('BBCode list subhead'),
                    'fields' => [],
                ],
            ],
            'btns'   => [
                'save' => [
                    'type'      => 'submit',
                    'value'     => __('Save changes'),
//                    'accesskey' => 's',
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
                'caption'   => __('BBCode tag label'),
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
                'caption'   => __('BBCode mes label'),
                'disabled'  => 'ROOT' === $tag,
            ];
            $fields["bbcode[{$tag}][in_sig]"] = [
                'class'     => ['bbcode', 'in_sig'],
                'type'      => 'select',
                'options'   => $selectList,
                'value'     => $this->getValue($tag, $this->c->config->a_bb_white_sig, $this->c->config->a_bb_black_sig),
                'caption'   => __('BBCode sig label'),
                'disabled'  => 'ROOT' === $tag,
            ];
            $fields["bbcode{$id}-del"] = [
                'class'     => ['bbcode', 'delete'],
                'type'      => 'btn',
                'value'     => '❌',
                'caption'   => __('Delete'),
                'title'     => __('Delete'),
                'link'      => $this->c->Router->link(
                    'AdminBBCodeDelete',
                    [
                        'id'    => $id,
                        'token' => null,
                    ]
                ),
                'disabled'  => 1 !== $tagData['bb_delete'],
            ];

            $form['sets']["bbcode{$id}"] = [
                'class'  => 'bbcode',
                'legend' => __('BBCode %s', $tag),
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
        if (! $this->c->Csrf->verify($args['token'], 'AdminBBCodeDelete', $args)) {
            return $this->c->Message->message('Bad token');
        }

        $this->c->bbcode->delete((int) $args['id']);

        return $this->c->Redirect->page('AdminBBCode')->message('BBCode deleted redirect');
    }

    /**
     * Редактирование/добавление нового bbcode
     */
    public function edit(array $args, string $method): Page
    {
        $this->c->bbcode->load();

        $structure = $this->c->BBStructure;
        $id        = isset($args['id']) ? (int) $args['id'] : 0;
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
        foreach ($this->c->bbcode->bbcodeTable as $cur) {
            $type = $this->c->BBStructure->fromString($cur['bb_structure'])->type;
            $bbTypes[$type] = $type;
        }
        $this->bbTypes = $bbTypes;

        if ('POST' === $method) {
        }


        if ($id > 0) {
            $title            = __('Edit bbcode head');
            $this->formAction = $this->c->Router->link('AdminBBCodeEdit', ['id' => $id]);
            $this->formToken  = $this->c->Csrf->create('AdminBBCodeEdit', ['id' => $id]);
        } else {
            $title            = __('Add bbcode head');
            $this->formAction = $this->c->Router->link('AdminBBCodeNew');
            $this->formToken  = $this->c->Csrf->create('AdminBBCodeNew');
        }
        $this->aCrumbs[] = [
            $this->formAction,
            $title,
        ];
        if ($id > 0) {
            $this->aCrumbs[] = __('"%s"', $this->c->bbcode->bbcodeTable[$id]['bb_tag']);
        }
        $this->aCrumbs[] = [
            $this->c->Router->link('AdminBBCode'),
            __('BBCode management'),
        ];
        $this->form      = $this->formEdit($id, $structure);
        $this->titleForm = $title;
        $this->classForm = 'editbbcode';
        $this->nameTpl   = 'admin/form';

        return $this;
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
                'save' => [
                    'type'      => 'submit',
                    'value'     => __('Save'),
//                    'accesskey' => 's',
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];

        $form['sets']["structure"] = [
            'class'  => 'structure',
//            'legend' => ,
            'fields' => [
                'tag' => [
                    'type'      => $id > 0 ? 'str' : 'text',
                    'value'     => $structure->tag,
                    'caption'   => __('Tag label'),
                    'info'      => __('Tag info'),
                    'maxlength' => 11,
                    'pattern'   => '^[a-z\*][a-z\d-]{0,10}$',
                    'required'  => true,
                ],
                'type' => [
                    'type'      => 'select',
                    'options'   => $this->bbTypes,
                    'value'     => $structure->type,
                    'caption'   => __('Type label'),
                    'info'      => __('Type info'),
                ],
                'type_new' => [
                    'type'      => 'text',
                    'value'     => isset($this->bbTypes[$structure->type]) ? '' : $structure->type,
                    'caption'   => __('Type label'),
                    'info'      => __('New type info'),
                    'maxlength' => 20,
                    'pattern'   => '^[a-z][a-z\d-]{0,19}$',
                ],
                'parents' => [
                    'type'      => 'multiselect',
                    'options'   => $this->bbTypes,
                    'value'     => $structure->parents,
                    'caption'   => __('Parents label'),
                    'info'      => __('Parents info'),
                    'size'      => \min(15, \count($this->bbTypes)),
                    'required'  => true,
                ],
                'handler' => [
                    'class'     => 'handler',
                    'type'      => 'textarea',
                    'value'     => $structure->handler,
                    'caption'   => __('Handler label'),
                    'info'      => __('Handler info'),
                ],
                'text_handler' => [
                    'class'     => 'handler',
                    'type'      => 'textarea',
                    'value'     => $structure->text_handler,
                    'caption'   => __('Text handler label'),
                    'info'      => __('Text handler info'),
                ],
                'recursive' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->recursive ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Recursive label'),
                    'info'    => __('Recursive info'),
                ],
                'text_only' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->text_only ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Text only label'),
                    'info'    => __('Text only info'),
                ],
                'tags_only' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->tags_only ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Tags only label'),
                    'info'    => __('Tags only info'),
                ],
                'pre' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->pre ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Pre label'),
                    'info'    => __('Pre info'),
                ],
                'single' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->single ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Single label'),
                    'info'    => __('Single info'),
                ],
                'auto' => [
                    'type'    => 'radio',
                    'value'   => true === $structure->auto ? 1 : 0,
                    'values'  => $yn,
                    'caption' => __('Auto label'),
                    'info'    => __('Auto info'),
                ],
                'self_nesting' => [
                    'type'    => 'number',
                    'value'   => $structure->self_nesting > 0 ? $structure->self_nesting : 0,
                    'min'     => 0,
                    'max'     => 10,
                    'caption' => __('Self nesting label'),
                    'info'    => __('Self nesting info'),
                ],
            ],
        ];

        $tagStr = $id > 0 ? $structure->tag : 'TAG';

        $form['sets']["no_attr"] = [
            'class'  => ['attr', 'no_attr'],
            'legend' => __('No attr subhead', $tagStr),
            'fields' => [
                'no_attr[allowed]' => [
                    'type'    => 'radio',
                    'value'   => null === $structure->no_attr ? 0 : 1,
                    'values'  => $yn,
                    'caption' => __('Allowed label'),
                    'info'    => __('Allowed no_attr info'),
                ],
                'no_attr[body_format]' => [
                    'class'     => 'format',
                    'type'      => 'text',
                    'value'     => $structure->no_attr['body_format'] ?? '',
                    'caption'   => __('Body format label'),
                    'info'      => __('Body format info'),
                ],
                'no_attr[text_only]' => [
                    'type'    => 'radio',
                    'value'   => empty($structure->no_attr['text_only']) ? 0 : 1,
                    'values'  => $yn,
                    'caption' => __('Text only label'),
                    'info'    => __('Text only info'),
                ],
            ],
        ];

        $form['sets']["def_attr"] = [
            'class'  => ['attr', 'def_attr'],
            'legend' => __('Def attr subhead', $tagStr),
            'fields' => [
                'def_attr[allowed]' => [
                    'type'    => 'radio',
                    'value'   => null === $structure->def_attr ? 0 : 1,
                    'values'  => $yn,
                    'caption' => __('Allowed label'),
                    'info'    => __('Allowed def_attr info'),
                ],
                'def_attr[required]' => [
                    'type'    => 'radio',
                    'value'   => empty($structure->def_attr['required']) ? 0 : 1,
                    'values'  => $yn,
                    'caption' => __('Required label'),
                    'info'    => __('Required info'),
                ],
                'def_attr[format]' => [
                    'class'     => 'format',
                    'type'      => 'text',
                    'value'     => $structure->def_attr['format'] ?? '',
                    'caption'   => __('Format label'),
                    'info'      => __('Format info'),
                ],
                'def_attr[body_format]' => [
                    'class'     => 'format',
                    'type'      => 'text',
                    'value'     => $structure->def_attr['body_format'] ?? '',
                    'caption'   => __('Body format label'),
                    'info'      => __('Body format info'),
                ],
                'def_attr[text_only]' => [
                    'type'    => 'radio',
                    'value'   => empty($structure->def_attr['text_only']) ? 0 : 1,
                    'values'  => $yn,
                    'caption' => __('Text only label'),
                    'info'    => __('Text only info'),
                ],
            ],
        ];

        $fields = [];

        return $form;
    }
}
