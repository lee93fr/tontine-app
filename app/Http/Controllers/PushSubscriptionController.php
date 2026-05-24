<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $validated['endpoint'],
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
                'user_agent' => $request->userAgent(),
                'enabled' => true,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'subscribed' => true,
            'subscription_id' => $subscription->id,
        ], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:2048'],
        ]);

        $query = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('enabled', true);

        if (! empty($validated['endpoint'])) {
            $query->where('endpoint', $validated['endpoint']);
        }

        $disabled = $query->update(['enabled' => false]);

        return response()->json([
            'subscribed' => false,
            'disabled' => $disabled,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $enabledCount = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('enabled', true)
            ->count();

        return response()->json([
            'subscribed' => $enabledCount > 0,
            'subscriptions_count' => $enabledCount,
            'public_key' => config('services.push.vapid_public_key'),
        ]);
    }

    public function test(Request $request, PushNotificationService $push): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
            'tag' => ['nullable', 'string', 'max:120'],
            'payload' => ['nullable', 'array'],
        ]);

        $payload = array_merge([
            'title' => $validated['title'] ?? 'Test notification',
            'body' => $validated['body'] ?? 'Votre configuration push fonctionne.',
            'url' => $validated['url'] ?? url('/'),
            'tag' => $validated['tag'] ?? 'push-test',
        ], $validated['payload'] ?? []);

        $result = $push->sendPushNotification($request->user()->id, $payload);

        return response()->json([
            'ok' => $result['configured'] && $result['sent'] > 0,
            'result' => $result,
        ], $result['configured'] ? 200 : 503);
    }
}
