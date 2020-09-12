<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use ForkBB\Core\Exceptions\ForkException;

class Load extends Action
{
    /**
     * Создает текст запрос
     */
    protected function getSql(string $where): string
    {
        $query = 'SELECT u.*, g.*
            FROM ::users AS u
            LEFT JOIN ::groups AS g ON u.group_id=g.g_id
            WHERE ' . $where;

        return $query;
    }

    /**
     * Загружает пользователя из БД
     */
    public function load(int $id): ?User
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive user id');
        }

        $vars  = [':id' => $id];
        $query = $this->getSql('u.id=?i:id');

        $data = $this->c->DB->query($query, $vars)->fetch();

        return empty($data['id']) ? null : $this->manager->create($data);
    }

    /**
     * Загружает список пользователей из БД
     */
    public function loadByIds(array $ids): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive user id');
            }
        }

        $vars  = [':ids' => $ids];
        $query = $this->getSql('u.id IN (?ai:ids)');

        $data = $this->c->DB->query($query, $vars)->fetchAll();

        $result = [];
        foreach ($data as $row) {
            $result[] = $this->manager->create($row);
        }

        return $result;
    }

    /**
     * Возвращает результат
     */
    protected function returnUser(string $query, array $vars): ?User
    {
        $data = $this->c->DB->query($query, $vars)->fetchAll();

        if (empty($data)) {
            return null;
        } elseif (\count($data) > 1) { // ???? невыполнимое условие?!
            throw new ForkException('Multiple users found');
        } else {
            return $this->manager->create($data[0]);
        }
    }

    /**
     * Получает пользователя по имени
     */
    public function loadByName(string $name, bool $caseInsencytive = false): ?User
    {
        $where = $caseInsencytive ? 'LOWER(u.username)=LOWER(?s:name)' : 'u.username=?s:name';
        $vars  = [':name' => $name];
        $query = $this->getSql($where);

        return $this->returnUser($query, $vars);
    }

    /**
     * Получает пользователя по email
     */
    public function loadByEmail(string $email): ?User
    {
        $vars  = [':email' => $this->c->NormEmail->normalize($email)];
        $query = $this->getSql('u.email_normal=?s:email');

        return $this->returnUser($query, $vars);
    }
}
