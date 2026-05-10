<?php

namespace App\Models;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $action, ?string $description = null, ?string $subjectType = null, ?int $subjectId = null): self
    {
        $request = request();
        try {
            return static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (QueryException $e) {
            // Do not break user workflow if activity_log table is missing/misaligned.
            Log::warning('Activity log write failed: ' . $e->getMessage(), [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ]);
            return new static();
        }
    }
}
