<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ExamGroup extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}
