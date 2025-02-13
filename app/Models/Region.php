<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/regions/' . $this->image) : null;
    }

    public function countries()
    {
        return $this->hasMany(Country::class);
    }
}
