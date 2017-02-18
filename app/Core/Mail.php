<?php

namespace ForkBB\Core;

class Mail
{
    /**
     * @var string
     */
    protected $folder;

    /**
     * @var string
     */
    protected $language;

    /**
     * Валидация email
     * @param mixed $email
     * @return bool
     */
    public function valid($email)
    {
        return is_string($email)
            && strlen($email) <= 80
            && preg_match('%^.+@.+$%D', $email);
    }

    /**
     * Установка папки для поиска шаблонов писем
     * @param string $folder
     * @return Mail
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Установка языка для поиска шаблонов писем
     * @param string $language
     * @return Mail
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Отправка письма
     * @param string $email
     * @param string $tpl
     * @param array $data
     * @return bool
     */
    public function send($email, $tpl, array $data)
    {
        var_dump($data);
        return true;
    }
}
