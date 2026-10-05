<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\PolarWebhookProcessor;
use App\Domain\Billing\WebhookSignature;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Polar webhook'u. İmza doğrulanmadan hiçbir şey kaydedilmez; aynı olay kimliği
 * ikinci kez işlenmez.
 */
class PolarWebhookController extends Controller
{
    public function __invoke(Request $request, PolarWebhookProcessor $processor): JsonResponse
    {
        $id = (string) $request->header('webhook-id');
        $payload = (string) $request->getContent();
        $signature = new WebhookSignature((string) config('services.polar.webhook_secret'));

        if (! $signature->verify($id, (string) $request->header('webhook-timestamp'), (string) $request->header('webhook-signature'), $payload)) {
            Log::warning('Polar webhook imzası doğrulanamadı.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'invalid signature'], 403);
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return response()->json(['message' => 'invalid payload'], 400);
        }

        return response()->json(['result' => $processor->handle($id, $data)]);
    }
}
