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
    public static function getTypeOptions(): array
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
            'other' => 'Other'
        ];
    }

    /**
     * Get result options
     */
    public static function getResultOptions(): array
    {
        return [
            'correct' => 'Correct',
            'incorrect' => 'Incorrect',
            'unanticipated' => 'Unanticipated',
            'neutral' => 'Neutral',
        ];
    }

    /**
     * Scope for correct interactions
     */
    public function scopeCorrect($query)
    {
        return $query->where('result', 'correct');
    }

    /**
     * Scope for incorrect interactions
     */
    public function scopeIncorrect($query)
    {
        return $query->where('result', 'incorrect');
    }

    /**
     * Scope for interactions by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if interaction is correct
     */
    public function getIsCorrectAttribute(): bool
    {
        return $this->result === 'correct';
    }

    /**
     * Get score based on weighting and result
     */
    public function getScoreAttribute(): float
    {
        if ($this->is_correct) {
            return (float) ($this->weighting ?: 1.0);
        }
        return 0.0;
    }

    /**
     * Get formatted latency (seconds to readable format)
     */
    public function getFormattedLatencyAttribute(): ?string
    {
        if (!$this->latency)
            return null;

        $seconds = $this->latency;
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
     * Get interaction data as array for API responses
     */
    public function toInteractionArray(): array
    {
        return [
            'id' => $this->id,
            'interaction_id' => $this->interaction_id,
            'type' => $this->type,
            'type_display' => self::getTypeOptions()[$this->type] ?? $this->type,
            'description' => $this->description,
            'learner_response' => $this->learner_response,
            'correct_response' => $this->correct_response,
            'result' => $this->result,
            'result_display' => self::getResultOptions()[$this->result] ?? $this->result,
            'is_correct' => $this->is_correct,
            'weighting' => (float) $this->weighting,
            'score' => $this->score,
            'latency' => (float) $this->latency,
            'formatted_latency' => $this->formatted_latency,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get user from tracking relationship
     */
    public function getUserAttribute()
    {
        return $this->tracking->user;
    }

    /**
     * Get SCO from tracking relationship
     */
    public function getScoAttribute()
    {
        return $this->tracking->sco;
    }
}