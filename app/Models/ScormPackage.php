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

    public function trackings()
    {
        return $this->hasManyThrough(ScormTracking::class, ScormSco::class);
    }

    /**
     * Get launchable SCOs
     */
    public function launchableScos()
    {
        return $this->scos()->where('is_launchable', true);
    }

    /**
     * Get package progress for current user
     */
    public function getUserProgress()
    {
        $totalScos = $this->launchableScos()->count();
        $completedScos = $this->trackings()
            ->where('user_id', auth()->id())
            ->whereIn('cmi_core_lesson_status', ['completed', 'passed'])
            ->count();

        return [
            'total_scos' => $totalScos,
            'completed_scos' => $completedScos,
            'completion_percentage' => $totalScos > 0 ? ($completedScos / $totalScos) * 100 : 0,
        ];
    }

    /**
     * Check if package is completed by current user
     */
    public function getIsCompletedAttribute(): bool
    {
        $progress = $this->getUserProgress();
        return $progress['completion_percentage'] >= 100;
    }

    /**
     * Get package data as array for API responses
     */
    public function toPackageArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'identifier' => $this->identifier,
            'version' => $this->version,
            'description' => $this->description,
            'entry_point' => $this->entry_point,
            'file_path' => $this->file_path,
            'user_progress' => $this->getUserProgress(),
            'is_completed' => $this->is_completed,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
