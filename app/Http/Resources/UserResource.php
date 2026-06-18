<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int         $id
 * @property string      $username
 * @property string|null $email
 * @property string|null $avatar_url
 * @property \Illuminate\Support\Carbon $created_at
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user into the API response shape shared by all endpoints.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'username'  => $this->username,
            'email'     => $this->email,
            'avatarUrl' => $this->avatar_url,
            'createdAt' => $this->created_at,
        ];
    }
}
