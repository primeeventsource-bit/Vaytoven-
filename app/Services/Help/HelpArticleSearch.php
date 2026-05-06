<?php

namespace App\Services\Help;

use App\Enums\HelpAudience;
use Illuminate\Support\Collection;

/**
 * Searches the curated help center.
 *
 * Implementations:
 *   - DatabaseHelpArticleSearch: token-based LIKE matching against
 *     title/summary/body/search_keywords. Good enough for ~hundreds of
 *     articles; portable across MySQL/SQLite (and so works in CI).
 *   - (future) MeilisearchHelpArticleSearch: typo tolerance + relevance
 *     ranking once the catalogue grows. The interface is a deliberate seam
 *     so the swap is one binding change in the service provider.
 */
interface HelpArticleSearch
{
    /**
     * Returns a Collection<HelpArticle> ordered by relevance.
     *
     * @param  HelpAudience|null  $audience  optional scope; null returns all
     */
    public function search(string $query, ?HelpAudience $audience = null, int $limit = 10): Collection;
}
