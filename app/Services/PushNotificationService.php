<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    protected ?WebPush $webPush = null;

    public function __construct()
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');

        if (!$publicKey || !$privateKey) {
            Log::warning('VAPID keys missing — web push notifications disabled');
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];
        $this->webPush = new WebPush($auth);
    }

    public function sendToUser(int $userId, string $title, string $body, string $url = '/'): array
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) return [];
        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    public function sendToUsers(array $userIds, string $title, string $body, string $url = '/'): array
    {
        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();
        if ($subscriptions->isEmpty()) return [];
        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    public function sendToAll(string $title, string $body, string $url = '/'): array
    {
        $subscriptions = PushSubscription::all();
        if ($subscriptions->isEmpty()) return [];
        return $this->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    protected function sendToSubscriptions($subscriptions, string $title, string $body, string $url): array
    {
        if (!$this->webPush) return [];

        try {
            $payload = json_encode(compact('title', 'body', 'url'));

            foreach ($subscriptions as $sub) {
                $this->webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->p256dh_key,
                        'authToken' => $sub->auth_key,
                    ]),
                    $payload
                );
            }

            $reports = [];
            foreach ($this->webPush->flush() as $report) {
                $rep = [
                    'success' => $report->isSuccess(),
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->isSuccess() ? null : $report->getReason(),
                ];
                $reports[] = $rep;

                if (!$report->isSuccess()) {
                    Log::warning('Push send failed', $rep);
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            }
            return $reports;
        } catch (\Throwable $e) {
            Log::warning('Push notification exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}