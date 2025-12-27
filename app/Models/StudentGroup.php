<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    // Note: Migration did not include public_id for this internal table,
    // but if you add it later, include the Trait.
    protected $guarded = ['id'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
