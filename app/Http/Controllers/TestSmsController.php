<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseApiController; // Ensure this matches your BaseController location
use Illuminate\Http\Request;
use App\Services\ResalaSmsService;

class TestSmsController extends BaseApiController
{
    protected $smsService;

    public function __construct(ResalaSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendTest(Request $request)
    {
        // Use the provided number or one from the request
        $phone = $request->query('phone', '0912939198');

        $result = $this->smsService->sendOtp($phone);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => 'SMS sent successfully to ' . $phone,
                'api_response' => $result['data']
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send SMS',
            'details' => $result['error']
        ], 500);
    }
}
