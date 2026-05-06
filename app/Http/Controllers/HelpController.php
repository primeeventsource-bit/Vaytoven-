<?php

namespace App\Http\Controllers;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use App\Services\Help\HelpArticleSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __construct(private readonly HelpArticleSearch $search)
    {
    }

    /**
     * GET /help — index grouped by category, optionally filtered by audience
     * via ?audience=traveler|host|member.
     */
    public function index(Request $request): View
    {
        $audience = $this->resolveAudience($request->query('audience'));

        $articles = HelpArticle::published()
            ->when($audience !== null, fn ($q) => $q->whereIn('audience', [$audience->value, HelpAudience::All->value]))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return view('help.index', [
            'audience'   => $audience,
            'categories' => $articles,
        ]);
    }

    /**
     * GET /help/{slug} — individual article. 404 if unpublished or missing.
     */
    public function show(string $slug): View
    {
        $article = HelpArticle::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('help.show', ['article' => $article]);
    }

    /**
     * GET /help/search?q=foo&audience=traveler — JSON list of matches.
     * Public so the chat widget and any future in-app search bar can call it
     * without auth. Limited to 10 results to keep responses small.
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $audience = $this->resolveAudience($request->query('audience'));

        $results = $this->search->search($query, $audience, 10);

        return response()->json([
            'query'    => $query,
            'audience' => $audience?->value,
            'results'  => $results->map(fn (HelpArticle $a) => [
                'slug'     => $a->slug,
                'title'    => $a->title,
                'summary'  => $a->summary,
                'audience' => $a->audience->value,
                'category' => $a->category,
                'url'      => route('help.show', $a->slug),
            ])->all(),
        ]);
    }

    private function resolveAudience(mixed $raw): ?HelpAudience
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        return HelpAudience::tryFrom($raw);
    }
}
