<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_files_exist(): void
    {
        foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png',
                  'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    /**
     * The .ico holds PNG-encoded entries, which GDI+ cannot read back — so it
     * gets validated structurally instead: header, entry count, and every
     * entry pointing at real PNG data inside the file.
     */
    public function test_the_ico_is_well_formed(): void
    {
        $bytes = file_get_contents(public_path('favicon.ico'));

        $this->assertSame(0, unpack('v', substr($bytes, 0, 2))[1], 'reserved field');
        $this->assertSame(1, unpack('v', substr($bytes, 2, 2))[1], 'type should be icon');

        $count = unpack('v', substr($bytes, 4, 2))[1];
        $this->assertGreaterThan(0, $count);

        for ($i = 0; $i < $count; $i++) {
            $entry  = substr($bytes, 6 + (16 * $i), 16);
            $length = unpack('V', substr($entry, 8, 4))[1];
            $offset = unpack('V', substr($entry, 12, 4))[1];

            $this->assertLessThanOrEqual(strlen($bytes), $offset + $length, "entry {$i} runs past the file");
            $this->assertSame("\x89PNG", substr($bytes, $offset, 4), "entry {$i} is not PNG data");
        }
    }

    public function test_the_manifest_is_valid_json_pointing_at_files_that_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('Vaytoven', $manifest['name']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_a_public_page_links_the_icons(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('href="/favicon.svg"', false);
        $response->assertSee('rel="apple-touch-icon"', false);
        $response->assertSee('href="/site.webmanifest"', false);
    }

    /** Every layout owns its own head, so the include has to be in all of them. */
    public function test_the_other_layouts_link_them_too(): void
    {
        foreach (['/become-a-host', '/members', '/legal/tos', '/help', '/properties'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="/favicon.svg"', false);
        }
    }

    /**
     * The homepage and the auth screens each carried their own inline
     * data-URI icon before the shared partial existed. Two rel="icon"
     * declarations means the browser picks one, so those pages showed a
     * different mark from the rest of the site — which is the one thing a
     * favicon must not do.
     */
    public function test_no_page_declares_a_second_competing_icon(): void
    {
        foreach (['/', '/login', '/become-a-host', '/properties'] as $path) {
            $html = $this->get($path)->getContent();

            $this->assertSame(
                0,
                substr_count($html, 'href="data:image/svg+xml'),
                "{$path} still carries an inline data-URI icon alongside the shared one."
            );

            $this->assertSame(
                1,
                preg_match_all('/<link[^>]+rel="icon"[^>]+type="image\/svg\+xml"/', $html),
                "{$path} declares more than one SVG icon."
            );
        }
    }
}
