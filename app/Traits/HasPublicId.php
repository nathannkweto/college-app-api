<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

trait HasPublicId
{
    /**
     * Boot the trait to auto-generate UUIDs on creation.
     */
    protected static function bootHasPublicId()
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Helper to find a model by its public ID.
     */
    public static function findByPublicId(string $uuid): ?Model
    {
        return static::where('public_id', $uuid)->first();
    }

    /**
     * Helper to find ID by public ID (for FK setting).
     * Throws 404 if not found (good for APIs).
     */
    public static function getIdFromPublicId(string $uuid): int
    {
        return static::where('public_id', $uuid)->firstOrFail()->id;
    }
}
