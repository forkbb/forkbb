<?php

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Core\Exceptions\SmtpException;
use function \ForkBB\e;

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
     * @var string
     */
    protected $from;

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
     * var int
     */
    protected $maxRecipients = 1;

    /**
     * @var array
     */
    protected $tplHeaders = [
        'Subject'      => true,
        'Content-Type' => true,
    ];

    /**
     * @var string
     */
    protected $response;

    public function __construct(/* string */ $host, /* string */ $user, /* string */ $pass, /* bool */ $ssl, /* string */ $eol)
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
            $this->EOL = \in_array($eol, ["\r\n", "\n", "\r"]) ? $eol : \PHP_EOL;
        }
    }

    /**
     * Валидация email
     */
    public function valid(/* mixed */ $email, bool $strict = false, bool $idna = false) /* : string|false */
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
     * Устанавливает максимальное кол-во получателей в одном письме
     */
    public function setMaxRecipients(int $max): Mail
    {
        $this->maxRecipients = $max;

        return $this;
    }

    /**
     * Сброс
     */
    public function reset(): Mail
    {
        $this->to      = [];
        $this->headers = [
            'MIME-Version'              => '1.0',
            'Content-Transfer-Encoding' => '8bit',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'X-Mailer'                  => 'ForkBB Mailer',
        ];
        $this->message = null;

        return $this;
    }

    /**
     * Задает тему письма
     */
    public function setSubject(string $subject): Mail
    {
        $this->headers['Subject'] = $this->encodeText(\preg_replace('%[\x00-\x1F]%', '', \trim($subject)));

        return $this;
    }

    /**
     * Добавляет заголовок To
     */
    public function addTo(/* array|string */ $email, string $name = null): Mail
    {
        if (! \is_array($email)) {
            $email = \preg_split('%[,\n\r]%', (string) $email, -1, \PREG_SPLIT_NO_EMPTY);
        }

        foreach($email as $cur) {
            $cur = $this->valid(\trim((string) $cur), false, true);

            if (false !== $cur) {
                $this->to[$cur] = $name ?? '';
            }
        }

        return $this;
    }

    /**
     * Задает заголовок To
     */
    public function setTo(/* array|string */ $email, string $name = null): Mail
    {
        $this->to = [];

        return $this->addTo($email, $name ?? '');
    }

    /**
     * Задает заголовок From
     */
    public function setFrom(string $email, string $name = null): Mail
    {
        $email = $this->valid($email, false, true);

        if (false !== $email) {
            $this->from            = $email;
            $this->headers['From'] = $this->formatAddress($email, $name);
        }

        return $this;
    }

    /**
     * Задает заголовок Reply-To
     */
    public function setReplyTo(string $email, string $name = null): Mail
    {
        $email = $this->valid($email, false, true);

        if (false !== $email) {
            $this->headers['Reply-To'] = $this->formatAddress($email, $name);
        }

        return $this;
    }

    /**
     * Форматирование адреса
     */
    protected function formatAddress(string $email, ?string $name): string
    {
        if (
            null === $name
            || ! isset($name[0])
        ) {
            return $email;
        } else {
            $name = $this->encodeText($this->filterName($name));

            return \sprintf('"%s" <%s>', $name, $email);
        }
    }

    /**
     * Кодирование заголовка/имени
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
     */
    protected function filterName(string $name): string
    {
        return \addcslashes(\preg_replace('%[\x00-\x1F]%', '', \trim($name)), '\\"');
    }

    /**
     * Установка папки для поиска шаблонов писем
     */
    public function setFolder(string $folder): Mail
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * Установка языка для поиска шаблонов писем
     */
    public function setLanguage(string $language): Mail
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Задает сообщение по шаблону
     */
    public function setTpl(string $tpl, array $data): Mail
    {
        $file = \rtrim($this->folder, '\\/') . '/' . $this->language . '/mail/' . $tpl;

        if (! \is_file($file)) {
            throw new MailException("The template isn't found ({$file}).");
        }

        $tpl = \trim(\file_get_contents($file));

        foreach ($data as $key => $val) {
            $tpl = \str_replace('{!' . $key . '!}', (string) $val, $tpl);
        }

        if (false !== \strpos($tpl, '{{')) {
            foreach ($data as $key => $val) {
                $tpl = \str_replace('{{' . $key . '}}', e((string) $val), $tpl);
            }
        }

        if (! \preg_match('%^(.+?)(?:\r\n\r\n|\n\n|\r\r)(.+)$%s', $tpl, $matches)) {
            throw new MailException("Unknown format template ({$file}).");
        }

        foreach (\preg_split('%\r\n|\n|\r%', $matches[1]) as $line) {
            list($type, $value)  = \array_map('\\trim', \explode(':', $line, 2));

            if (! isset($this->tplHeaders[$type])) {
                throw new MailException("Unknown template header: {$type}.");
            } elseif ('' == $value) {
                throw new MailException("Empty template header: {$type}.");
            }

            if ('Subject' === $type) {
                $this->setSubject($value);
            } else {
                $this->headers[$type] = \preg_replace('%[\x00-\x1F]%', '', $value);
            }
        }

        return $this->setMessage($matches[2]);
    }

    /**
     * Задает сообщение
     */
    public function setMessage(string $message): Mail
    {
        $this->message = \str_replace("\0", $this->EOL,
                            \str_replace(["\r\n", "\n", "\r"], "\0",
                                \str_replace("\0", '', \trim($message))
                            )
                        );
//        $this->message = wordwrap ($this->message, 75, $this->EOL, false);

        return $this;
    }

    /**
     * Отправляет письмо
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

        $this->headers['Date'] = \gmdate('r');

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
        $subject = $this->headers['Subject'];
        $headers = $this->headers;
        unset($headers['Subject']);

        if (
            1 === \count($this->to)
            || $this->maxRecipients <= 1
        ) {
            foreach ($this->to as $to => $name) {
                $headers['To'] = $this->formatAddress($to, $name);
                $result        = @\mail($to, $subject, $this->message, $headers);

                if (true !== $result) {
                    return false;
                }
            }
        } else {
            $to        = $this->from;
            $arrArrBcc = \array_chunk($this->to, $this->maxRecipients, true);

            foreach ($arrArrBcc as $arrBcc) {
                foreach ($arrBcc as $email => &$name) {
                    $name = $this->formatAddress($email, $name);
                }
                unset($name);

                $headers['Bcc'] = \implode(', ', $arrBcc);
                $result         = @\mail($to, $subject, $this->message, $headers);

                if (true !== $result) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Переводит заголовки из массива в строку
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
     */
    protected function smtp(): bool
    {
        // подлючение
        if (! \is_resource($this->connect)) {
            if (false === ($connect = @\fsockopen($this->smtp['host'], $this->smtp['port'], $errno, $errstr, 5))) {
                throw new SmtpException("Couldn't connect to smtp host \"{$this->smtp['host']}:{$this->smtp['port']}\" ({$errno}) ({$errstr}).");
            }
            \stream_set_timeout($connect, 5);
            $this->connect = $connect;
            $this->smtpData(null, ['220']);
        }

        $message = $this->EOL
            . \str_replace("\n.", "\n..", $this->EOL . $this->message)
            . $this->EOL
            . '.'
            . $this->EOL;
        $headers = $this->strHeaders($this->headers);

        if (
            1 === \count($this->to)
            || $this->maxRecipients <= 1
        ) {
            $this->smtpHello();

            foreach ($this->to as $email => $name) {
                $this->smtpData("MAIL FROM:<{$this->from}>", ['250']);
                $this->smtpData("RCPT TO:<{$email}>", ['250', '251']);
                $this->smtpData('DATA', ['354']);
                $this->smtpData(
                    'To: '
                    . $this->formatAddress($email, $name)
                    . $this->EOL
                    . $headers
                    . $message,
                    ['250']
                );
                $this->smtpData('NOOP', ['250']);
            }
        } else {
            $arrRecipients = \array_chunk($this->to, $this->maxRecipients, true);

            $this->smtpHello();

            foreach ($arrRecipients as $recipients) {
                $this->smtpData("MAIL FROM:<{$this->from}>", ['250']);

                foreach ($recipients as $email => $name) {
                    $this->smtpData("RCPT TO:<{$email}>", ['250', '251']);
                }

                $this->smtpData('DATA', ['354']);
                $this->smtpData(
                    $headers
                    . $message,
                    ['250']
                );
                $this->smtpData('NOOP', ['250']);
            }
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
                $this->smtpData('EHLO ' . $this->hostname(), ['250']);

                return;
            case 0:
                if (
                    '' != $this->smtp['user']
                    && '' != $this->smtp['pass']
                ) {
                   $code = $this->smtpData('EHLO ' . $this->hostname(), ['250', '500', '501', '502', '550']);

                   if (
                       '250' === $code
                       && \preg_match('%250[- ]AUTH[ =](.+)%', $this->response, $matches)
                    ) {
                        $methods = \array_flip(
                            \array_map(
                                '\\trim',
                                \explode(' ', $matches[1])
                            )
                        );

                        if (isset($methods['CRAM-MD5'])) {
                            $this->smtpData('AUTH CRAM-MD5', ['334']);
                            $challenge = \base64_decode(
                                \trim(
                                    \substr($this->response, 4)
                                )
                            );
                            $digest    = \hash_hmac('md5', $challenge, $this->smtp['pass']);
                            $cramMd5   = \base64_encode("{$this->smtp['user']} {$digest}");
                            $this->smtpData($cramMd5, ['235']);
                            $this->auth = 1;

                            return;
                        } elseif (isset($methods['LOGIN'])) {
                            $this->smtpData('AUTH LOGIN', ['334']);
                            $this->smtpData(\base64_encode($this->smtp['user']), ['334']);
                            $this->smtpData(\base64_encode($this->smtp['pass']), ['235']);
                            $this->auth = 1;

                            return;
                        } elseif (isset($methods['PLAIN'])) {
                            $plain = \base64_encode("\0{$this->smtp['user']}\0{$this->smtp['pass']}");
                            $this->smtpData("AUTH PLAIN {$plain}", ['235']);
                            $this->auth = 1;

                            return;
                        }
                    }
                }
            default:
                $this->auth = -1;
                $this->smtpData('HELO ' . $this->hostname(), ['250']);
        }
    }

    /**
     * Отправляет данные на сервер
     * Проверяет ответ
     * Возвращает код ответа
     */
    protected function smtpData(?string $data, ?array $code): string
    {
        if (\is_resource($this->connect) && null !== $data) {
            if (false === @\fwrite($this->connect, $data . $this->EOL)) {
                throw new SmtpException('Couldn\'t send data to mail server.');
            }
        }

        $this->response = '';
        while (\is_resource($this->connect) && ! \feof($this->connect)) {
            if (false === ($get = @\fgets($this->connect, 512))) {
                throw new SmtpException('Couldn\'t get mail server response codes.');
            }

            $this->response .= $get;

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
            && ! \in_array($return, $code)
        ) {
            throw new SmtpException("Unable to send email. Response of mail server: \"{$this->response}\"");
        }

        return $return;
    }

    /**
     * Возвращает имя сервера или его ip
     */
    protected function hostname(): string
    {
        return empty($_SERVER['SERVER_NAME'])
            ? (isset($_SERVER['SERVER_ADDR']) ? '[' . $_SERVER['SERVER_ADDR'] . ']' : '[127.0.0.1]')
            : $_SERVER['SERVER_NAME'];
    }

    /**
     * Завершает сеанс smtp
     */
    public function __destruct()
    {
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
