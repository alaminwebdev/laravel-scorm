<?php

namespace App\Http\Controllers;

use App\Models\ScormTracking;
use App\Models\ScormInteraction;
use App\Models\ScormSco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ScormTrackingController extends Controller
{
    /**
     * Initialize SCO - One tracking record per user per SCO
     */
    public function initialize($packageId, $scoId): JsonResponse
    {
        try {
            $sco = ScormSco::where('id', $scoId)
                ->where('scorm_package_id', $packageId)
                ->firstOrFail();

            // One record per user per SCO
            $tracking = ScormTracking::firstOrCreate(
                [
                    'user_id' => auth()->id(),
                    'scorm_sco_id' => $scoId
                ],
                [
                    'cmi_core_lesson_status' => 'not attempted',
                    'cmi_core_entry' => 'ab-initio',
                    'cmi_core_score_min' => 0,
                    'cmi_core_score_max' => 100,
                    'last_accessed_at' => now()
                ]
            );

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('SCORM Initialize Error: ' . $e->getMessage());
            return response()->json(['success' => false]);
        }
    }

    /**
     * Get SCORM data model value
     */
    public function getValue($packageId, $scoId, Request $request): JsonResponse
    {
        try {
            $element = $request->query('element');

            if (!$element) {
                return response()->json(['value' => '']);
            }

            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if (!$tracking) {
                return response()->json(['value' => '']);
            }

            $value = $this->getElementValue($element, $tracking);

            return response()->json(['value' => $value]);

        } catch (\Exception $e) {
            \Log::error('SCORM GetValue Error: ' . $e->getMessage());
            return response()->json(['value' => '']);
        }
    }

    /**
     * Set SCORM data model value - Updates single tracking record
     */
    public function setValue($packageId, $scoId, Request $request): JsonResponse
    {
        try {
            $element = $request->input('element');
            $value = $request->input('value');

            if (!$element) {
                return response()->json(['success' => false, 'error' => 'Element required']);
            }

            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if (!$tracking) {
                return response()->json(['success' => false, 'error' => 'SCO not initialized']);
            }

            // Skip interaction elements - they go to interactions table
            if (strpos($element, 'cmi.interactions.') === 0) {
                return response()->json(['success' => true]);
            }

            $result = $this->setElementValue($element, $value, $tracking);

            return $result['success']
                ? response()->json(['success' => true])
                : response()->json(['success' => false, 'error' => $result['error']]);

        } catch (\Exception $e) {
            \Log::error('SCORM SetValue Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Internal error']);
        }
    }

    /**
     * Commit data - No-op since we save immediately
     */
    public function commit($packageId, $scoId): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    /**
     * Terminate SCO
     */
    public function terminate($packageId, $scoId): JsonResponse
    {
        try {
            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if ($tracking) {
                $tracking->update([
                    'cmi_core_exit' => 'normal',
                    'last_accessed_at' => now()
                ]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('SCORM Terminate Error: ' . $e->getMessage());
            return response()->json(['success' => false]);
        }
    }

    /**
     * Record Quiz Interaction - Only for actual quiz questions
     */
    public function recordInteraction($packageId, $scoId, Request $request): JsonResponse
    {
        try {
            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if (!$tracking) {
                return response()->json(['success' => false, 'error' => 'SCO not initialized']);
            }

            DB::transaction(function () use ($tracking, $request) {
                $interactionId = $request->input('interaction_id');

                // Update or create the interaction
                $interaction = ScormInteraction::updateOrCreate(
                    [
                        'scorm_tracking_id' => $tracking->id,
                        'interaction_id' => $interactionId
                    ],
                    [
                        'type' => $request->input('type', 'choice'),
                        'description' => $request->input('description', ''),
                        'learner_response' => $request->input('learner_response', ''),
                        'correct_response' => $request->input('correct_response', ''),
                        'result' => $request->input('result', 'neutral'),
                        'weighting' => $request->input('weighting', 1.0),
                        'latency' => $request->input('latency', 0),
                        'timestamp' => now()
                    ]
                );

                // Update tracking analytics
                $this->updateTrackingAnalytics($tracking);
            });

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('SCORM Interaction Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to record interaction']);
        }
    }

    /**
     * Update tracking analytics (interaction counts, scores)
     */
    private function updateTrackingAnalytics(ScormTracking $tracking): void
    {
        // Get unique interactions count (group by interaction_id to avoid duplicates)
        $uniqueInteractions = $tracking->interactions()
            ->select('interaction_id')
            ->distinct()
            ->get()
            ->count();

        $correctCount = $tracking->interactions()
            ->where('result', 'correct')
            ->select('interaction_id')
            ->distinct()
            ->get()
            ->count();

        $scorePercentage = $uniqueInteractions > 0
            ? ($correctCount / $uniqueInteractions) * 100
            : null;

        $tracking->update([
            'interactions_count' => $uniqueInteractions,
            'correct_interactions_count' => $correctCount,
            'score_percentage' => $scorePercentage,
            'last_accessed_at' => now()
        ]);
    }

    /**
     * Get element value from tracking
     */
    private function getElementValue(string $element, ScormTracking $tracking): string
    {
        $mapping = [
            // SCORM 1.2 Core elements
            'cmi.core.lesson_status' => $tracking->cmi_core_lesson_status,
            'cmi.core.lesson_location' => $tracking->cmi_core_lesson_location,
            'cmi.core.score.raw' => $tracking->cmi_core_score_raw,
            'cmi.core.score.min' => $tracking->cmi_core_score_min,
            'cmi.core.score.max' => $tracking->cmi_core_score_max,
            'cmi.core.total_time' => $this->formatTime($tracking->cmi_core_total_time),
            'cmi.core.entry' => $tracking->cmi_core_entry,
            'cmi.core.exit' => $tracking->cmi_core_exit,
            'cmi.suspend_data' => $tracking->suspend_data,

            // SCORM 2004 elements - ADD THESE
            'cmi.completion_status' => $tracking->completion_status,
            'cmi.success_status' => $tracking->success_status,
            'cmi.score.scaled' => $tracking->score_scaled,
            'cmi.score.raw' => $tracking->score_raw,
            'cmi.score.min' => $tracking->score_min,
            'cmi.score.max' => $tracking->score_max,
            'cmi.total_time' => $this->formatTime($tracking->total_time),
            'cmi.entry' => $tracking->entry,
        ];

        return $mapping[$element] ?? '';
    }

    /**
     * Set element value with validation
     */
    private function setElementValue(string $element, $value, ScormTracking $tracking): array
    {
        $updates = [];

        // Handle different element types with validation
        switch ($element) {
            // SCORM 1.2 elements
            case 'cmi.core.lesson_status':
                if (!in_array($value, ['passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted'])) {
                    return ['success' => false, 'error' => 'Invalid lesson status'];
                }
                $updates['cmi_core_lesson_status'] = $value;
                // Auto-map to SCORM 2004
                $updates['completion_status'] = $this->mapLessonStatusToCompletion($value);
                $updates['success_status'] = $this->mapLessonStatusToSuccess($value);
                break;

            case 'cmi.core.score.raw':
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return ['success' => false, 'error' => 'Invalid score'];
                }
                $updates['cmi_core_score_raw'] = $value;
                break;

            case 'cmi.core.lesson_location':
                $updates['cmi_core_lesson_location'] = substr($value, 0, 255);
                break;

            case 'cmi.core.total_time':
                $updates['cmi_core_total_time'] = $this->parseTime($value);
                break;

            case 'cmi.suspend_data':
                $updates['suspend_data'] = $value;
                break;

            // SCORM 2004 elements - ADD THESE
            case 'cmi.score.scaled':
                if (!is_numeric($value) || $value < -1 || $value > 1) {
                    return ['success' => false, 'error' => 'Invalid scaled score'];
                }
                $updates['score_scaled'] = $value;
                break;

            case 'cmi.score.raw':
                if (!is_numeric($value) || $value < 0) {
                    return ['success' => false, 'error' => 'Invalid raw score'];
                }
                $updates['score_raw'] = $value;
                break;

            case 'cmi.success_status':
                if (!in_array($value, ['passed', 'failed', 'unknown'])) {
                    return ['success' => false, 'error' => 'Invalid success status'];
                }
                $updates['success_status'] = $value;
                break;

            case 'cmi.completion_status':
                if (!in_array($value, ['completed', 'incomplete', 'not attempted', 'unknown'])) {
                    return ['success' => false, 'error' => 'Invalid completion status'];
                }
                $updates['completion_status'] = $value;
                break;

            case 'cmi.score.min':
                if (!is_numeric($value)) {
                    return ['success' => false, 'error' => 'Invalid min score'];
                }
                $updates['score_min'] = $value;
                break;

            case 'cmi.score.max':
                if (!is_numeric($value)) {
                    return ['success' => false, 'error' => 'Invalid max score'];
                }
                $updates['score_max'] = $value;
                break;

            default:
                // For unknown elements, just log and continue
                \Log::info('Unknown SCORM element: ' . $element);
                break;
        }

        if (!empty($updates)) {
            $updates['last_accessed_at'] = now();
            $tracking->update($updates);
        }

        return ['success' => true];
    }

    /**
     * Helper methods for status mapping
     */
    private function mapLessonStatusToCompletion($status): string
    {
        return match ($status) {
            'passed', 'completed', 'browsed' => 'completed',
            'failed', 'incomplete' => 'incomplete',
            default => 'not attempted'
        };
    }

    private function mapLessonStatusToSuccess($status): string
    {
        return match ($status) {
            'passed' => 'passed',
            'failed' => 'failed',
            default => 'unknown'
        };
    }

    private function formatTime(?int $seconds): string
    {
        if (!$seconds)
            return 'PT0H0M0S';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        return sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    private function parseTime(string $time): int
    {
        if (preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $time, $matches)) {
            $hours = isset($matches[1]) ? (int) str_replace('H', '', $matches[1]) : 0;
            $minutes = isset($matches[2]) ? (int) str_replace('M', '', $matches[2]) : 0;
            $seconds = isset($matches[3]) ? (int) str_replace('S', '', $matches[3]) : 0;
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
        return 0;
    }

    public function getProgress($packageId): JsonResponse
    {
        try {
            $scos = ScormSco::where('scorm_package_id', $packageId)
                ->with([
                    'userTrackings' => function ($query) {
                        $query->where('user_id', auth()->id());
                    }
                ])
                ->where('is_launchable', true)
                ->get();

            $progressData = $scos->map(function ($sco) {
                $tracking = $sco->userTrackings->first();

                return [
                    'sco_id' => $sco->id,
                    'completion_status' => $tracking->completion_status ?? null,
                    'success_status' => $tracking->success_status ?? null,
                    'score_percentage' => $tracking->score_percentage ?? null,
                ];
            });

            return response()->json($progressData);

        } catch (\Exception $e) {
            \Log::error('Get Progress Error: ' . $e->getMessage());
            return response()->json([]);
        }
    }
}