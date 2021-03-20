<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Core\Container;
use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * @var array
     */
    protected $repository;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'area' => ['numPages', 'pagination'],
        ];
        $this->repository = [
            Cnst::PTOPIC => [],
            Cnst::PPOST  => [],
        ];
    }

    protected function checkType(int $type): void
    {
        switch ($type) {
            case Cnst::PTOPIC:
            case Cnst::PPOST:
                break;
            default:
                throw new InvalidArgumentException("Wrong type: {$type}");
        }
    }

    public function get(int $type, int $key): ?ParentModel
    {
        $this->checkType($type);

        return $this->repository[$type][$key] ?? null;
    }

    public function set(int $type, int $key, /* mixed */ $value): self
    {
        $this->checkType($type);

        $this->repository[$type][$key] = $value;

        return $this;
    }

    public function isset(int $type, int $key): bool
    {
        $this->checkType($type);

        return \array_key_exists($key, $this->repository[$type]);
    }

    public function create(int $type, array $attrs = []): ParentModel
    {
        switch ($type) {
            case Cnst::PTOPIC:
                return $this->c->PTopicModel->setAttrs($attrs);
            case Cnst::PPOST:
                return $this->c->PPostModel->setAttrs($attrs);
            case Cnst::PRND:
                return $this->c->PRndModel->setAttrs($attrs);
            default:
                throw new InvalidArgumentException("Wrong type: {$type}");
        }
    }

    public function accessTopic(int $id): bool
    {
        return isset($this->idsCurrent[$id]) || isset($this->numArchive[$id]);
    }

    public function load(int $type, int $id): ?ParentModel
    {
        $this->checkType($type);

        if ($this->isset($type, $id)) {
            return $this->get($type, $id);
        }

        switch ($type) {
            case Cnst::PTOPIC:
                if ($this->accessTopic($id)) {
                    $model = $this->Load->load($type, $id);
                } else {
                    $model = null;
                }

                break;
            case Cnst::PPOST:
                $model = $this->Load->load($type, $id);

                if (
                    $model instanceof PPost
                    && ! $this->accessTopic($model->topic_id)
                ) {
                    $model = null;
                }

                break;
        }

        $this->set($type, $id, $model);

        return $model;
    }

    public function loadByIds(int $type, array $ids): array
    {
        $this->checkType($type);

        $result = [];
        $data   = [];

        foreach ($ids as $id) {
            if ($this->isset($type, $id)) {
                $result[$id] = $this->get($type, $id);
            } else {
                switch ($type) {
                    case Cnst::PTOPIC:
                        if (! $this->accessTopic($id)) {
                            break;
                        }
                    default:
                        $data[] = $id;
                }

                $result[$id] = null;

                $this->set($type, $id, null);
            }
        }

        if (empty($data)) {
            return $result;
        }

        foreach ($this->Load->loadByIds($type, $data) as $model) {
            if ($model instanceof PPost) {
                if (! $this->accessTopic($model->topic_id)) {
                    continue;
                }
            } elseif (! $model instanceof PTopic) {
                continue;
            }

            $result[$model->id] = $model;

            $this->set($type, $model->id, $model);
        }

        return $result;
    }

    public function update(int $type, ParentModel $model): ParentModel
    {
        $this->checkType($type);

        return $this->Save->update($type, $model);
    }

    public function insert(int $type, ParentModel $model): int
    {
        $this->checkType($type);

        $id = $this->Save->insert($type, $model);

        $this->set($type, $id, $model);

        return $id;
    }

    /**
     * Инициализирует массивы индексов приватных тем текущего пользователя
     * Инициализирует число приватных тем без фильтра
     * Может использовать фильтр по второму пользователю: id или username
     */
    public function init(/* null|int|string */ $second = null): self
    {
        list(
            $this->idsNew,
            $this->idsCurrent,
            $this->idsArchive,
            $this->totalCurrent,
            $this->totalArchive
        ) = $this->infoForUser($this->c->user, $second);

        $this->second     = $second;
        $this->numNew     = \count($this->idsNew);
        $this->numCurrent = \count($this->idsCurrent);
        $this->numArchive = \count($this->idsArchive);

        return $this;
    }

    /**
     * Возвращает данные по приватным темам (индексы) любого пользователя
     */
    public function infoForUser(User $user, /* null|int|string */ $second = null): array
    {
        if (
            $user->isGuest
            || $user->isUnverified
        ) {
            throw new RuntimeException('Unexpected Guest or Unverified');
        }

        $vars = [
            ':id'  => $user->id,
            ':var' => $second,
        ];
        $query = 'SELECT pr.topic_id, pt.last_post, pr.pt_visit, pr.pt_status %repl1%
            FROM ::pm_rnd AS pr
            INNER JOIN ::pm_topics AS pt ON pt.id=pr.topic_id %repl2%
            WHERE pr.user_id=?i:id AND pr.pt_status>1
            ORDER BY pt.last_post DESC';

        if (null === $second) {
            $repl1 = '';
            $repl2 = '';
        } elseif (
            \is_int($second)
            && $second > 0
        ) {
            $repl1 = ', prs.user_id AS pmsecond';
            $repl2 = 'LEFT JOIN ::pm_rnd AS prs ON prs.user_id=?i:var AND prs.topic_id=pr.topic_id';
        } elseif (
            \is_string($second)
            && '' !== $second
        ) {
            $repl1 = ', prs.username AS pmsecond';
            $repl2 = 'LEFT JOIN ::pm_rnd AS prs ON prs.topic_id=pr.topic_id AND prs.username=?s:var'; // на имени нет индекса
        } else {
            throw new InvalidArgumentException('Wrong second user');
        }

        $query = \str_replace(['%repl1%', '%repl2%'], [$repl1, $repl2], $query);

        // deleted      // pt_status = 0
        // unsent       // pt_status = 1
        $idsNew   = []; // pt_status = 2 and last_post > last_visit
        $idsCur   = []; // pt_status = 2 or last_post > last_visit
        $idsArc   = []; // pt_status = 3
        $totalCur = 0;
        $totalArc = 0;
        $stmt     = $this->c->DB->query($query, $vars);

        while ($row = $stmt->fetch()) {
            switch ($row['pt_status']) {
                case Cnst::PT_ARCHIVE:
                    ++$totalArc;

                    if (
                        null === $second
                        || $row['pmsecond'] == $second
                    ) {
                        $idsArc[$row['topic_id']] = $row['last_post'];
                    }

                    break;
                case Cnst::PT_NORMAL:
                    ++$totalCur;

                    if (
                        null === $second
                        || $row['pmsecond'] == $second
                    ) {
                        if ($row['last_post'] > $row['pt_visit']) {
                            $idsNew[$row['topic_id']] = $row['last_post'];
                        }

                        $idsCur[$row['topic_id']] = $row['last_post'];
                    }

                    break;
            }
        }

        return [$idsNew, $idsCur, $idsArc, $totalCur, $totalArc];
    }

    /**
     * Возвращает список приватных тем в зависимости от активной папки
     * Номер темы в индексе, а не в значении
     */
    protected function idsList(): array
    {
        switch ($this->area) {
            case Cnst::ACTION_NEW:
                $list = $this->idsNew;
                break;
            case Cnst::ACTION_ARCHIVE:
                $list = $this->idsArchive;
                break;
            default:
                $list = $this->idsCurrent;
        }

        if (\is_array($list)) {
            return $list;
        }

        throw new RuntimeException('Init() method was not executed');
    }

    /**
     * $this->area = ...
     */
    protected function setarea(string $area): self
    {
        switch ($area) {
            case Cnst::ACTION_NEW:
            case Cnst::ACTION_CURRENT:
            case Cnst::ACTION_ARCHIVE:
                break;
            default:
                $area = Cnst::ACTION_CURRENT;
        }

        $this->setAttr('area', $area);

        return $this;
    }

    /**
     * ... = $this->numPages;
     */
    protected function getnumPages(): int
    {
        return (int) \ceil((\count($this->idsList()) ?: 1) / $this->c->user->disp_topics);
    }

    /**
     * Статус наличия установленной страницы
     */
    public function hasPage(): bool
    {
        return \is_int($this->page) && $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * ... = $this->pagination;
     */
    protected function getpagination(): array
    {
        if (
            $this->page < 1
            && 1 === $this->numPages
        ) {
            return [];
        } else {
            return $this->c->Func->paginate(
                $this->numPages,
                $this->page,
                'PMAction',
                [
                    'second' => $this->second,
                    'action' => $this->area,
                ]
            );
        }
    }

    /**
     * Возвращает массив приватных тем с установленной страницы
     */
    public function pmListCurPage(): array
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $ids = \array_slice(
            $this->idsList(),
            ($this->page - 1) * $this->c->user->disp_topics,
            $this->c->user->disp_topics
        );

        return $this->loadByIds(Cnst::PTOPIC, \array_keys($ids));
    }
}
