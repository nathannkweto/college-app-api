<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Qualification extends Model
{
    use HasPublicId;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'code'];

    public function programs() {
        return $this->hasMany(Program::class);
    }
}
