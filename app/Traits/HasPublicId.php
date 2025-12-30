<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasPublicId
{
    protected static function bootHasPublicId()
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'public_id';
    }
}
