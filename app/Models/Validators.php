<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\User\Model as User;

class Validators
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Дополнительная проверка username
     *
     * @param Validator $v
     * @param string $username
     * @param string $z
     * @param mixed $originalUser
     *
     * @return string
     */
    public function vCheckUsername(Validator $v, $username, $z, $originalUser)
    {
        if ($originalUser instanceof User) {
            $id   = $originalUser->id;
            $old  = $originalUser->username;
        } else {
            $id   = null;
            $old  = null;
        }

        if ($old !== $username) {

            $user = $this->c->users->create(['id' => $id, 'username' => $username]);

            // username = Гость
            if (\preg_match('%^(guest|' . \preg_quote(\ForkBB\__('Guest'), '%') . ')$%iu', $username)) { // ???? а зачем?
                $v->addError('Username guest');
            // цензура
            } elseif ($this->c->censorship->censor($username) !== $username) {
                $v->addError('Username censor');
            // username забанен
            } elseif ($this->c->bans->isBanned($user) > 0) {
                $v->addError('Banned username');
            // есть пользователь с похожим именем
            } elseif (empty($v->getErrors()) && ! $this->c->users->isUniqueName($user)) {
                $v->addError('Username not unique');
            }
        }

        return $username;
    }

    /**
     * Дополнительная проверка email
     * WARNING!!!
     * Если передан гость 4-ым параметром, то проверка уникальности email не проводится
     *
     * @param Validator $v
     * @param string $email
     * @param string $attrs
     * @param mixed $originalUser
     *
     * @return string
     */
    public function vCheckEmail(Validator $v, $email, $attrs, $originalUser)
    {
        // email забанен
        if ($this->c->bans->isBanned($this->c->users->create(['email' => $email])) > 0) {
            $v->addError('Banned email');
        // остальные проверки
        } elseif (empty($v->getErrors())) {
            $attrs = \array_flip(\explode(',', $attrs));
            $ok    = true;
            $user  = true;

            // наличие
            if (isset($attrs['exists'])) {
                $user = $this->c->users->load($email, 'email');

                if (! $user instanceof User) {
                    $v->addError('Invalid email');
                    $ok = false;
                }
            }

            // уникальность
            if ($ok && isset($attrs['unique']) && (! $originalUser instanceof User || ! $originalUser->isGuest)) {
                if (true === $user) {
                    $user = $this->c->users->load($email, 'email');
                }

                $id = $originalUser instanceof User ? $originalUser->id : true;

                if (($user instanceof User && $id !== $user->id) || (! $user instanceof User && 0 !== $user)) {
                    $v->addError('Dupe email');
                    $ok = false;
                }
            }

            // флуд
            if ($ok && isset($attrs['flood'])) {
                $min = 3600;

                if ($originalUser instanceof User && ! $originalUser->isGuest) {
                    $flood = \time() - $originalUser->last_email_sent;
                } elseif ($user instanceof User) {
                    $flood = \time() - $user->last_email_sent;
                } else {
                    $flood = $min;
                }
                if ($flood < $min) {
                    $v->addError(\ForkBB\__('Email flood', (int) (($min - $flood) / 60)), 'e');
                    $ok = false;
                }
            }

            // возврат данных пользователя через 4-ый параметр
            if ($ok && $originalUser instanceof User && $originalUser->id < 1 && $user instanceof User) {
                $originalUser->setAttrs($user->getAttrs());
            }
        }
        return $email;
    }

    /**
     * Дополнительная проверка на отсутствие url в значении
     *
     * @param Validator $v
     * @param mixed $value
     * @param string $flag
     *
     * @return mixed
     */
    public function vNoURL(Validator $v, $value, $flag)
    {
        $flag = empty($flag) || '1' != $this->c->user->g_post_links;

        if ($flag && \preg_match('%https?://|www\.%i', $value)) {
            $v->addError('The :alias contains a link');
        }
        return $value;
    }

}
