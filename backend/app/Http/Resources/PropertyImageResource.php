<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url'           => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'alt_text'      => $this->alt_text,
            'is_cover'      => $this->is_cover,
            'width'         => $this->width,
            'height'        => $this->height,
        ];
    }
}
