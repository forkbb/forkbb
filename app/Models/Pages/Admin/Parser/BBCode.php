<?php

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Parser;
use ForkBB\Models\Config\Model as Config;
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
}
