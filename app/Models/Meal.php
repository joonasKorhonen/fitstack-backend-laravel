<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'date', 'title', 'calories', 'protein', 'carbs', 'fat', 'notes'];

    protected $casts = ['date' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
