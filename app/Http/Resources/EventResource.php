<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'start_date' => $this->resource->start_date?->toISOString(),
            'end_date' => $this->resource->end_date?->toISOString(),
            'timezone' => $this->resource->timezone,
            'location_type' => $this->resource->location_type,
            'location' => $this->resource->location,
            'virtual_link' => $this->when($this->resource->location_type === 'virtual' || $this->resource->location_type === 'hybrid', $this->resource->virtual_link),
            'capacity' => $this->resource->capacity,
            'poster' => $this->resource->poster ? asset('storage/' . $this->resource->poster) : null,
            'agenda' => $this->resource->agenda,
            'tags' => $this->resource->tags,
            'status' => $this->resource->status,
            'visibility' => $this->resource->visibility,
            'requirements' => $this->resource->requirements,
            'host' => new UserResource($this->whenLoaded('host')),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
