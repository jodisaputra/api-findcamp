<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $guarded = [];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->flag ? asset('storage/countries/' . $this->flag) : null;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
