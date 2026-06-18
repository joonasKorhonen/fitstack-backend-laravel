<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    public $timestamps = false;

    protected $fillable = ['workout_id', 'movement_id', 'exercise', 'reps', 'weight', 'intensity', 'notes'];

    protected $casts = ['weight' => 'float'];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }
}
