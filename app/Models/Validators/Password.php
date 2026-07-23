<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Validators;

use ForkBB\Core\RulesValidator;
use ForkBB\Core\Validator;

class Password extends RulesValidator
{
    protected array $numbers;
    protected array $latinUpper;
    protected array $latinLower;
    protected array $otherOne;
    protected array $ruUpper;
    protected array $ruLower;
    protected array $otherSymb;

    protected int $uniqueCount;
    protected int $numbersCount;
    protected int $latinUpperCount;
    protected int $latinLowerCount;
    protected int $otherOneCount;
    protected int $ruUpperCount;
    protected int $ruLowerCount;
    protected int $otherSymbCount;

    protected bool $subsequenceFlag;

    protected array $sequences = [
        '1234567890',
        '0987654321',
        'qwertyuiop',
        'poiuytrewq',
        'asdfghjkl',
        'lkjhgfdsa',
        'zxcvbnm',
        'mnbvcxz',
        'йцукенгшщзхъ',
        'ъхзщшгнекуцй',
        'фывапролджэ',
        'эждлорпавыф',
        'ячсмитьбю',
        'юбьтимсчя',
        '1q2w3e4r5t6y7u8i9o0p',
        '0p9o8i7u6y5t4r3e2w1q',
        'q1w2e3r4t5y6u7i8o9p0',
        'p0o9i8u7y6t5r4e3w2q1',
        '1qaz2wsx3edc4rfv5tgb6yhn7ujm',
        'mju7nhy6bgt5vfr4cde3xsw2zaq1',
        '0okm9ijn8uhb7ygv6tfc5rdx4esz',
        'zse4xdr5cft6vgy7bhu8nji9mko0',
        '1й2ц3у4к5е6н7г8ш9щ0з',
        '0з9щ8ш7г6н5е4к3у2ц1й',
        'й1ц2у3к4е5н6г7ш8щ9з0',
        'з0щ9ш8г7н6е5к4у3ц2й1',
        '1йфя2цыч3увс4кам5епи6нрт7гоь8шлб9щдю',
        'юдщ9блш8ьог7трн6ипе5мак4сву3чыц2яфй1',
        '0щль9шот8гри7нпм6еас5квч4уыя',
        'яыу4чвк5сае6мпн7ирг8тош9ьлщ0',
    ];

    /**
     * Проверяет парольную фразу
     */
    public function password(Validator $v, string $pass): string
    {
        $len = \mb_strlen($pass, 'UTF-8');

        if ($len < $this->c->PASSPHRASE['min']) {
            $v->addError('Short passphrase');

        } else {
            $level = $this->analysis($pass);

            if ($this->uniqueCount < 4) {
                $v->addError('Many repeated chars passphrase');

            } elseif ($level < 10) {
                $v->addError('Critically vulnerable passphrase');

            } elseif ($level < 40) {
                $v->addError('Very vulnerable passphrase');

            } elseif ($level <= 50) {
                $v->addError('Vulnerable passphrase');

            } else {
                $this->c->passphraseEntropy = $level;
            }
        }

        return $pass;
    }

    protected function analysis(string $pass): int|float
    {
        $this->numbers    = [];
        $this->latinUpper = [];
        $this->latinLower = [];
        $this->otherOne   = [];
        $this->ruUpper    = [];
        $this->ruLower    = [];
        $this->otherSymb  = [];

        $this->subsequenceFlag = false;

        $symbols = \mb_str_split($pass, 1, 'UTF-8');

        if (empty($symbols)) {
            $this->uniqueCount = 0;

            return 0;

        } else {
            $this->uniqueCount = \count(\array_flip($symbols));
        }

        foreach ($symbols as $pos => $symbol) {
            if (\preg_match('%[0-9]%', $symbol)) {
                $this->numbers[$pos] = $symbol;

            } elseif (\preg_match('%[A-Z]%', $symbol)) {
                $this->latinUpper[$pos] = $symbol;

            } elseif (\preg_match('%[a-z]%', $symbol)) {
                $this->latinLower[$pos] = $symbol;

            } elseif (! isset($symbol[1])) {
                $this->otherOne[$pos] = $symbol;

            } elseif (\preg_match('%[А-ЯЁ]%u', $symbol)) {
                $this->ruUpper[$pos] = $symbol;

            } elseif (\preg_match('%[а-яё]%u', $symbol)) {
                $this->ruLower[$pos] = $symbol;

            } else {
                $this->otherSymb[$pos] = $symbol;
            }
        }

        $this->numbersCount    = \count($this->numbers);
        $this->latinUpperCount = \count($this->latinUpper);
        $this->latinLowerCount = \count($this->latinLower);
        $this->otherOneCount   = \count($this->otherOne);
        $this->ruUpperCount    = \count($this->ruUpper);
        $this->ruLowerCount    = \count($this->ruLower);
        $this->otherSymbCount  = \count($this->otherSymb);

        $charsetSize = $this->otherSymbCount;

        if ($this->numbersCount > 0) {
            $charsetSize = \preg_match('%^[^0-9]*[0-9]+$%', $pass) ? 1 : 10;
        }

        if ($this->latinUpperCount > 1) {
            $charsetSize += 26;

        } elseif (1 === $this->latinUpperCount) {
            $charsetSize += isset($this->latinUpper[0]) ? 1 : 26;
        }

        if ($this->latinLowerCount > 0) {
            $charsetSize += 26;
        }

        if ($this->otherOneCount > 1) {
            $charsetSize += 32;

        } elseif (1 === $this->otherOneCount) {
            $charsetSize += isset($this->otherOne[$pos]) ? 1 : 32;

        }

        if ($this->ruUpperCount > 1) {
            $charsetSize += 33;

        } elseif (1 === $this->ruUpperCount) {
            $charsetSize += isset($this->ruUpper[0]) ? 1 : 33;
        }

        if ($this->ruLowerCount > 0) {
            $charsetSize += 33;
        }

        $passLower = \mb_strtolower($pass, 'UTF-8');

        foreach ($this->sequences as $subsequence) {
            $len = \mb_strlen($subsequence, 'UTF-8');
            $end = $len - 2;

            for ($s = 0; $s < $end; $s++) {
                for ($l = $len - $s; $l > 2; $l--) {
                    $passLower = \str_replace(\mb_substr($subsequence, $s, $l, 'UTF-8'), '', $passLower, $n);

                    if ($n > 0) {
                        $this->subsequenceFlag = true;

                        break;
                    }
                }
            }
        }

        $passLower = \preg_replace('%(.)\1{3,}%u', '$1', $passLower);

        return \mb_strlen($passLower, 'UTF-8') * \log($charsetSize, 2);
    }
}
