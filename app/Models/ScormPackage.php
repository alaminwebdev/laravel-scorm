<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormPackage extends Model
{
    protected $fillable = [
        'title',
        'identifier',
        'version',
        'description',
        'entry_point',
        'file_path',
    ];

    public function scos()
    {
        return $this->hasMany(ScormSco::class)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }
}
