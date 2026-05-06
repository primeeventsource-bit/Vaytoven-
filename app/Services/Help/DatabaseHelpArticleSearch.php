<?php

namespace App\Services\Help;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Token-based LIKE search across title/summary/body/search_keywords.
 *
 * Ranking strategy (descending importance):
 *   1. Title hit
 *   2. Summary hit
 *   3. Search-keywords hit
 *   4. Body hit
 *
 * For each row, we count which fields matched the query tokens and order
 * by that count, then by the model's editorial sort_order. This is naive
 * but predictable — and the interface is the swap point for Meilisearch
 * when the catalogue grows past what a couple of LIKE queries can serve.
 */
class DatabaseHelpArticleSearch implements HelpArticleSearch
{
    public function search(string $query, ?HelpAudience $audience = null, int $limit = 10): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return new Collection();
        }

        // Tokenise on whitespace, drop tiny tokens that produce noisy LIKE matches.
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($query)) ?: [],
            fn ($t) => mb_strlen($t) >= 2,
        ));

        if ($tokens === []) {
            return new Collection();
        }

        $rows = HelpArticle::published()
            ->when($audience !== null && $audience !== HelpAudience::All, function (Builder $q) use ($audience) {
                $q->whereIn('audience', [$audience->value, HelpAudience::All->value]);
            })
            ->where(function (Builder $q) use ($tokens) {
                foreach ($tokens as $token) {
                    $like = '%'.$token.'%';
                    $q->where(function (Builder $inner) use ($like) {
                        $inner->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhere('body', 'like', $like)
                            ->orWhere('search_keywords', 'like', $like);
                    });
                }
            })
            ->orderBy('sort_order')
            ->limit($limit * 3) // overfetch to allow in-memory rerank
            ->get();

        // Rerank by weighted field hits across all tokens.
        $scored = $rows->map(function (HelpArticle $a) use ($tokens) {
            $score = 0;
            foreach ($tokens as $t) {
                if (mb_stripos($a->title, $t) !== false)            $score += 4;
                if (mb_stripos($a->summary, $t) !== false)          $score += 3;
                if (mb_stripos((string) $a->search_keywords, $t) !== false) $score += 2;
                if (mb_stripos($a->body, $t) !== false)             $score += 1;
            }
            return [$score, $a];
        });

        return $scored
            ->sortByDesc(fn ($pair) => $pair[0])
            ->pluck(1)
            ->values()
            ->take($limit);
    }
}
