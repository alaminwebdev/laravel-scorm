<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ScormTracking extends Model
{

    protected $fillable = [
        'user_id',
        'scorm_sco_id',
        'status',
        'score',
        'last_accessed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sco()
    {
        return $this->belongsTo(ScormSco::class);
    }
}
