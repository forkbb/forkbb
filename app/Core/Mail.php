<?php

namespace ForkBB\Core;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Core\Exceptions\SmtpException;

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
     *
     * @param mixed $host
     * @param mixed $user
     * @param mixed $pass
     * @param mixed $ssl
     * @param mixed $eol
     */
    public function __construct($host, $user, $pass, $ssl, $eol)
    {
        if (
            \is_string($host)
            && \strlen(\trim($host)) > 0
        ) {
            list($host, $port) = \explode(':', $host);
            if (
                empty($port)
                || $port < 1
                || $port > 65535
            ) {
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
            $this->EOL = \in_array($eol, ["\r\n", "\n", "\r"]) ? $eol : PHP_EOL;
        }
    }

    /**
     * Валидация email
     *
     * @param mixed $email
     * @param bool $strict
     * @param bool $idna
     *
     * @return false|string
     */
    public function valid($email, bool $strict = false, bool $idna = false)
    {
        if (
            ! \is_string($email)
            || \mb_strlen($email, 'UTF-8') > 80 //???? for DB
            || ! \preg_match(
                '%^(?!\.)((?:(?:^|\.)(?>"(?!\s)(?:\x5C[^\x00-\x1F]|[^\x00-\x1F\x5C"])++(?<!\s)"|[a-zA-Z0-9!#$\%&\'*+/=?^_`{|}~-]+))+)@([^\x00-\x1F\s@]++)$%Du',
                    $email,
                    $matches
                )
            || \mb_strlen($matches[1], 'UTF-8') > 64
        ) {
            return false;
        }
        $local  = $matches[1];
        $domain = $matches[2];

        if (
            '[' === $domain[0]
            && ']' === \substr($domain, -1)
        ) {
            if (1 === \strpos($domain, 'IPv6:')) {
                $prefix = 'IPv6:';
                $ip     = \substr($domain, 6, -1);
            } else {
                $prefix = '';
                $ip     = \substr($domain, 1, -1);
            }
            $ip = \strtoupper($ip);

            if (false === \filter_var($ip, \FILTER_VALIDATE_IP)) {
                return false;
            }

            $domainASCII = $domain = "[{$prefix}{$ip}]";
        } else {
            $ip          = null;
            $domainASCII = $domain = \mb_strtolower($domain, 'UTF-8');

            if (
                \preg_match('%[\x80-\xFF]%', $domain)
                && \function_exists('\\idn_to_ascii')
            ) {
                $domainASCII = \idn_to_ascii($domain, 0, \INTL_IDNA_VARIANT_UTS46);
            }

            if (
                'localhost' == $domain
                || ! \preg_match('%^(?:(?:xn\-\-)?[a-z0-9]+(?:\-[a-z0-9]+)*(?:$|\.(?!$)))+$%', $domainASCII)
            ) {
                return false;
            }
        }

        if ($strict) {
            if ($ip) {
                $mx = @\checkdnsrr($ip, 'MX'); // ???? ipv6?
            } else {
                $mx = @\dns_get_record($domainASCII, \DNS_MX);
            }
            if (empty($mx)) {
                return false;
            }
        }

        return $local . '@' . ($idna ? $domainASCII : $domain);
    }

    /**
     * Сброс
     *
     * @return Mail
     */
    public function reset(): self
    {
        $this->to = [];
        $this->headers = [];
        $this->message = null;
        return $this;
    }

    /**
     * Задает тему письма
     *
     * @param string $subject
     *
     * @return Mail
     */
    public function setSubject(string $subject): self
    {
        $this->headers['Subject'] = $this->encodeText(\preg_replace('%[\x00-\x1F]%', '', \trim($subject)));
        return $this;
    }

    /**
     * Добавляет заголовок To
     *
     * @param string|array $email
     * @param string $name
     *
     * @return Mail
     */
    public function addTo($email, string $name = null): self
    {
        if (! \is_array($email)) {
            $email = \preg_split('%[,\n\r]%', (string) $email, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach($email as $cur) {
            $cur = $this->valid(\trim((string) $cur), false, true);
            if (false !== $cur) {
                $this->to[] = $this->formatAddress($cur, $name);
            }
        }
        return $this;
    }

    /**
     * Задает заголовок To
     *
     * @param string|array $email
     * @param string $name
     *
     * @return Mail
     */
    public function setTo($email, string $name = null): self
    {
        $this->to = [];
        return $this->addTo($email, $name);
    }

    /**
     * Задает заголовок From
     *
     * @param string $email
     * @param string $name
     *
     * @return Mail
     */
    public function setFrom(string $email, string $name = null): self
    {
        $email = $this->valid($email, false, true);
        if (false !== $email) {
            $this->headers['From'] = $this->formatAddress($email, $name);
        }
        return $this;
    }

    /**
     * Задает заголовок Reply-To
     *
     * @param string $email
     * @param string $name
     *
     * @return Mail
     */
    public function setReplyTo(string $email, string $name = null): self
    {
        $email = $this->valid($email, false, true);
        if (false !== $email) {
            $this->headers['Reply-To'] = $this->formatAddress($email, $name);
        }
        return $this;
    }

    /**
     * Форматирование адреса
     *
     * @param string|array $email
     * @param string $name
     *
     * @return string
     */
    protected function formatAddress($email, string $name = null): string
    {
        if (
            ! \is_string($name)
            || 0 == \strlen(\trim($name))
        ) {
            return $email;
        } else {
            $name = $this->encodeText($this->filterName($name));
            return \sprintf('"%s" <%s>', $name, $email);
        }
    }

    /**
     * Кодирование заголовка/имени
     *
     * @param string $str
     *
     * @return string
     */
    protected function encodeText(string $str): string
    {
        if (\preg_match('%[^\x20-\x7F]%', $str)) {
            return '=?UTF-8?B?' . \base64_encode($str) . '?=';
        } else {
            return $str;
        }
    }

    /**
     * Фильтрация имени
     *
     * @param string $name
     *
     * @return string
     */
    protected function filterName(string $name): string
    {
        return \addcslashes(\preg_replace('%[\x00-\x1F]%', '', \trim($name)), '\\"');
    }

    /**
     * Установка папки для поиска шаблонов писем
     *
     * @param string $folder
     *
     * @return Mail
     */
    public function setFolder(string $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Установка языка для поиска шаблонов писем
     *
     * @param string $language
     *
     * @return Mail
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Задает сообщение по шаблону
     *
     * @param string $tpl
     * @param array $data
     *
     * @throws MailException
     *
     * @return Mail
     */
    public function setTpl(string $tpl, array $data): self
    {
        $file = \rtrim($this->folder, '\\/') . '/' . $this->language . '/mail/' . $tpl;
        if (! \is_file($file)) {
            throw new MailException('The template isn\'t found (' . $file . ').');
        }
        $tpl = \trim(\file_get_contents($file));
        foreach ($data as $key => $val) {
            $tpl = \str_replace('<' . $key . '>', (string) $val, $tpl);
        }
        list($subject, $tpl) = \explode("\n", $tpl, 2);
        if (! isset($tpl)) {
            throw new MailException('The template is empty (' . $file . ').');
        }
        $this->setSubject(\substr($subject, 8));
        return $this->setMessage($tpl);
    }

    /**
     * Задает сообщение
     *
     * @param string $message
     *
     * @return Mail
     */
    public function setMessage(string $message): self
    {
        $this->message = \str_replace("\0", $this->EOL,
                         \str_replace(["\r\n", "\n", "\r"], "\0",
                         \str_replace("\0", '', \trim($message))));
//        $this->message = wordwrap ($this->message, 75, $this->EOL, false);
        return $this;
    }

    /**
     * Отправляет письмо
     *
     * @throws MailException
     *
     * @return bool
     */
    public function send(): bool
    {
        if (empty($this->to)) {
            throw new MailException('No recipient for the email.');
        }
        if (empty($this->headers['From'])) {
            throw new MailException('No sender for the email.');
        }
        if (! isset($this->headers['Subject'])) {
            throw new MailException('The subject of the email is empty.');
        }
        if ('' == \trim($this->message)) {
            throw new MailException('The body of the email is empty.');
        }

        $this->headers = \array_replace($this->headers, [
            'Date'                      => \gmdate('r'),
            'MIME-Version'              => '1.0',
            'Content-transfer-encoding' => '8bit',
            'Content-type'              => 'text/plain; charset=utf-8',
            'X-Mailer'                  => 'ForkBB Mailer',
        ]);

        if (\is_array($this->smtp)) {
            return $this->smtp();
        } else {
            return $this->mail();
        }
    }

    /**
     * Отправка письма через функцию mail
     *
     * @return bool
     */
    protected function mail(): bool
    {
        $to      = \implode(', ', $this->to);
        $subject = $this->headers['Subject'];
        $headers = $this->headers;
        unset($headers['Subject']);
        $headers = $this->strHeaders($headers);
        return @\mail($to, $subject, $this->message, $headers);
    }

    /**
     * Переводит заголовки из массива в строку
     *
     * @param array $headers
     *
     * @return string
     */
    protected function strHeaders(array $headers): string
    {
        foreach ($headers as $key => &$value) {
            $value = $key . ': ' . $value;
        }
        unset($value);
        return \implode($this->EOL, $headers);
    }

    /**
     * Отправка письма через smtp
     *
     * @throws SmtpException
     *
     * @return bool
     */
    protected function smtp(): bool
    {
        // подлючение
        if (! \is_resource($this->connect)) {
            if (false === ($connect = @\fsockopen($this->smtp['host'], $this->smtp['port'], $errno, $errstr, 5))) {
                throw new SmtpException('Couldn\'t connect to smtp host "' . $this->smtp['host'] . ':' . $this->smtp['port'] . '" (' . $errno . ') (' . $errstr . ').');
            }
            \stream_set_timeout($connect, 5);
            $this->connect = $connect;
            $this->smtpData(null, '220');
        }

        $message = $this->EOL . \str_replace("\n.", "\n..", $this->EOL . $this->message) . $this->EOL . '.';
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

    /**
     * Hello SMTP server
     */
    protected function smtpHello(): void
    {
        switch ($this->auth) {
            case 1:
                $this->smtpData('EHLO ' . $this->hostname(), '250');
                return;
            case 0:
                if (
                    '' != $this->smtp['user']
                    && '' != $this->smtp['pass']
                ) {
                   $code = $this->smtpData('EHLO ' . $this->hostname(), ['250', '500', '501', '502', '550']);
                   if ('250' === $code) {
                       $this->smtpData('AUTH LOGIN', '334');
                       $this->smtpData(\base64_encode($this->smtp['user']), '334');
                       $this->smtpData(\base64_encode($this->smtp['pass']), '235');
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
     * Отправляет данные на сервер
     * Проверяет ответ
     * Возвращает код ответа
     *
     * @param string $data
     * @param mixed $code
     *
     * @throws SmtpException
     *
     * @return string
     */
    protected function smtpData(string $data, $code): string
    {
        if (\is_resource($this->connect)) {
            if (false === @\fwrite($this->connect, $data . $this->EOL)) {
                throw new SmtpException('Couldn\'t send data to mail server.');
            }
        }
        $response = '';
        while (\is_resource($this->connect) && ! \feof($this->connect)) {
            if (false === ($get = @\fgets($this->connect, 512))) {
                throw new SmtpException('Couldn\'t get mail server response codes.');
            }
            $response .= $get;
            if (
                isset($get[3])
                && ' ' === $get[3]
            ) {
                $return = \substr($get, 0, 3);
                break;
            }
        }
        if (
            null !== $code
            && ! \in_array($return, (array) $code)
        ) {
            throw new SmtpException('Unable to send email. Response of mail server: "' . $get . '"');
        }
        return $return;
    }

    /**
     * Выделяет email из заголовка
     *
     * @param string $str
     *
     * @return string
     */
    protected function getEmailFrom(string $str): string
    {
        $match = \explode('" <', $str);
        if (
            2 == \count($match)
            && '>' == \substr($match[1], -1)
        ) {
            return \rtrim($match[1], '>');
        } else {
            return $str;
        }
    }

    /**
     * Возвращает имя сервера или его ip
     *
     * @return string
     */
    protected function hostname(): string
    {
        return empty($_SERVER['SERVER_NAME'])
            ? (isset($_SERVER['SERVER_ADDR']) ? '[' . $_SERVER['SERVER_ADDR'] . ']' : '[127.0.0.1]')
            : $_SERVER['SERVER_NAME'];
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        // завершение сеанса smtp
        if (\is_resource($this->connect)) {
            try {
                $this->smtpData('QUIT', null);
            } catch (MailException $e) {
                //????
            }
            @\fclose($this->connect);
        }
    }
}
