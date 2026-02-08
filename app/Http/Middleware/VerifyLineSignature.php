<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLineSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Line-Signature');

        if (!$signature) {
            return response()->json([
                'error' => 'Missing X-Line-Signature header',
            ], 401);
        }

        $channelSecret = config('services.line.channel_secret');
        $body = $request->getContent();

        // Calculate expected signature
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $body, $channelSecret, true)
        );

        // Compare signatures
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }
}
