<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'cmi_core_session_time',
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

        // Common fields
        'suspend_data',
        'launch_data',
        'comments',
        'comments_from_lms',

        // Progress tracking
        'progress_measure',
        'scaled_passing_score',

        'last_accessed_at'
    ];

    protected $casts = [
        'cmi_core_score_raw' => 'decimal:2',
        'cmi_core_score_min' => 'decimal:2',
        'cmi_core_score_max' => 'decimal:2',
        'cmi_core_total_time' => 'integer',
        'cmi_core_session_time' => 'integer',

        'score_scaled' => 'decimal:4',
        'score_raw' => 'decimal:2',
        'score_min' => 'decimal:2',
        'score_max' => 'decimal:2',
        'total_time' => 'integer',

        'progress_measure' => 'decimal:4',
        'scaled_passing_score' => 'decimal:4',

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
     * Relationship with ScormInteractions
     */
    public function interactions()
    {
        return $this->hasMany(ScormInteraction::class);
    }

    /**
     * Get lesson status options
     */
    public static function getLessonStatusOptions()
    {
        return [
            'not attempted' => 'Not Attempted',
            'incomplete' => 'Incomplete',
            'completed' => 'Completed',
            'passed' => 'Passed',
            'failed' => 'Failed',
            'browsed' => 'Browsed',
        ];
    }

    /**
     * Get completion status options
     */
    public static function getCompletionStatusOptions()
    {
        return [
            'completed' => 'Completed',
            'incomplete' => 'Incomplete',
            'not attempted' => 'Not Attempted',
            'unknown' => 'Unknown',
        ];
    }

    /**
     * Get success status options
     */
    public static function getSuccessStatusOptions()
    {
        return [
            'passed' => 'Passed',
            'failed' => 'Failed',
            'unknown' => 'Unknown',
        ];
    }

    /**
     * Scope for completed trackings
     */
    public function scopeCompleted($query)
    {
        return $query->where('cmi_core_lesson_status', 'completed')
            ->orWhere('cmi_core_lesson_status', 'passed');
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
        return $query->where('cmi_core_lesson_status', 'not attempted')
            ->orWhere('completion_status', 'not attempted');
    }

    /**
     * Get formatted total time (seconds to readable format)
     */
    public function getFormattedTotalTimeAttribute()
    {
        return $this->formatTime($this->cmi_core_total_time ?: $this->total_time);
    }

    /**
     * Get formatted session time (seconds to readable format)
     */
    public function getFormattedSessionTimeAttribute()
    {
        return $this->formatTime($this->cmi_core_session_time);
    }

    /**
     * Get current score percentage
     */
    public function getScorePercentageAttribute()
    {
        if ($this->cmi_core_score_raw !== null) {
            $min = $this->cmi_core_score_min ?: 0;
            $max = $this->cmi_core_score_max ?: 100;

            if ($max > $min) {
                return (($this->cmi_core_score_raw - $min) / ($max - $min)) * 100;
            }
        } elseif ($this->score_scaled !== null) {
            return ($this->score_scaled + 1) * 50; // Convert -1 to 1 scale to 0-100%
        } elseif ($this->score_raw !== null) {
            $min = $this->score_min ?: 0;
            $max = $this->score_max ?: 100;

            if ($max > $min) {
                return (($this->score_raw - $min) / ($max - $min)) * 100;
            }
        }

        return null;
    }

    /**
     * Check if tracking is completed
     */
    public function getIsCompletedAttribute()
    {
        return in_array($this->cmi_core_lesson_status, ['completed', 'passed', 'browsed']) ||
            in_array($this->completion_status, ['completed']);
    }

    /**
     * Check if tracking is passed
     */
    public function getIsPassedAttribute()
    {
        return $this->cmi_core_lesson_status === 'passed' ||
            $this->success_status === 'passed' ||
            ($this->score_percentage !== null && $this->score_percentage >= 70);
    }

    /**
     * Get current lesson status with fallback
     */
    public function getCurrentLessonStatusAttribute()
    {
        if ($this->cmi_core_lesson_status && $this->cmi_core_lesson_status !== 'not attempted') {
            return $this->cmi_core_lesson_status;
        }

        if ($this->completion_status && $this->completion_status !== 'not attempted') {
            return $this->completion_status;
        }

        return 'not attempted';
    }

    /**
     * Get interaction count
     */
    public function getInteractionsCountAttribute()
    {
        return $this->interactions()->count();
    }

    /**
     * Get correct interactions count
     */
    public function getCorrectInteractionsCountAttribute()
    {
        return $this->interactions()->where('result', 'correct')->count();
    }

    /**
     * Get interaction accuracy percentage
     */
    public function getInteractionAccuracyAttribute()
    {
        $total = $this->interactions_count;
        $correct = $this->correct_interactions_count;

        return $total > 0 ? ($correct / $total) * 100 : 0;
    }

    /**
     * Update last accessed timestamp
     */
    public function touchLastAccessed()
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Calculate total time spent (in seconds)
     */
    public function calculateTotalTimeSpent()
    {
        return $this->cmi_core_total_time ?: $this->total_time ?: 0;
    }

    /**
     * Format time in seconds to SCORM format (PTHHMMSS)
     */
    private function formatTime($seconds)
    {
        if (!$seconds)
            return 'PT0H0M0S';

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    /**
     * Parse SCORM time format to seconds
     */
    public static function parseTime($timeString)
    {
        if (preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $timeString, $matches)) {
            $hours = isset($matches[1]) ? (int) str_replace('H', '', $matches[1]) : 0;
            $minutes = isset($matches[2]) ? (int) str_replace('M', '', $matches[2]) : 0;
            $seconds = isset($matches[3]) ? (int) str_replace('S', '', $matches[3]) : 0;
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
        return 0;
    }

    /**
     * Get progress percentage based on progress_measure or completion
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->progress_measure !== null) {
            return $this->progress_measure * 100;
        }

        if ($this->is_completed) {
            return 100;
        }

        // Estimate progress based on time spent or other factors
        if ($this->cmi_core_lesson_location) {
            return 50; // Default estimate if location is set
        }

        return 0;
    }

    /**
     * Get all tracking data as array (for API responses)
     */
    public function toTrackingArray()
    {
        return [
            'id' => $this->id,
            'sco_id' => $this->scorm_sco_id,
            'sco_title' => $this->sco->title ?? '',
            'lesson_status' => $this->current_lesson_status,
            'completion_status' => $this->completion_status,
            'success_status' => $this->success_status,
            'score_percentage' => $this->score_percentage,
            'score_raw' => $this->cmi_core_score_raw ?? $this->score_raw,
            'total_time' => $this->formatted_total_time,
            'session_time' => $this->formatted_session_time,
            'progress_percentage' => $this->progress_percentage,
            'interactions_count' => $this->interactions_count,
            'correct_interactions_count' => $this->correct_interactions_count,
            'interaction_accuracy' => $this->interaction_accuracy,
            'last_accessed' => $this->last_accessed_at?->format('Y-m-d H:i:s'),
            'is_completed' => $this->is_completed,
            'is_passed' => $this->is_passed,
        ];
    }
}