<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'code', 'phone_prefix', 'is_active'];

    public function texts()
    {
        return $this->hasMany(Text::class);
    }
}
