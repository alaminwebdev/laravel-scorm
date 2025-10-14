<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScormTracking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ScormSco;

class ScormTrackingController extends Controller
{

    /**
     * Save SCORM progress data
     */
    public function saveProgress(Request $request)
    {
        $request->validate([
            'sco_id' => 'required|exists:scorm_scos,id',
            'data' => 'sometimes|array',
            'session_time' => 'sometimes|string',
            'score' => 'sometimes|numeric|min:0|max:100',
            'status' => 'sometimes|in:not_attempted,incomplete,completed,passed,failed',
            'completion_status' => 'sometimes|string',
            'success_status' => 'sometimes|string',
            'suspend_data' => 'sometimes|string'
        ]);

        try {
            $tracking = ScormTracking::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'scorm_sco_id' => $request->sco_id
                ],
                [
                    'status' => $request->status ?? 'incomplete',
                    'score' => $request->score,
                    'last_accessed_at' => now(),
                    'success_status' => $request->success_status,
                    'completion_status' => $request->completion_status,
                    'suspend_data' => $request->suspend_data,
                    'session_time' => $request->session_time,
                    'data' => $request->data // if you have a data column for raw SCORM data
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Progress saved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('SCORM Progress Save Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save progress'
            ], 500);
        }
    }

    /**
     * Get SCORM progress data
     */
    public function getProgress($scoId)
    {
        try {
            $tracking = ScormTracking::where('scorm_sco_id', $scoId)
                ->where('user_id', auth()->id())
                ->first();

            return response()->json([
                'success' => true,
                'data' => $tracking ? $tracking->data : []
            ]);

        } catch (\Exception $e) {
            \Log::error('SCORM Progress Load Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => []
            ]);
        }
    }

}
