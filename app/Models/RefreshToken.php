<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefreshToken extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = ['token', 'token_family', 'user_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
