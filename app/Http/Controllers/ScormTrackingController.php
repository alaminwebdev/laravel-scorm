<?php

namespace App\Http\Controllers;

use App\Models\ScormPackage;
use App\Models\ScormSco;
use App\Models\ScormTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScormTrackingController extends Controller
{

    private function validateScoIsLaunchable(ScormSco $sco)
    {
        if (!$sco->is_launchable) {
            throw new \Exception("SCO '{$sco->title}' is not launchable and cannot be tracked.");
        }
    }
    /**
     * Initialize SCORM session
     */
    public function initialize(Request $request, ScormPackage $package, ScormSco $sco)
    {
        $this->validateScoIsLaunchable($sco);
        $user = Auth::user();

        $tracking = ScormTracking::firstOrCreate([
            'user_id' => $user->id,
            'scorm_sco_id' => $sco->id,
        ], [
            'cmi_core_lesson_status' => 'not attempted',
            'cmi_core_entry' => 'ab-initio',
            'cmi_core_total_time' => 0,
            'status' => 'not_attempted',
        ]);

        // Update entry point if resuming
        if ($tracking->cmi_core_lesson_status !== 'not attempted') {
            $tracking->update(['cmi_core_entry' => 'resume']);
        }

        $tracking->update([
            'last_accessed_at' => now(),
            'cmi_core_session_time' => 0,
        ]);

        return response()->json([
            'success' => true,
            'tracking' => $tracking
        ]);
    }

    /**
     * Get SCORM values
     */
    public function getValue(Request $request, ScormPackage $package, ScormSco $sco)
    {
        $this->validateScoIsLaunchable($sco);
        $user = Auth::user();

        $tracking = ScormTracking::where([
            'user_id' => $user->id,
            'scorm_sco_id' => $sco->id,
        ])->first();

        if (!$tracking) {
            return response()->json(['value' => '']);
        }

        $element = $request->get('element');
        $value = $this->mapElementToValue($element, $tracking);

        return response()->json(['value' => $value]);
    }

    /**
     * Set SCORM values
     */
    public function setValue(Request $request, ScormPackage $package, ScormSco $sco)
    {
        $this->validateScoIsLaunchable($sco);
        $user = Auth::user();

        $tracking = ScormTracking::firstOrCreate([
            'user_id' => $user->id,
            'scorm_sco_id' => $sco->id,
        ]);

        $element = $request->get('element');
        $value = $request->get('value');

        $this->updateTrackingData($tracking, $element, $value);

        return response()->json(['success' => true]);
    }

    /**
     * Commit SCORM data
     */
    public function commit(Request $request, ScormPackage $package, ScormSco $sco)
    {
        // Force save any pending data
        return response()->json(['success' => true]);
    }

    /**
     * Terminate SCORM session
     */
    public function terminate(Request $request, ScormPackage $package, ScormSco $sco)
    {
        $this->validateScoIsLaunchable($sco);
        $user = Auth::user();

        $tracking = ScormTracking::where([
            'user_id' => $user->id,
            'scorm_sco_id' => $sco->id,
        ])->first();

        if ($tracking) {
            // Update total time
            $totalTime = $tracking->cmi_core_total_time + $tracking->cmi_core_session_time;
            $tracking->update([
                'cmi_core_total_time' => $totalTime,
                'cmi_core_session_time' => 0,
                'cmi_core_exit' => 'normal',
                'last_accessed_at' => now(),
            ]);

            // Sync with your existing status fields
            $this->syncLegacyFields($tracking);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Map SCORM elements to database values
     */
    private function mapElementToValue($element, $tracking)
    {
        $elementMap = [
            'cmi.core.lesson_status' => $tracking->cmi_core_lesson_status ?? 'not attempted',
            'cmi.core.lesson_location' => $tracking->cmi_core_lesson_location ?? '',
            'cmi.core.score.raw' => $tracking->cmi_core_score_raw ?? '',
            'cmi.core.score.min' => $tracking->cmi_core_score_min ?? '0',
            'cmi.core.score.max' => $tracking->cmi_core_score_max ?? '100',
            'cmi.core.total_time' => $this->formatTimeForSCORM($tracking->cmi_core_total_time ?? 0),
            'cmi.core.session_time' => $this->formatTimeForSCORM($tracking->cmi_core_session_time ?? 0),
            'cmi.core.entry' => $tracking->cmi_core_entry ?? 'ab-initio',
            'cmi.core.exit' => $tracking->cmi_core_exit ?? '',
            'cmi.suspend_data' => $tracking->suspend_data ?? '',
            'cmi.launch_data' => $tracking->launch_data ?? '',
        ];

        return $elementMap[$element] ?? '';
    }

    /**
     * Update tracking data based on SCORM element
     */
    private function updateTrackingData($tracking, $element, $value)
    {
        $updates = [];

        switch ($element) {
            case 'cmi.core.lesson_status':
                $updates['cmi_core_lesson_status'] = $value;
                break;
            case 'cmi.core.lesson_location':
                $updates['cmi_core_lesson_location'] = $value;
                break;
            case 'cmi.core.score.raw':
                $updates['cmi_core_score_raw'] = $value;
                break;
            case 'cmi.core.score.min':
                $updates['cmi_core_score_min'] = $value;
                break;
            case 'cmi.core.score.max':
                $updates['cmi_core_score_max'] = $value;
                break;
            case 'cmi.suspend_data':
                $updates['suspend_data'] = $value;
                break;
            case 'cmi.launch_data':
                $updates['launch_data'] = $value;
                break;
        }

        if (!empty($updates)) {
            $tracking->update($updates);
            $this->syncLegacyFields($tracking);
        }
    }

    /**
     * Sync SCORM standard fields with your existing fields
     */
    private function syncLegacyFields($tracking)
    {
        $updates = [];

        // Map SCORM status to your status field
        $statusMap = [
            'not attempted' => 'not_attempted',
            'incomplete' => 'incomplete',
            'completed' => 'completed',
            'passed' => 'passed',
            'failed' => 'failed',
        ];

        if (isset($statusMap[$tracking->cmi_core_lesson_status])) {
            $updates['status'] = $statusMap[$tracking->cmi_core_lesson_status];
        }

        // Sync score
        if ($tracking->cmi_core_score_raw !== null) {
            $updates['score'] = $tracking->cmi_core_score_raw;
        }

        // Sync completion status
        if (in_array($tracking->cmi_core_lesson_status, ['completed', 'passed'])) {
            $updates['completion_status'] = 'completed';
        } elseif ($tracking->cmi_core_lesson_status === 'incomplete') {
            $updates['completion_status'] = 'incomplete';
        }

        // Sync success status
        if ($tracking->cmi_core_lesson_status === 'passed') {
            $updates['success_status'] = 'passed';
        } elseif ($tracking->cmi_core_lesson_status === 'failed') {
            $updates['success_status'] = 'failed';
        }

        if (!empty($updates)) {
            $tracking->update($updates);
        }
    }

    /**
     * Format time for SCORM (HH:MM:SS)
     */
    private function formatTimeForSCORM($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
}