<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Rules extends Page
{
    use CrumbTrait;

    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function view()
    {
        $this->fIndex     = 'rules';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->canonical  = $this->c->Router->link('Rules');
        $this->title      = \ForkBB\__('Forum rules');
        $this->crumbs     = $this->crumbs([$this->c->Router->link('Rules'), \ForkBB\__('Forum rules')]);
        $this->rules      = $this->c->config->o_rules_message;
        $this->formAction = null;

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function confirmation()
    {
        $this->c->Lang->load('register');

        $this->fIndex     = 'register';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->robots     = 'noindex';
        $this->title      = \ForkBB\__('Forum rules');
        $this->crumbs     = $this->crumbs(\ForkBB\__('Forum rules'), [$this->c->Router->link('Register'), \ForkBB\__('Register')]);
        $this->rules      = $this->c->config->o_rules == '1' ? $this->c->config->o_rules_message : \ForkBB\__('If no rules');
        $this->formAction = $this->c->Router->link('RegisterForm');
        $this->formToken  = $this->c->Csrf->create('RegisterForm');
        $this->formHash   = $this->c->Csrf->create('Register');

        return $this;
    }
}
