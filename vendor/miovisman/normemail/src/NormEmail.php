<?php

/**
 * @copyright  Copyright (c) 2019 Visman. All rights reserved.
 * @author     Visman <mio.visman@yandex.ru>
 * @link       https://github.com/MioVisman/NormEmail
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace MioVisman\NormEmail;

class NormEmail
{
    const NO_RULE   = 0;
    const DOT       = 1;  // google
    const DUM       = 2;  // protonmail
    const DTOH      = 4;  // yandex
    const HYPHEN    = 8;  // yahoo
    const DONT_PLUS = 16; // someone?
    const SENSITIVE = 32;

    protected $rules = [
        // Google https://support.google.com/mail/answer/7436150
        'gmail.com'      => self::DOT,
        'googlemail.com' => 'gmail.com',
        // Protonmail https://protonmail.com/support/knowledge-base/addresses-and-aliases/#comment-7913
        // https://protonmail.com/support/knowledge-base/pm-me-addresses/
        'protonmail.com' => self::DUM,
        'protonmail.ch'  => 'protonmail.com',
        'pm.me'          => 'protonmail.com',
        // Yahoo https://help.yahoo.com/kb/SLN28338.html
        // https://help.yahoo.com/kb/SLN2153.html
        'yahoo.com'      => self::HYPHEN,
        'yahoo.ae'       => self::HYPHEN,
        'yahoo.com.ar'   => self::HYPHEN,
        'yahoo.at'       => self::HYPHEN,
        'yahoo.com.au'   => self::HYPHEN,
        'yahoo.be'       => self::HYPHEN,
        'yahoo.com.br'   => self::HYPHEN,
        'yahoo.ca'       => self::HYPHEN,
        'yahoo.ch'       => self::HYPHEN,
        'yahoo.com.co'   => self::HYPHEN,
        'yahoo.cz'       => self::HYPHEN,
        'yahoo.de'       => self::HYPHEN,
        'yahoo.dk'       => self::HYPHEN,
        'yahoo.es'       => self::HYPHEN,
        'yahoo.fi'       => self::HYPHEN,
        'yahoo.fr'       => self::HYPHEN,
        'yahoo.gr'       => self::HYPHEN,
        'yahoo.com.hk'   => self::HYPHEN,
        'yahoo.com.hr'   => self::HYPHEN,
        'yahoo.hu'       => self::HYPHEN,
        'yahoo.co.id'    => self::HYPHEN,
        'yahoo.ie'       => self::HYPHEN,
        'yahoo.co.il'    => self::HYPHEN,
        'yahoo.in'       => self::HYPHEN,
        'yahoo.co.in'    => self::HYPHEN,
        'yahoo.it'       => self::HYPHEN,
        'yahoo.co.jp'    => self::HYPHEN,
        'yahoo.com.my'   => self::HYPHEN,
        'yahoo.com.mx'   => self::HYPHEN,
        'yahoo.nl'       => self::HYPHEN,
        'yahoo.no'       => self::HYPHEN,
        'yahoo.co.nz'    => self::HYPHEN,
        'yahoo.com.ph'   => self::HYPHEN,
        'yahoo.pl'       => self::HYPHEN,
        'yahoo.pt'       => self::HYPHEN,
        'yahoo.ro'       => self::HYPHEN,
        'yahoo.ru'       => self::HYPHEN,
        'yahoo.se'       => self::HYPHEN,
        'yahoo.com.sg'   => self::HYPHEN,
        'yahoo.co.th'    => self::HYPHEN,
        'yahoo.com.tr'   => self::HYPHEN,
        'yahoo.com.tw'   => self::HYPHEN,
        'yahoo.co.uk'    => self::HYPHEN,
        'yahoo.com.vn'   => self::HYPHEN,
        'yahoo.co.za'    => self::HYPHEN,
        // Yandex https://habr.com/ru/company/yandex/blog/56866/
        'yandex.ru'      => self::DTOH,
        'ya.ru'          => 'yandex.ru',
#       'yandex.asia'    => 'yandex.ru', // no MX
        'yandex.az'      => 'yandex.ru',
        'yandex.by'      => 'yandex.ru',
        'yandex.com'     => 'yandex.ru',
#       'yandex.de'      => 'yandex.ru', // no MX
#       'yandex.dk'      => 'yandex.ru', // empty
#       'yandex.do'      => 'yandex.ru', // empty
        'yandex.ee'      => 'yandex.ru',
#       'yandex.es'      => 'yandex.ru', // empty
#       'yandex.eu'      => 'yandex.ru', // empty
#       'yandex.ie'      => 'yandex.ru', // empty
#       'yandex.in'      => 'yandex.ru', // empty
#       'yandex.it'      => 'yandex.ru', // no MX
        'yandex.lt'      => 'yandex.ru',
#       'yandex.lu'      => 'yandex.ru', // empty
        'yandex.lv'      => 'yandex.ru',
        'yandex.md'      => 'yandex.ru',
#       'yandex.mobi'    => 'yandex.ru', // no MX
#       'yandex.mx'      => 'yandex.ru', // empty
#       'yandex.net'     => 'yandex.ru', // is not synonymous with ru? need self::DTOH?
#       'yandex.no'      => 'yandex.ru', // empty
#       'yandex.nu'      => 'yandex.ru', // no MX
#       'yandex.org'     => 'yandex.ru', // no MX
#       'yandex.pl'      => 'yandex.ru', // empty
#       'yandex.pt'      => 'yandex.ru', // no MX
#       'yandex.qa'      => 'yandex.ru', // empty
#       'yandex.ro'      => 'yandex.ru', // empty
#       'yandex.rs'      => 'yandex.ru', // empty
#       'yandex.net.ru'  => 'yandex.ru', // no MX
#       'yandex.com.ru'  => 'yandex.ru', // no MX
#       'yandex.sk'      => 'yandex.ru', // empty
#       'yandex.so'      => 'yandex.ru', // empty
        'yandex.tj'      => 'yandex.ru',
        'yandex.tm'      => 'yandex.ru',
        'yandex.ua'      => 'yandex.ru',
#       'yandex.com.ua'  => 'yandex.ru', // is not synonymous with ru? need self::DTOH?
        'yandex.uz'      => 'yandex.ru',
        'xn--d1acpjx3f.xn--p1ai' => 'yandex.ru', // яндекс.рф
        // Fastmail https://www.fastmail.com/help/receive/addressing.html
        // https://www.fastmail.com/about/ourdomains/
        // TODO: Make subdomain translation to the local part of the address
    ];

    public function normalize($email)
    {
        if (false === ($pos = \strrpos($email, '@'))) {
            $domain = $email;
            $local = '';
        } else {
            $domain = \substr($email, $pos + 1);
            $local = \substr($email, 0, $pos);
        }

        $domain = \mb_strtolower($domain, 'UTF-8');

        // TODO: Process the dot at the beginning of the domain for the ban of the domains array (ban by the domain name without the local part)

        if ('[' !== $domain[0] && \preg_match('%[\x80-\xFF]%', $domain)) {
            $parts = \explode('.', $domain);
            foreach ($parts as &$part) {
                $ascii = \idn_to_ascii($part, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
                if (false !== $ascii) {
                    $part = $ascii;
                }
            }
            unset($part);
            $domain = \implode('.', $parts);
        }

        do {
            $rule = self::NO_RULE;
            if (isset($this->rules[$domain])) {
                $rule = $this->rules[$domain];
                if (\is_string($rule)) {
                    $domain = $rule;
                    continue;
                }
            }
            break;
        } while (true);

        if (isset($local[0]) && '"' !== $local[0]) {
            if ($rule & self::HYPHEN) {
                $symbol = '-';
            } elseif ($rule & self::DONT_PLUS) {
                $symbol = false;
            } else {
                $symbol = '+';
            }

            if (false !== $symbol) {
                $pos = \strpos($local, $symbol);
                if (false !== $pos) {
                    $local = \substr($local, 0, $pos);
                }
            }

            if ($rule & self::DOT) {
                $local = \str_replace('.', '', $local);
            }

            if ($rule & self::DUM) {
                $local = \str_replace(['.', '_', '-'], '', $local);
            }

            if ($rule & self::DTOH) {
                $local = \str_replace('.', '-', $local);
            }

            if (! ($rule & self::SENSITIVE)) {
                $local = \mb_strtolower($local, 'UTF-8');
            }
        }

        if ('' == $local) {
            return $domain; // '@myhost.com' --> 'myhost.com'
        } else {
            return $local . '@' . $domain;
        }
    }
}
