<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->resource->full_name,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'email' => $this->resource->email,
            'avatar' => $this->resource->avatar ? asset('storage/' . $this->resource->avatar) : null,
            'bio' => $this->resource->bio,
            'institution' => $this->resource->institution,
            'department' => $this->resource->department,
            'position' => $this->resource->position,
            'website' => $this->resource->website,
            'phone' => $this->resource->phone,
            'social_links' => $this->resource->social_links,
            'account_status' => $this->resource->account_status,
            'is_admin' => $this->resource->is_admin,
            'email_verified_at' => $this->resource->email_verified_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
