<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScormInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'scorm_tracking_id',
        'interaction_id',
        'type',
        'description',
        'learner_response',
        'correct_response',
        'result',
        'weighting',
        'latency',
        'timestamp'
    ];

    protected $casts = [
        'weighting' => 'decimal:2',
        'latency' => 'decimal:2',
        'timestamp' => 'datetime'
    ];

    /**
     * Relationship with ScormTracking
     */
    public function tracking()
    {
        return $this->belongsTo(ScormTracking::class, 'scorm_tracking_id');
    }

    /**
     * Get interaction type options
     */
    public static function getTypeOptions()
    {
        return [
            'true-false' => 'True/False',
            'choice' => 'Multiple Choice',
            'fill-in' => 'Fill in the Blank',
            'matching' => 'Matching',
            'performance' => 'Performance',
            'sequencing' => 'Sequencing',
            'likert' => 'Likert Scale',
            'numeric' => 'Numeric',
        ];
    }

    /**
     * Get result options
     */
    public static function getResultOptions()
    {
        return [
            'correct' => 'Correct',
            'incorrect' => 'Incorrect',
            'unanticipated' => 'Unanticipated',
            'neutral' => 'Neutral',
        ];
    }

    /**
     * Scope for correct answers
     */
    public function scopeCorrect($query)
    {
        return $query->where('result', 'correct');
    }

    /**
     * Scope for incorrect answers
     */
    public function scopeIncorrect($query)
    {
        return $query->where('result', 'incorrect');
    }

    /**
     * Get formatted latency (seconds to readable format)
     */
    public function getFormattedLatencyAttribute()
    {
        if (!$this->latency) return null;
        
        $seconds = $this->latency;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Check if interaction is correct
     */
    public function getIsCorrectAttribute()
    {
        return $this->result === 'correct';
    }

    /**
     * Get score based on weighting and result
     */
    public function getScoreAttribute()
    {
        if ($this->result === 'correct') {
            return $this->weighting ?: 1;
        }
        return 0;
    }
}