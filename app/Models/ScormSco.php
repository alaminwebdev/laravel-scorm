<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ScormSco extends Model
{

    protected $with = ['children'];

    protected $fillable = [
        'scorm_package_id',
        'identifier',
        'title',
        'launch',
        'sort_order',
        'parent_id',
        'is_launchable'
    ];

    public function package()
    {
        return $this->belongsTo(ScormPackage::class, 'scorm_package_id');
    }

    public function trackings()
    {
        return $this->hasMany(ScormTracking::class);
    }

    public function children()
    {
        return $this->hasMany(ScormSco::class, 'parent_id')->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(ScormSco::class, 'parent_id');
    }

}
