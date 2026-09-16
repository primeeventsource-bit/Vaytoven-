<?php

namespace App\Services\Fulfillment;

/**
 * One enrollment thank-you offer, exactly as a member is shown it.
 *
 * Versioned and hashed: an audit record names the key, version and hash of the
 * offer that was on screen, so "what were they offered" is answered from the
 * record even after the catalog changes. Change the wording → change the
 * version. Never edit a published version in place.
 */
final readonly class Incentive
{
    /**
     * @param  list<string>  $included
     */
    public function __construct(
        public string $key,
        public string $version,
        public string $name,          // "$300 Dining Rewards"
        public string $value,         // "$300"
        public string $provider,
        public string $eyebrow,       // "OUR THANK-YOU WHEN YOU ENROLL"
        public string $headline,      // "$300" / "2 Airfares"
        public string $subheadline,   // "in Dining Rewards"
        public string $tagline,       // "Breakfast · Lunch · Dinner"
        public string $theme,         // pink | purple | purple-deep | pink-purple
        public string $icon,          // dining | travel | hotel
        public string $viewButton,    // "VIEW MY DINING REWARD"
        public array $included,
        public string $finePrint,
    ) {
    }

    public function viewButton(): string
    {
        return $this->viewButton;
    }

    public function continueButton(): string
    {
        return 'CONTINUE & CLAIM MY REWARD';
    }

    public function presentationHash(): string
    {
        return hash('sha256', implode("\n", [
            $this->key, $this->version, $this->name, $this->value, $this->provider,
            $this->headline, $this->subheadline, $this->tagline,
            ...$this->included, $this->finePrint,
        ]));
    }

    public function heroGradient(): string
    {
        return match ($this->theme) {
            'purple'      => 'linear-gradient(135deg, #b865d8 0%, #9b45cf 55%, #7b2cbf 100%)',
            'purple-deep' => 'linear-gradient(135deg, #7b2cbf 0%, #9b45cf 55%, #b865d8 100%)',
            'pink-purple' => 'linear-gradient(135deg, #FF3D8A 0%, #b43fb8 50%, #7B2CBF 100%)',
            default       => 'linear-gradient(135deg, #FF3D8A 0%, #e85aa0 55%, #D63384 100%)',
        };
    }

    /** @return array<string, string> the incentive columns of an audit record */
    public function columns(): array
    {
        return [
            'incentive_key'               => $this->key,
            'incentive_name'              => $this->name,
            'incentive_value'             => $this->value,
            'incentive_provider'          => $this->provider,
            'incentive_version'           => $this->version,
            'incentive_presentation_hash' => $this->presentationHash(),
        ];
    }
}
