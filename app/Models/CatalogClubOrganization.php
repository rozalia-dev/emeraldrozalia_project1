<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogClubOrganization extends Model
{
    protected $fillable = ['catalog_club_id', 'taxonomy_type'];

    public function club(): BelongsTo
    {
        return $this->belongsTo(CatalogClub::class, 'catalog_club_id');
    }
}
