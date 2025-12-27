<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->morphOne(User::class, 'profileable');
    }
}
