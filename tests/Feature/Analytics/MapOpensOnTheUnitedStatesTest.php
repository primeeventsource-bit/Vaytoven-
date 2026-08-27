<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both dashboard maps open on the United States.
 *
 * Fitting the view to the pins meant a single visitor in London pulled the
 * frame out to the whole globe, and the domestic audience — nearly all of it —
 * collapsed into a smudge over North America. A member signing in to see where
 * their advertising landed got a world map instead.
 *
 * The distinction that matters, and the reason an earlier attempt at this was
 * reverted: this is the opening frame, not a restriction. No maxBounds is set
 * and minZoom is untouched, so zooming out to the rest of the world still
 * works, and the overseas pins are still plotted. Framing is not filtering.
 *
 * Asserted against the partials themselves. The behaviour is client-side, so
 * there is no server response that proves it; what this catches is somebody
 * reverting to fitBounds-on-pins without meaning to.
 */
class MapOpensOnTheUnitedStatesTest extends TestCase
{
    use RefreshDatabase;

    /** The contiguous states, as both partials define them. */
    private const US_BOUNDS = '[[24.4, -124.85], [49.4, -66.9]]';

    private function partial(string $name): string
    {
        $path = resource_path('views/partials/'.$name.'.blade.php');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return list<array{0: string}> */
    public static function maps(): array
    {
        return [
            'engagement map'   => ['engagement-map'],
            'listing analytics'=> ['listing-analytics'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maps')]
    public function test_the_map_frames_the_contiguous_united_states(string $partial): void
    {
        $this->assertStringContainsString(
            self::US_BOUNDS,
            $this->partial($partial),
            'the opening frame should be the contiguous states',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maps')]
    public function test_the_map_opens_centred_on_the_united_states(string $partial): void
    {
        $this->assertStringContainsString(
            'center: [39.5, -98.35]',
            $this->partial($partial),
            'without a US centre the map paints the world for a frame first',
        );
    }

    /**
     * The reason the previous attempt was reverted. A member with a visitor in
     * London must still be able to reach that pin.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('maps')]
    public function test_the_map_is_framed_not_restricted(string $partial): void
    {
        // Comments stripped first: these files discuss maxBounds precisely
        // because it must not be used, and matching the prose would pass no
        // matter what the code did.
        $code = $this->codeOnly($this->partial($partial));

        $this->assertStringNotContainsString(
            'maxBounds',
            $code,
            'a maxBounds option or call would trap the member inside the frame',
        );
    }

    /** Blade comments and JS line comments removed, so assertions see code. */
    private function codeOnly(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

        return preg_replace('!//[^\n]*!', '', $source) ?? $source;
    }

    /**
     * With no domestic traffic at all, framing an empty country would be worse
     * than showing the member where their audience actually is.
     */
    public function test_the_engagement_map_falls_back_when_there_is_no_us_traffic(): void
    {
        $source = $this->partial('engagement-map');

        $this->assertStringContainsString('anyInUs', $source);
        $this->assertStringContainsString(
            'map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });',
            $source,
            'the pin-fitting fallback should still be there',
        );
    }

    /**
     * Filtering to one listing answers a different question — where did THIS
     * advertisement land — so it fits that listing's own footprint. Returning
     * to all listings goes back to the opening frame.
     */
    public function test_only_the_unfiltered_view_is_framed_on_the_united_states(): void
    {
        $source = $this->partial('listing-analytics');

        // Every render of the full pin set asks for the US frame...
        $this->assertSame(
            3,
            substr_count($source, 'renderPins(allPins'),
            'initial paint, toggle-off and the clear button',
        );
        $this->assertSame(
            3,
            substr_count($source, 'frameUnitedStates: true'),
            'all three full-set renders should use the opening frame',
        );

        // ...and the per-listing renders do not.
        $this->assertStringContainsString('renderPins(pins);', $source);
    }
}
