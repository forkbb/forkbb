<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Core\Exceptions\SmtpException;
use SensitiveParameter;
use function \ForkBB\e;

class Mail
{
    protected string $folder;
    protected string $language;
    protected string $from;
    protected array $to = [];
    protected array $headers = [];
    protected string $message;
    protected ?array $smtp = null;
    protected string $EOL;
    protected $connect;
    protected int $auth = 0;
    protected int $maxRecipients = 1;
    protected array $tplHeaders = [
        'Subject'      => true,
        'Content-Type' => true,
    ];
    protected string $response;

    public function __construct(string $host, string $user, #[SensitiveParameter] string $pass, int $ssl, string $eol, protected Container $c)
    {
        if ('' !== $host) {
            $hp = \explode(':', $host, 2);

            if (
                empty($hp[1])
                || ! \is_int($hp[1] + 0)
                || $hp[1] < 1
                || $hp[1] > 65535
            ) {
                $hp[1] = 25;
            }

            $this->smtp = [
                'host'    => ($ssl ? 'ssl://' : '') . $hp[0],
                'port'    => (int) $hp[1],
                'user'    => $user,
                'pass'    => $pass,
                'timeout' => 15,
            ];
            $this->EOL = "\r\n";
        } else {
            $this->EOL = \in_array($eol, ["\r\n", "\n", "\r"], true) ? $eol : \PHP_EOL;
        }
    }

    /**
     * Валидация email
     */
    public function valid(mixed $email, bool $strict = false, bool $idna = false): string|false
    {
        if (
            ! \is_string($email)
            || \mb_strlen($email, 'UTF-8') > $this->c->MAX_EMAIL_LENGTH
            || ! \preg_match('%^([^\x00-\x1F]+)@([^\x00-\x1F\s@]++)$%Du', $email, $matches)
        ) {
            return false;
        }

        $local                 = $matches[1];
        $domain = $domainASCII = $matches[2];

        if (
            '[' === $domain[0]
            && ']' === $domain[-1]
        ) {
            if (1 === \strpos($domain, 'IPv6:')) {
                $ip = \substr($domain, 6, -1);
            } else {
                $ip = \substr($domain, 1, -1);
            }

            if (false === \filter_var($ip, \FILTER_VALIDATE_IP)) {
                return false;
            }
        } else {
            $ip = null;

            if (\preg_match('%[\x80-\xFF]%', $domain)) {
                $domainASCII = \idn_to_ascii($domain, 0, \INTL_IDNA_VARIANT_UTS46);
            }
        }

        if (false === \filter_var("{$local}@{$domainASCII}", \FILTER_VALIDATE_EMAIL, \FILTER_FLAG_EMAIL_UNICODE)) {
            return false;
        }

        if (true === $strict) {
            $level = $this->c->ErrorHandler->logOnly(\E_WARNING);

            if (\is_string($ip)) {
                $mx = \checkdnsrr($ip, 'MX'); // ipv6 в пролёте :(
            } else {
                $mx = \dns_get_record($domainASCII, \DNS_MX);
            }

            $this->c->ErrorHandler->logOnly($level);

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
        $this->message = '';

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
    public function addTo(string|array $email, string $name = null): Mail
    {
        if (! \is_array($email)) {
            $email = \preg_split('%[,\n\r]%', $email, -1, \PREG_SPLIT_NO_EMPTY);
        }

        foreach ($email as $cur) {
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
    public function setTo(array|string $email, string $name = null): Mail
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

        if ('' === \trim($this->message)) {
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
                $to     = $this->formatAddress($to, $name);
                $result = \mail($to, $subject, $this->message, $headers);

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
                $result         = \mail($to, $subject, $this->message, $headers);

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
            $level   = $this->c->ErrorHandler->logOnly(\E_WARNING);
            $connect = \fsockopen($this->smtp['host'], $this->smtp['port'], $errno, $errstr, $this->smtp['timeout']);

            $this->c->ErrorHandler->logOnly($level);

            if (false === $connect) {
                throw new SmtpException("Couldn't connect to smtp host \"{$this->smtp['host']}:{$this->smtp['port']}\" ({$errno}) ({$errstr}).");
            }

            \stream_set_timeout($connect, $this->smtp['timeout']);

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
     * Возвращает массив расширений из ответа сервера
     */
    protected function smtpExtn(string $response): array
    {
        $result = [];

        if (\preg_match_all('%250[- ]([0-9A-Z_-]+)(?:[ =]([^\n\r]+))?%', $response, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $cur) {
                $result[$cur[1]] = $cur[2] ?? '';
            }
        }

        return $result;
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
                    '' !== $this->smtp['user']
                    && '' !== $this->smtp['pass']
                ) {
                    $code = $this->smtpData('EHLO ' . $this->hostname(), ['250', '500', '501', '502', '550']);

                    if ('250' === $code) {
                        $extn = $this->smtpExtn($this->response);

                        if (
                            isset($extn['STARTTLS'])
                            && \function_exists('\\stream_socket_enable_crypto')
                        ) {
                            $this->smtpData('STARTTLS', ['220']);

                            $level  = $this->c->ErrorHandler->logOnly(\E_WARNING);
                            $crypto = \stream_socket_enable_crypto(
                                $this->connect,
                                true,
                                \STREAM_CRYPTO_METHOD_TLS_CLIENT
                            );

                            $this->c->ErrorHandler->logOnly($level);

                            if (true !== $crypto) {
                                throw new SmtpException('Failed to enable encryption on the stream using TLS.');
                            }

                            $this->smtpData('EHLO ' . $this->hostname(), ['250']);

                            $extn = $this->smtpExtn($this->response);
                        }

                        if (isset($extn['AUTH'])) {
                            $methods = \array_flip(\explode(' ', $extn['AUTH']));

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
                            } else {
                                throw new SmtpException("Unknown AUTH methods: \"{$extn['AUTH']}\".");
                            }
                        }

                        if (
                            isset($extn['STARTTLS'])
                            && ! isset($extn['AUTH'])
                        ) {
                            if (\function_exists('\\stream_socket_enable_crypto')) {
                                throw new SmtpException("The server \"{$this->smtp['host']}:{$this->smtp['port']}\" requires STARTTLS.");
                            } else {
                                throw new SmtpException("The server \"{$this->smtp['host']}:{$this->smtp['port']}\" requires STARTTLS, but \stream_socket_enable_crypto() was not found.");
                            }
                        }

                        $this->auth = 1;

                        return;
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
    protected function smtpData(?string $data, ?array $responseOptions): string
    {
        $level = $this->c->ErrorHandler->logOnly(\E_WARNING);

        if (
            \is_resource($this->connect)
            && null !== $data
            && false === \fwrite($this->connect, $data . $this->EOL)
        ) {
            $this->c->ErrorHandler->logOnly($level);

            throw new SmtpException('Couldn\'t send data to mail server.');
        }

        $this->response = '';
        $responseCode   = '';

        while (
            \is_resource($this->connect)
            && ! \feof($this->connect)
        ) {
            $get = \fgets($this->connect, 512);

            if (false === $get) {
                $this->c->ErrorHandler->logOnly($level);

                throw new SmtpException('Couldn\'t get mail server response codes.');
            }

            $this->response .= $get;

            if (
                isset($get[3])
                && ' ' === $get[3]
            ) {
                $responseCode = \substr($get, 0, 3);
                break;
            }
        }

        $this->c->ErrorHandler->logOnly($level);

        if (
            null !== $responseOptions
            && ! \in_array($responseCode, $responseOptions, true)
        ) {
            throw new SmtpException("Unable to send email. Response of mail server: \"{$this->response}\"");
        }

        return $responseCode;
    }

    /**
     * Возвращает имя сервера или его ip
     */
    protected function hostname(): string
    {
        $name = $_SERVER['SERVER_NAME'] ?? null;
        $ip   = $_SERVER['SERVER_ADDR'] ?? '';

        if (
            ! $name
            || '' === ($name = \preg_replace('%(?:[\x00-\x1F\x7F]|\xC2[\x80-\x9F])%', '', \trim($name)))
        ) {
            $name = '[127.0.0.1]';

            if (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                $name = "[{$ip}]";
            } elseif (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                $name = "[IPv6:{$ip}]";
            }
        }

        return $name;
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

            \fclose($this->connect);
        }
    }
}
