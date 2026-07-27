<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Grading extends Model
{
    use HasPublicId;

    protected $primaryKey = 'db_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'public_id',
        'name',
        'upper_mark',
        'lower_mark',
        'mention',
    ];

    protected $casts = [
        'upper_mark' => 'decimal:2',
        'lower_mark' => 'decimal:2',
    ];

    protected $hidden = [
        'db_id',
    ];

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    public static function forScore(float $score): ?self
    {
        return self::where('lower_mark', '<=', $score)
            ->where('upper_mark', '>=', $score)
            ->first();
    }

    protected static function booted()
    {
        static::creating(function (Grading $grading) {
            if (empty($grading->public_id)) {
                $grading->public_id = (string) Str::uuid();
            }
        });
    }
}
