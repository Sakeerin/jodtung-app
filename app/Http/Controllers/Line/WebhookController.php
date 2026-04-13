<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidEventRequestException;
use LINE\Parser\Exception\InvalidSignatureException;

class WebhookController extends Controller
{
    /**
     * Handle LINE webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $channelSecret = config('services.line.channel_secret');
        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);

        try {
            $parsedRequest = EventRequestParser::parseEventRequest(
                $request->getContent(),
                $channelSecret,
                $signature
            );
        } catch (InvalidSignatureException $e) {
            Log::error('Invalid LINE signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        } catch (InvalidEventRequestException $e) {
            Log::error('Invalid LINE event request', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid event request'], 400);
        }

        foreach ($parsedRequest->getEvents() as $event) {
            // Dispatch to queue so LINE gets a response immediately (<30s SLA)
            ProcessLineEvent::dispatch($event);
        }

        return response()->json(['success' => true]);
    }
}
