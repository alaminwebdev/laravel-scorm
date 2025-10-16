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

    public function scormTrackings()
    {
        return $this->hasMany(ScormTracking::class);
    }

    public function userTrackings()
    {
        return $this->hasMany(ScormTracking::class)->where('user_id', auth()->id());
    }

    /**
     * Scope for launchable SCOs
     */
    public function scopeLaunchable($query)
    {
        return $query->where('is_launchable', true);
    }

    /**
     * Scope for root SCOs (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Check if SCO has children
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if SCO is a root item
     */
    public function getIsRootAttribute(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Get current user's tracking for this SCO
     */
    public function getUserTracking()
    {
        return $this->scormTrackings()->where('user_id', auth()->id())->first();
    }

    /**
     * Check if SCO is completed by current user
     */
    public function getIsCompletedAttribute(): bool
    {
        $tracking = $this->getUserTracking();
        return $tracking ? $tracking->is_completed : false;
    }

    /**
     * Check if SCO is passed by current user
     */
    public function getIsPassedAttribute(): bool
    {
        $tracking = $this->getUserTracking();
        return $tracking ? $tracking->is_passed : false;
    }

    /**
     * Get completion percentage for current user
     */
    public function getCompletionPercentageAttribute(): int
    {
        $tracking = $this->getUserTracking();
        return $tracking ? $tracking->completion_percentage : 0;
    }

    /**
     * Get score percentage for current user
     */
    public function getScorePercentageAttribute(): ?float
    {
        $tracking = $this->getUserTracking();
        return $tracking ? $tracking->score_percentage : null;
    }

    /**
     * Get time spent by current user
     */
    public function getTimeSpentAttribute(): string
    {
        $tracking = $this->getUserTracking();
        return $tracking ? $tracking->formatted_total_time : 'PT0H0M0S';
    }

    /**
     * Get SCO data as array for API responses
     */
    public function toScoArray(): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'title' => $this->title,
            'launch' => $this->launch,
            'sort_order' => $this->sort_order,
            'parent_id' => $this->parent_id,
            'is_launchable' => $this->is_launchable,
            'has_children' => $this->has_children,
            'is_root' => $this->is_root,
            'user_progress' => [
                'is_completed' => $this->is_completed,
                'is_passed' => $this->is_passed,
                'completion_percentage' => $this->completion_percentage,
                'score_percentage' => $this->score_percentage,
                'time_spent' => $this->time_spent,
            ],
            'children' => $this->children->map(function ($child) {
                return $child->toScoArray();
            })
        ];
    }

}
