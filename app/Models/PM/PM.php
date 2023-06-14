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
use ForkBB\Models\Model;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PBlock;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class PM extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'PMS';

    protected array $repository;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'area' => ['numPages', 'pagination'],
            'page' => ['pagination'],
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

    public function set(int $type, int $key, ?DataModel $value): self
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
                return $this->c->PTopicModel->setModelAttrs($attrs);
            case Cnst::PPOST:
                return $this->c->PPostModel->setModelAttrs($attrs);
            default:
                throw new InvalidArgumentException("Wrong type: {$type}");
        }
    }

    public function accessTopic(int $id): bool
    {
        return isset($this->idsCurrent[$id]) || isset($this->idsArchive[$id]);
    }

    public function inArea(PTopic $topic): ?string
    {
        if (isset($this->idsArchive[$topic->id])) {
            return Cnst::ACTION_ARCHIVE;
        } elseif (isset($this->idsNew[$topic->id])) {
            return Cnst::ACTION_NEW;
        } elseif (isset($this->idsCurrent[$topic->id])) {
            return Cnst::ACTION_CURRENT;
        } else {
            return null;
        }
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
     * Может использовать фильтр по второму пользователю: id или "username" (именно в кавычках)
     */
    public function init(int|string|null $second = null): self
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
    public function infoForUser(User $user, int|string|null $second = null): array
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
                      OR (pt.target_id=?i:id AND pt.target_status=?i:norm)
                      OR (pt.target_id=?i:id AND pt.target_status=?i:arch)
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
                            || '"' . $row['target'] . '"' === $second
                        ) {
                            $idsArc[$id] = $lp;
                        }

                        ++$totalArc;

                        break;
                    case Cnst::PT_NORMAL:
                        if (
                            null === $second
                            || $row['target_id'] === $second
                            || '"' . $row['target'] . '"' === $second
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
                            || '"' . $row['poster'] . '"' === $second
                        ) {
                            $idsArc[$id] = $lp;
                        }

                        ++$totalArc;

                        break;
                    case Cnst::PT_NORMAL:
                        if (
                            null === $second
                            || $row['poster_id'] === $second
                            || '"' . $row['poster'] . '"' === $second
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

        $this->setModelAttr('area', $area);

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
        return $this->c->Func->paginate(
            $this->numPages,
            $this->page,
            'PMAction',
            [
                'second' => $this->second,
                'action' => $this->area,
                'page'   => 'more1', // нестандарная переменная для page
            ]
        );
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
            $this->c->user->disp_topics,
            true
        );

        return $this->loadByIds(Cnst::PTOPIC, \array_keys($ids));
    }

    /**
     * Перечитывает данные приватных сообщений пользователя
     */
    public function recalculate(User $user): void
    {
        if ($user->isGuest) {
            return;
        }

        list($idsNew, $idsCurrent, $idsArchive, $new, $current, $archive) = $this->infoForUser($user);

        $user->u_pm_num_new = $new;
        $user->u_pm_num_all = $current;

        $this->c->users->update($user);
    }

    protected function getblock(): PBlock
    {
        return $this->c->PBlockModel;
    }

    protected function setblock(): void
    {
        throw new RuntimeException('Read-only block property');
    }
}
