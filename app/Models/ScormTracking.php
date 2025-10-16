<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScormTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scorm_sco_id',

        // SCORM 1.2 Core elements
        'cmi_core_lesson_status',
        'cmi_core_lesson_location',
        'cmi_core_score_raw',
        'cmi_core_score_min',
        'cmi_core_score_max',
        'cmi_core_total_time',
        'cmi_core_entry',
        'cmi_core_exit',

        // SCORM 2004 elements
        'completion_status',
        'success_status',
        'score_scaled',
        'score_raw',
        'score_min',
        'score_max',
        'total_time',
        'entry',

        // Progress & Analytics
        'interactions_count',
        'correct_interactions_count',
        'score_percentage',
        'suspend_data',

        'last_accessed_at'
    ];

    protected $casts = [
        'cmi_core_score_raw' => 'decimal:2',
        'cmi_core_score_min' => 'decimal:2',
        'cmi_core_score_max' => 'decimal:2',
        'cmi_core_total_time' => 'integer',
        'score_scaled' => 'decimal:4',
        'score_raw' => 'decimal:2',
        'score_min' => 'decimal:2',
        'score_max' => 'decimal:2',
        'total_time' => 'integer',
        'score_percentage' => 'decimal:2',
        'interactions_count' => 'integer',
        'correct_interactions_count' => 'integer',
        'last_accessed_at' => 'datetime'
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with ScormSco
     */
    public function sco()
    {
        return $this->belongsTo(ScormSco::class, 'scorm_sco_id');
    }

    /**
     * Relationship with ScormInteractions (Quiz data only)
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ScormInteraction::class);
    }

    /**
     * Scope for completed trackings
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('cmi_core_lesson_status', ['completed', 'passed']);
    }

    /**
     * Scope for passed trackings
     */
    public function scopePassed($query)
    {
        return $query->where('cmi_core_lesson_status', 'passed')
            ->orWhere('success_status', 'passed');
    }

    /**
     * Scope for failed trackings
     */
    public function scopeFailed($query)
    {
        return $query->where('cmi_core_lesson_status', 'failed')
            ->orWhere('success_status', 'failed');
    }

    /**
     * Scope for not attempted trackings
     */
    public function scopeNotAttempted($query)
    {
        return $query->where('cmi_core_lesson_status', 'not attempted');
    }

    /**
     * Scope for in-progress trackings
     */
    public function scopeInProgress($query)
    {
        return $query->where('cmi_core_lesson_status', 'incomplete');
    }

    /**
     * Check if tracking is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->cmi_core_lesson_status, ['completed', 'passed', 'browsed']);
    }

    /**
     * Check if tracking is passed
     */
    public function getIsPassedAttribute(): bool
    {
        return $this->cmi_core_lesson_status === 'passed' ||
            $this->success_status === 'passed' ||
            ($this->score_percentage !== null && $this->score_percentage >= 70);
    }

    /**
     * Get current score as percentage
     */
    public function getScorePercentageAttribute(): ?float
    {
        if ($this->attributes['score_percentage'] !== null) {
            return (float) $this->attributes['score_percentage'];
        }

        // Calculate from raw score if available
        if ($this->cmi_core_score_raw !== null) {
            $min = $this->cmi_core_score_min ?: 0;
            $max = $this->cmi_core_score_max ?: 100;

            if ($max > $min) {
                return (($this->cmi_core_score_raw - $min) / ($max - $min)) * 100;
            }
        }

        return null;
    }

    /**
     * Get formatted total time (HH:MM:SS)
     */
    public function getFormattedTotalTimeAttribute(): string
    {
        return $this->formatTime($this->cmi_core_total_time);
    }

    /**
     * Get interaction accuracy percentage
     */
    public function getInteractionAccuracyAttribute(): float
    {
        if ($this->interactions_count === 0) {
            return 0.0;
        }

        return ($this->correct_interactions_count / $this->interactions_count) * 100;
    }

    /**
     * Get time spent in seconds
     */
    public function getTimeSpentSecondsAttribute(): int
    {
        return $this->cmi_core_total_time ?: 0;
    }

    /**
     * Get time spent in readable format
     */
    public function getTimeSpentFormattedAttribute(): string
    {
        $seconds = $this->time_spent_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Get completion percentage based on lesson status
     */
    public function getCompletionPercentageAttribute(): int
    {
        if ($this->is_completed) {
            return 100;
        }

        // Estimate based on time spent or other factors
        if ($this->cmi_core_lesson_location) {
            return 50; // Some progress
        }

        return $this->cmi_core_lesson_status === 'not attempted' ? 0 : 10;
    }

    /**
     * Update last accessed timestamp
     */
    public function touchLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Calculate and update analytics
     */
    public function updateAnalytics(): void
    {
        $this->interactions_count = $this->interactions()->count();
        $this->correct_interactions_count = $this->interactions()->where('result', 'correct')->count();

        if ($this->interactions_count > 0) {
            $this->score_percentage = ($this->correct_interactions_count / $this->interactions_count) * 100;
        }

        $this->save();
    }

    /**
     * Get all tracking data as array for API responses
     */
    public function toTrackingArray(): array
    {
        return [
            'id' => $this->id,
            'sco_id' => $this->scorm_sco_id,
            'sco_title' => $this->sco->title ?? '',
            'sco_identifier' => $this->sco->identifier ?? '',
            'lesson_status' => $this->cmi_core_lesson_status,
            'completion_status' => $this->completion_status,
            'success_status' => $this->success_status,
            'score_raw' => $this->cmi_core_score_raw,
            'score_percentage' => $this->score_percentage,
            'time_spent' => $this->formatted_total_time,
            'time_spent_seconds' => $this->time_spent_seconds,
            'interactions_count' => $this->interactions_count,
            'correct_interactions_count' => $this->correct_interactions_count,
            'interaction_accuracy' => $this->interaction_accuracy,
            'completion_percentage' => $this->completion_percentage,
            'is_completed' => $this->is_completed,
            'is_passed' => $this->is_passed,
            'last_accessed' => $this->last_accessed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Format time in seconds to SCORM format
     */
    private function formatTime(?int $seconds): string
    {
        if (!$seconds)
            return 'PT0H0M0S';

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    /**
     * Get formatted total time for SCORM 2004
     */
    public function getFormattedTotalTime2004Attribute(): string
    {
        return $this->formatTime($this->total_time);
    }

    /**
     * Get time spent in seconds for SCORM 2004
     */
    public function getTimeSpentSeconds2004Attribute(): int
    {
        return $this->total_time ?: 0;
    }
}