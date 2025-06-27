<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\EVO\EvoSiteContent;

class Cart extends Model
{
    protected $table = 'carts';
    protected $fillable = ['user_id','promo',];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(EvoSiteContent::class)->withPivot('quantity');
    }
}
