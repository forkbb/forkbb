<?php

namespace ForkBB\Core;

use RuntimeException;

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
     * @var array
     */
    protected $to = [];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $smtp;

    /**
     * @var string
     */
    protected $EOL;

    /**
     * @var Resource
     */
    protected $connect;

    /**
     * var int
     */
    protected $auth = 0;

    /**
     * Конструктор
     * @param mixed $host
     * @param mixed $user
     * @param mixed $pass
     * @param mixed $ssl
     * @param mixed $eol
     */
    public function __construct($host, $user, $pass, $ssl, $eol)
    {
        if (is_string($host) && strlen(trim($host)) > 0 ) {
            list ($host, $port) = explode(':', $host);
            if (empty($port) || $port < 1 || $port > 65535) {
                $port = 25;
            }
            $this->smtp = [
                'host' => ($ssl ? 'ssl://' : '') . $host,
                'port' => (int) $port,
                'user' => (string) $user,
                'pass' => (string) $pass,
            ];
            $this->EOL = "\r\n";
        } else {
            $this->EOL = in_array($eol, ["\r\n", "\n", "\r"]) ? $eol : PHP_EOL;
        }
    }

    /**
     * Валидация email
     * @param mixed $email
     * @return bool
     */
    public function valid($email)
    {
        return is_string($email)
            && strlen($email) <= 80
            && trim($email) === $email
            && preg_match('%^.+@.+$%D', $email);
    }

    /**
     * Сброс
     * @return Mail
     */
    public function reset()
    {
        $this->to = [];
        $this->headers = [];
        $this->message = null;
        return $this;
    }

    /**
     * Задает тему письма
     * @param string $subject
     * @return Mail
     */
    public function setSubject($subject)
    {
        $this->headers['Subject'] = $this->encodeText(preg_replace('%[\x00-\x1F]%', '', trim($subject)));
        return $this;
    }

    /**
     * Добавляет заголовок To
     * @param string|array $email
     * @param string $name
     * @return Mail
     */
    public function addTo($email, $name = null)
    {
        if (is_array($email)) {
        } else {
            $email = preg_split('%[,\n\r]%', (string) $email, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach($email as $cur) {
            $cur = trim((string) $cur);
            if ($this->valid($cur)) {
                $this->to[] = $this->formatAddress($cur, $name);
            }
        }
        return $this;
    }

    /**
     * Задает заголовок To
     * @param string|array $email
     * @param string $name
     * @return Mail
     */
    public function setTo($email, $name = null)
    {
        $this->to = [];
        return $this->addTo($email, $name);
    }

    /**
     * Задает заголовок From
     * @param string $email
     * @param string $name
     * @return Mail
     */
    public function setFrom($email, $name = null)
    {
        if ($this->valid($email)) {
            $this->headers['From'] = $this->formatAddress($email, $name);
        }
        return $this;
    }

    /**
     * Задает заголовок Reply-To
     * @param string $email
     * @param string $name
     * @return Mail
     */
    public function setReplyTo($email, $name = null)
    {
        if ($this->valid($email)) {
            $this->headers['Reply-To'] = $this->formatAddress($email, $name);
        }
        return $this;
    }

    /**
     * Форматирование адреса
     * @param string|array $email
     * @param string $name
     * @return string
     */
    protected function formatAddress($email, $name = null)
    {
        $email = $this->filterEmail($email);
        if (null === $name || ! is_string($name) || strlen(trim($name)) == 0) {
            return $email;
        } else {
            $name = $this->encodeText($this->filterName($name));
            return sprintf('"%s" <%s>', $name, $email);
        }
    }

    /**
     * Кодирование заголовка/имени
     * @param string $str
     * @return string
     */
    protected function encodeText($str)
    {
        if (preg_match('%[^\x20-\x7F]%', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        } else {
            return $str;
        }
    }

    /**
     * Фильтрация email
     * @param string $email
     * @return string
     */
    protected function filterEmail($email)
    {
        return preg_replace('%[\x00-\x1F",<>]%', '', $email);
    }

    /**
     * Фильтрация имени
     * @param string $name
     * @return string
     */
    protected function filterName($name)
    {
        return strtr(trim($name), [
            "\r" => '',
            "\n" => '',
            "\t" => '',
            '"'  => '\'',
            '<'  => '[',
            '>'  => ']',
        ]);
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
     * Задает сообщение по шаблону
     * @param string $tpl
     * @param array $data
     * @throws \RuntimeException
     * @return Mail
     */
    public function setTpl($tpl, array $data)
    {
        $file = rtrim($this->folder, '\\/') . '/' . $this->language . '/mail/' . $tpl;
        if (! file_exists($file)) {
            throw new RuntimeException('Tpl not found');
        }
        $tpl = trim(file_get_contents($file));
        foreach ($data as $key => $val) {
            $tpl = str_replace('<' . $key . '>', (string) $val, $tpl);
        }
        list($subject, $tpl) = explode("\n", $tpl, 2);
        if (! isset($tpl)) {
            throw new RuntimeException('Tpl empty');
        }
        $this->setSubject(substr($subject, 8));
        return $this->setMessage($tpl);
    }

    /**
     * Задает сообщение
     * @param string $message
     * @throws \RuntimeException
     * @return Mail
     */
    public function setMessage($message)
    {
        $this->message = str_replace("\0", $this->EOL,
                         str_replace(["\r\n", "\n", "\r"], "\0",
                         str_replace("\0", '', trim($message))));
//        $this->message = wordwrap ($this->message, 75, $this->EOL, false);
        return $this;
    }

    /**
     * Отправляет письмо
     * @return bool
     */
    public function send()
    {
        if (empty($this->to)) {
            throw new RuntimeException('No recipient(s)');
        }
        if (empty($this->headers['From'])) {
            throw new RuntimeException('No sender');
        }
        if (! isset($this->headers['Subject'])) {
            throw new RuntimeException('Subject empty');
        }
        if (trim($this->message) == '') {
            throw new RuntimeException('Message empty');
        }

        $this->headers = array_replace($this->headers, [
            'Date' => gmdate('r'),
            'MIME-Version' => '1.0',
            'Content-transfer-encoding' => '8bit',
            'Content-type' => 'text/plain; charset=utf-8',
            'X-Mailer' => 'ForkBB Mailer',
        ]);

        if (is_array($this->smtp)) {
            return $this->smtp();
        } else {
            return $this->mail();
        }
    }

    /**
     * Отправка письма через функцию mail
     * @return bool
     */
    protected function mail()
    {
        $to = implode(', ', $this->to);
        $subject = $this->headers['Subject'];
        $headers = $this->headers;
        unset($headers['Subject']);
        $headers = $this->strHeaders($headers);
        return @mail($to, $subject, $this->message, $headers);
    }

    /**
     * Переводит заголовки из массива в строку
     * @param array $headers
     * @return string
     */
    protected function strHeaders(array $headers)
    {
        foreach ($headers as $key => &$value) {
            $value = $key . ': ' . $value;
        }
        unset($value);
        return join($this->EOL, $headers);
    }

    /**
     * Отправка письма через smtp
     * @throws \RuntimeException
     * @return bool
     */
    protected function smtp()
    {
        // подлючение
        if (! is_resource($this->connect)) {
            if (($connect = @fsockopen($this->smtp['host'], $this->smtp['port'], $errno, $errstr, 5)) === false) {
                throw new RuntimeException('Could not connect to smtp host "' . $this->smtp['host'] . '" (' . $errno . ') (' . $errstr . ')');
            }
            stream_set_timeout($connect, 5);
            $this->connect = $connect;
            $this->smtpData(null, '220');
        }

        $message = $this->EOL . str_replace("\n.", "\n..", $this->EOL . $this->message) . $this->EOL . '.';
        $headers = $this->strHeaders($this->headers);

        // цикл по получателям
        foreach ($this->to as $to) {
            $this->smtpHello();
            $this->smtpData('MAIL FROM: <' . $this->getEmailFrom($this->headers['From']). '>', '250');
            $this->smtpData('RCPT TO: <' . $this->getEmailFrom($to) . '>', ['250', '251']);
            $this->smtpData('DATA', '354');
            $this->smtpData('To: ' . $to . $this->EOL . $headers . $message, '250');
            $this->smtpData('NOOP', '250');
        }
        return true;
    }

    public function __destruct()
    {
        // завершение сеанса smtp
        if (is_resource($this->connect)) {
            $this->smtpData('QUIT', null);
            @fclose($this->connect);
        }
    }

    /**
     * Hello SMTP server
     */
    protected function smtpHello()
    {
        switch ($this->auth) {
            case 1:
                $this->smtpData('EHLO ' . $this->hostname(), '250');
                return;
            case 0:
                if ($this->smtp['user'] != '' && $this->smtp['pass'] != '') {
                   $code = $this->smtpData('EHLO ' . $this->hostname(), ['250', '500', '501', '502', '550']);
                   if ($code === '250') {
                       $this->smtpData('AUTH LOGIN', '334');
                       $this->smtpData(base64_encode($this->smtp['user']), '334');
                       $this->smtpData(base64_encode($this->smtp['pass']), '235');
                       $this->auth = 1;
                       return;
                   }
                }
            default:
                $this->auth = -1;
                $this->smtpData('HELO ' . $this->hostname(), '250');
        }
    }

    /**
     * @param string $data
     * @param mixed $code
     * @throws \RuntimeException
     * @return string
     */
    protected function smtpData($data, $code)
    {
//var_dump($data);
        if (is_string($data)) {
            @fwrite($this->connect, $data . $this->EOL);
        }

        $response = '';
//        while (! isset($get{3}) || $get{3} !== ' ') {
        while (is_resource($this->connect) && !feof($this->connect)) {
            if (($get = @fgets($this->connect, 512)) === false) {
                throw new RuntimeException('Couldn\'t get mail server response codes');
            }
            $response .= $get;
            if (isset($get{3}) && $get{3} === ' ') {
                $return = substr($get, 0, 3);
                break;
            }
        }
//var_dump($response);
        if ($code !== null && ! in_array($return, (array) $code)) {
            throw new RuntimeException('Unable to send email. Response of the SMTP server: "'.$get.'"');
        }
        return $return;
    }

    /**
     * Выделяет email из заголовка
     * @param string $str
     * @return string
     */
    protected function getEmailFrom($str)
    {
        $match = explode('" <', $str);
        if (count($match) == 2 && substr($match[1], -1) == '>') {
            return rtrim($match[1], '>');
        } else {
            return $str;
        }
    }

    /**
     * Возвращает имя сервера или его ip
     * @return string
     */
    protected function hostname()
    {
        return empty($_SERVER['SERVER_NAME'])
            ? (isset($_SERVER['SERVER_ADDR']) ? '[' . $_SERVER['SERVER_ADDR'] . ']' : '[127.0.0.1]')
            : $_SERVER['SERVER_NAME'];
    }
}
