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
use ForkBB\Models\DataModel;
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

    protected function checkType(int $type, DataModel $model = null): void
    {
        switch ($type) {
            case Cnst::PTOPIC:
                if (
                    null === $model
                    || $model instanceof PTopic
                ) {
                    return;
                }

                break;
            case Cnst::PPOST:
                if (
                    null === $model
                    || $model instanceof PPost
                ) {
                    return;
                }

                break;
        }

        throw new InvalidArgumentException("Wrong type: {$type}");
    }

    public function get(int $type, int $key): ?DataModel
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

    public function create(int $type, array $attrs = []): DataModel
    {
        switch ($type) {
            case Cnst::PTOPIC:
                return $this->c->PTopicModel->setAttrs($attrs);
            case Cnst::PPOST:
                return $this->c->PPostModel->setAttrs($attrs);
            default:
                throw new InvalidArgumentException("Wrong type: {$type}");
        }
    }

    public function accessTopic(int $id): bool
    {
        return isset($this->idsCurrent[$id]) || isset($this->numArchive[$id]);
    }

    public function load(int $type, int $id): ?DataModel
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

    public function update(int $type, DataModel $model): DataModel
    {
        $this->checkType($type, $model);

        return $this->Save->update($model);
    }

    public function insert(int $type, DataModel $model): int
    {
        $this->checkType($type, $model);

        $id = $this->Save->insert($model);

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
            $this->totalNew,
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
        // deleted      // pt_status = PT_DELETED
        // unsent       // pt_status = PT_NOTSENT
        $idsNew   = []; // pt_status = PT_NORMAL and last_post > ..._visit
        $idsCur   = []; // pt_status = PT_NORMAL or last_post > ..._visit
        $idsArc   = []; // pt_status = PT_ARCHIVE
        $totalNew = 0;
        $totalCur = 0;
        $totalArc = 0;

        if (
            $user->isGuest
            || $user->isUnverified
        ) {
            return [$idsNew, $idsCur, $idsArc, $totalNew, $totalCur, $totalArc];
        }

        $vars = [
            ':id'   => $user->id,
            ':norm' => Cnst::PT_NORMAL,
            ':arch' => Cnst::PT_ARCHIVE,
        ];
        $query = 'SELECT pt.poster, pt.poster_id, pt.poster_status, pt.poster_visit,
                         pt.target, pt.target_id, pt.target_status, pt.target_visit,
                         pt.id, pt.last_post
                   FROM ::pm_topics AS pt
                  WHERE (pt.poster_id=?i:id AND pt.poster_status=?i:norm)
                     OR (pt.poster_id=?i:id AND pt.poster_status=?i:arch)
                     OR (pt.target_id=?i:id AND pt.poster_status=?i:norm)
                     OR (pt.target_id=?i:id AND pt.poster_status=?i:arch)
               ORDER BY pt.last_post DESC';

        $stmt = $this->c->DB->query($query, $vars);

        while ($row = $stmt->fetch()) {
            $id = $row['id'];
            $lp = $row['last_post'];

            if ($row['poster_id'] === $user->id) {
                switch ($row['poster_status']) {
                    case Cnst::PT_ARCHIVE:
                        if (
                            null === $second
                            || $row['target_id'] === $second
                            || $row['target'] === $second
                        ) {
                            $idsArc[$id] = $lp;
                        }

                        ++$totalArc;

                        break;
                    case Cnst::PT_NORMAL:
                        if (
                            null === $second
                            || $row['target_id'] === $second
                            || $row['target'] === $second
                        ) {
                            if ($lp > $row['poster_visit']) {
                                $idsNew[$id] = $lp;
                            }

                            $idsCur[$id] = $lp;
                        }

                        if ($lp > $row['poster_visit']) {
                            ++$totalNew;
                        }

                        ++$totalCur;

                        break;
                }
            } elseif ($row['target_id'] === $user->id) {
                switch ($row['target_status']) {
                    case Cnst::PT_ARCHIVE:
                        if (
                            null === $second
                            || $row['poster_id'] === $second
                            || $row['poster'] === $second
                        ) {
                            $idsArc[$id] = $lp;
                        }

                        ++$totalArc;

                        break;
                    case Cnst::PT_NORMAL:
                        if (
                            null === $second
                            || $row['poster_id'] === $second
                            || $row['poster'] === $second
                        ) {
                            if ($lp > $row['target_visit']) {
                                $idsNew[$id] = $lp;
                            }

                            $idsCur[$id] = $lp;
                        }

                        if ($lp > $row['target_visit']) {
                            ++$totalNew;
                        }

                        ++$totalCur;

                        break;
                }
            }
        }

        return [$idsNew, $idsCur, $idsArc, $totalNew, $totalCur, $totalArc];
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
