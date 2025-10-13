<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScormTracking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ScormSco;

class ScormTrackingController extends Controller
{

    public function commit(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();

        DB::transaction(function () use ($data, $user) {
            foreach ($data as $key => $value) {
                // Expecting keys like cmi.core.lesson_status_123 or cmi.completion_status_123
                if (preg_match('/_(\d+)$/', $key, $matches)) {
                    $scoId = $matches[1];

                    // Check SCO exists
                    $sco = ScormSco::find($scoId);
                    if (!$sco) {
                        continue; // Skip invalid SCO ID
                    }

                    $tracking = ScormTracking::firstOrNew([
                        'user_id' => $user->id,
                        'scorm_sco_id' => $scoId
                    ]);

                    // SCORM 1.2
                    if (strpos($key, 'lesson_status') !== false)
                        $tracking->status = $value;
                    if (strpos($key, 'score.raw') !== false)
                        $tracking->score = $value;

                    // SCORM 2004
                    if (strpos($key, 'completion_status') !== false)
                        $tracking->completion_status = $value;
                    if (strpos($key, 'success_status') !== false)
                        $tracking->success_status = $value;
                    if (strpos($key, 'suspend_data') !== false)
                        $tracking->suspend_data = $value;
                    if (strpos($key, 'session_time') !== false)
                        $tracking->session_time = $value;

                    $tracking->last_accessed_at = now();
                    $tracking->save();
                }
            }
        });

        return response()->json(['success' => true]);
    }

}
