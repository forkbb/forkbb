<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;

class TruncateIndex extends Method
{
    /**
     * Очистка поискового индекса
     */
    public function truncateIndex(): void
    {
        $this->c->DB->truncateTable('::search_cache');
        $this->c->DB->truncateTable('::search_matches');
        $this->c->DB->truncateTable('::search_words');
    }
}
