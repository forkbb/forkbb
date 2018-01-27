<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class TruncateIndex extends Method
{
    /**
     * Очистка поискового индекса
     */
    public function truncateIndex()
    {
        $this->c->DB->truncateTable('search_cache');
        $this->c->DB->truncateTable('search_matches');
        $this->c->DB->truncateTable('search_words');
    }
}
