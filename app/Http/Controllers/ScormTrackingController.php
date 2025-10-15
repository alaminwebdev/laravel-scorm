<?php

namespace App\Http\Controllers;

use App\Models\ScormTracking;
use App\Models\ScormInteraction;
use App\Models\ScormPackage;
use App\Models\ScormSco;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScormTrackingController extends Controller
{
    public function initialize($packageId, $scoId)
    {
        try {
            DB::beginTransaction();

            $sco = ScormSco::where('id', $scoId)
                ->where('scorm_package_id', $packageId)
                ->first();

            if (!$sco) {
                Log::error("SCO not found", ['package_id' => $packageId, 'sco_id' => $scoId]);
                return response()->json(['success' => false, 'error' => 'SCO not found'], 404);
            }

            $tracking = ScormTracking::firstOrCreate(
                [
                    'user_id' => auth()->id(),
                    'scorm_sco_id' => $scoId
                ],
                [
                    'cmi_core_entry' => 'ab-initio',
                    'entry' => 'ab-initio',
                    'cmi_core_lesson_status' => 'not attempted',
                    'last_accessed_at' => now()
                ]
            );

            DB::commit();

            return response()->json(['success' => true, 'data' => $tracking]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Initialize error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId,
                'user_id' => auth()->id()
            ]);
            return response()->json(['success' => false, 'error' => 'Initialization failed'], 500);
        }
    }

    public function getValue($packageId, $scoId, Request $request)
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

            $value = $this->mapElementToValue($element, $tracking);

            return response()->json(['value' => $value ?? '']);

        } catch (\Exception $e) {
            Log::error('GetValue error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId,
                'element' => $request->query('element')
            ]);
            return response()->json(['value' => '']);
        }
    }

    public function setValue($packageId, $scoId, Request $request)
    {
        try {
            DB::beginTransaction();

            $element = $request->input('element');
            $value = $request->input('value');

            if (!$element) {
                return response()->json(['success' => false, 'error' => 'Element is required']);
            }

            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if (!$tracking) {
                // Create tracking record if it doesn't exist
                $tracking = ScormTracking::create([
                    'user_id' => auth()->id(),
                    'scorm_sco_id' => $scoId,
                    'cmi_core_lesson_status' => 'incomplete',
                    'last_accessed_at' => now()
                ]);
            }

            $this->updateTrackingData($element, $value, $tracking);

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SetValue error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId,
                'element' => $request->input('element'),
                'value' => $request->input('value')
            ]);
            return response()->json(['success' => false, 'error' => 'Set value failed'], 500);
        }
    }

    public function commit($packageId, $scoId)
    {
        try {
            // For now, just return success as data is saved immediately in setValue
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Commit error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId
            ]);
            return response()->json(['success' => false], 500);
        }
    }

    public function terminate($packageId, $scoId)
    {
        try {
            DB::beginTransaction();

            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if ($tracking) {
                $tracking->update([
                    'cmi_core_exit' => 'normal',
                    'last_accessed_at' => now()
                ]);
            }

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Terminate error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId
            ]);
            return response()->json(['success' => false], 500);
        }
    }

    public function terminateSaveStatus($packageId, Request $request)
    {
        try {
            DB::beginTransaction();

            $lessonStatus = $request->input('lesson_status');
            $completionStatus = $request->input('completion_status', $this->mapLessonStatusToCompletion($lessonStatus));
            $successStatus = $request->input('success_status', $this->mapLessonStatusToSuccess($lessonStatus));
            $scoreRaw = $request->input('score_raw');

            // Update all SCOs for this package with the final status
            $scos = ScormSco::where('scorm_package_id', $packageId)->pluck('id');

            ScormTracking::where('user_id', auth()->id())
                ->whereIn('scorm_sco_id', $scos)
                ->update([
                    'cmi_core_lesson_status' => $lessonStatus,
                    'completion_status' => $completionStatus,
                    'success_status' => $successStatus,
                    'cmi_core_score_raw' => $scoreRaw,
                    'last_accessed_at' => now()
                ]);

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TerminateSaveStatus error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'lesson_status' => $request->input('lesson_status')
            ]);
            return response()->json(['success' => false], 500);
        }
    }

    public function recordInteraction($packageId, $scoId, Request $request)
    {
        try {
            DB::beginTransaction();

            $tracking = ScormTracking::where('user_id', auth()->id())
                ->where('scorm_sco_id', $scoId)
                ->first();

            if (!$tracking) {
                // Create tracking if it doesn't exist
                $tracking = ScormTracking::create([
                    'user_id' => auth()->id(),
                    'scorm_sco_id' => $scoId,
                    'cmi_core_lesson_status' => 'incomplete',
                    'last_accessed_at' => now()
                ]);
            }

            ScormInteraction::create([
                'scorm_tracking_id' => $tracking->id,
                'interaction_id' => $request->input('interaction_id', uniqid()),
                'type' => $request->input('type', 'choice'),
                'description' => $request->input('description', ''),
                'learner_response' => $request->input('learner_response', ''),
                'correct_response' => $request->input('correct_response', ''),
                'result' => $request->input('result', 'neutral'),
                'weighting' => $request->input('weighting', 1.0),
                'latency' => $request->input('latency', 0),
                'timestamp' => now()
            ]);

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RecordInteraction error: ' . $e->getMessage(), [
                'package_id' => $packageId,
                'sco_id' => $scoId,
                'request_data' => $request->all()
            ]);
            return response()->json(['success' => false, 'error' => 'Interaction recording failed'], 500);
        }
    }

    private function mapElementToValue($element, $tracking)
    {
        $mapping = [
            'cmi.core.lesson_status' => $tracking->cmi_core_lesson_status,
            'cmi.core.lesson_location' => $tracking->cmi_core_lesson_location,
            'cmi.core.score.raw' => $tracking->cmi_core_score_raw,
            'cmi.core.score.min' => $tracking->cmi_core_score_min,
            'cmi.core.score.max' => $tracking->cmi_core_score_max,
            'cmi.core.total_time' => $this->formatTime($tracking->cmi_core_total_time),
            'cmi.core.session_time' => $this->formatTime($tracking->cmi_core_session_time),
            'cmi.core.entry' => $tracking->cmi_core_entry,
            'cmi.core.exit' => $tracking->cmi_core_exit,
            'cmi.suspend_data' => $tracking->suspend_data,
            'cmi.launch_data' => $tracking->launch_data,

            // SCORM 2004
            'cmi.completion_status' => $tracking->completion_status,
            'cmi.success_status' => $tracking->success_status,
            'cmi.score.scaled' => $tracking->score_scaled,
            'cmi.score.raw' => $tracking->score_raw,
            'cmi.score.min' => $tracking->score_min,
            'cmi.score.max' => $tracking->score_max,
            'cmi.total_time' => $this->formatTime($tracking->total_time),
            'cmi.entry' => $tracking->entry,
            'cmi.progress_measure' => $tracking->progress_measure,
        ];

        return $mapping[$element] ?? '';
    }

    private function updateTrackingData($element, $value, $tracking)
    {
        $updates = [];

        $mapping = [
            'cmi.core.lesson_status' => 'cmi_core_lesson_status',
            'cmi.core.lesson_location' => 'cmi_core_lesson_location',
            'cmi.core.score.raw' => 'cmi_core_score_raw',
            'cmi.core.score.min' => 'cmi_core_score_min',
            'cmi.core.score.max' => 'cmi_core_score_max',
            'cmi.core.total_time' => ['field' => 'cmi_core_total_time', 'processor' => 'parseTime'],
            'cmi.core.session_time' => ['field' => 'cmi_core_session_time', 'processor' => 'parseTime'],
            'cmi.core.entry' => 'cmi_core_entry',
            'cmi.core.exit' => 'cmi_core_exit',
            'cmi.suspend_data' => 'suspend_data',
            'cmi.launch_data' => 'launch_data',

            // SCORM 2004
            'cmi.completion_status' => 'completion_status',
            'cmi.success_status' => 'success_status',
            'cmi.score.scaled' => 'score_scaled',
            'cmi.score.raw' => 'score_raw',
            'cmi.score.min' => 'score_min',
            'cmi.score.max' => 'score_max',
            'cmi.total_time' => ['field' => 'total_time', 'processor' => 'parseTime'],
            'cmi.entry' => 'entry',
            'cmi.progress_measure' => 'progress_measure',
        ];

        if (isset($mapping[$element])) {
            $mappingData = $mapping[$element];

            if (is_array($mappingData)) {
                $field = $mappingData['field'];
                $processor = $mappingData['processor'];
                $updates[$field] = $this->$processor($value);
            } else {
                $updates[$mappingData] = $value;
            }
        }

        // Auto-map statuses between versions
        if ($element === 'cmi.core.lesson_status') {
            $updates['completion_status'] = $this->mapLessonStatusToCompletion($value);
            $updates['success_status'] = $this->mapLessonStatusToSuccess($value);
        } elseif ($element === 'cmi.completion_status') {
            $updates['cmi_core_lesson_status'] = $this->mapCompletionToLessonStatus($value);
        }

        if (!empty($updates)) {
            $updates['last_accessed_at'] = now();
            $tracking->update($updates);
        }
    }

    private function parseTime($timeString)
    {
        // Convert SCORM time format (PT0H0M0S) to seconds
        if (preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $timeString, $matches)) {
            $hours = isset($matches[1]) ? (int) str_replace('H', '', $matches[1]) : 0;
            $minutes = isset($matches[2]) ? (int) str_replace('M', '', $matches[2]) : 0;
            $seconds = isset($matches[3]) ? (int) str_replace('S', '', $matches[3]) : 0;
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
        return 0;
    }

    private function formatTime($seconds)
    {
        // Convert seconds to SCORM time format
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('PT%dH%dM%dS', $hours, $minutes, $seconds);
    }

    private function mapLessonStatusToCompletion($lessonStatus)
    {
        $mapping = [
            'passed' => 'completed',
            'completed' => 'completed',
            'failed' => 'incomplete',
            'incomplete' => 'incomplete',
            'browsed' => 'completed',
            'not attempted' => 'not attempted'
        ];

        return $mapping[$lessonStatus] ?? 'unknown';
    }

    private function mapLessonStatusToSuccess($lessonStatus)
    {
        $mapping = [
            'passed' => 'passed',
            'completed' => 'passed',
            'failed' => 'failed',
            'incomplete' => 'failed',
            'browsed' => 'unknown',
            'not attempted' => 'unknown'
        ];

        return $mapping[$lessonStatus] ?? 'unknown';
    }

    private function mapCompletionToLessonStatus($completionStatus)
    {
        $mapping = [
            'completed' => 'completed',
            'incomplete' => 'incomplete',
            'not attempted' => 'not attempted',
            'unknown' => 'unknown'
        ];

        return $mapping[$completionStatus] ?? 'not attempted';
    }
}