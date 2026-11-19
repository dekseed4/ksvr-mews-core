<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.external_status.api_key') ?: env('EXTERNAL_STATUS_API_KEY');
        $incomingKey   = $request->header('X-API-KEY') ?: $request->bearerToken();

        if (empty($configuredKey) || empty($incomingKey) || !hash_equals($configuredKey, $incomingKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid or missing API Key',
            ], 401);
        }

        return $next($request);
    }
}