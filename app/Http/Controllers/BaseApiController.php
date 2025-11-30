<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Log;

class BaseApiController 
{
    /**
     * Send a successful JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function sendSuccess($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if (!is_null($message)) {
            $response['message'] = $message;
        }

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Send an error JSON response.
     *
     * @param string|null $message
     * @param mixed $errors
     * @param int $code
     * @return JsonResponse
     */
    protected function sendError(string $message, $errors = null, int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
        ];

        if (!is_null($message)) {
            $response['message'] = $message;
        }

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
    /**
     * Handle exceptions and log them
     *
     * @param \Exception $e
     * @param string $logMessage
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleException(\Exception $e, string $logMessage)
    {
        Log::error($logMessage . ': ' . $e->getMessage());
        if (config('app.debug')) {
            return $this->sendError('خطا في الخادم: ' . $e->getMessage(), [], 500);
        }
        return $this->sendError('ظهر خطا غير متوقع', [], 500);
    }
}
