<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get a setting value by key
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettingSwitch(Request $request)
    {
        $key = $request->input('key');
        
        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'Key is required',
            ], 400);
        }
        
        $setting = Setting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $setting->key,
                'value' => $this->castSettingValue($setting->value),
            ],
        ]);
    }
    
    /**
     * Update a setting value by key
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postSettingsSwitch(Request $request)
    {
        $key = $request->input('key');
        $value = $request->input('value');
        
        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'Key is required',
            ], 400);
        }
        
        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => 'Value is required',
            ], 400);
        }
        
        // Handle specific keys
        if ($key === 'referral_campaign_switch') {
            if (!is_bool($value) && !in_array($value, ['true', 'false', '0', '1', 0, 1])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Value must be a boolean for referral_campaign_switch',
                ], 400);
            }
            
            // Convert to boolean string for storage
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        
        // Update or create the setting
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => [
                'key' => $setting->key,
                'value' => $this->castSettingValue($setting->value),
            ],
        ]);
    }
    
    /**
     * Cast setting value to appropriate type
     * 
     * @param string $value
     * @return mixed
     */
    private function castSettingValue($value)
    {
        if ($value === 'true') {
            return true;
        }
        
        if ($value === 'false') {
            return false;
        }
        
        if (is_numeric($value)) {
            return $value * 1;
        }
        
        return $value;
    }
}
