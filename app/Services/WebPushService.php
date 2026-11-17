<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    private static function getWebPushInstance(): ?WebPush
    {
        $publicKey  = config('services.webpush.public_key') ?: env('VAPID_PUBLIC_KEY');
        $privateKey = config('services.webpush.private_key') ?: env('VAPID_PRIVATE_KEY');
        $subject    = config('services.webpush.subject') ?: 'mailto:admin@ksvrhospital.rta.mi.th';

        if (empty($publicKey) || empty($privateKey)) {
            Log::warning('WebPush Warning: ยังไม่ได้ตั้งค่า VAPID_PUBLIC_KEY หรือ VAPID_PRIVATE_KEY ใน .env');
            return null;
        }

        $auth = [
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth, ['TTL' => 86400, 'urgency' => 'high']);
        $webPush->setAutomaticPadding(false);

        return $webPush;
    }

    /**
     * ส่ง Web Push ไปยังรายการ User IDs ที่ระบุ
     */
    public static function sendToUsers(array $userIds, string $title, string $body, string $url = '/backend/dashboard', string $tag = 'ksvr-alert', bool $requireInteraction = false): void
    {
        if (empty($userIds)) {
            return;
        }

        $subscriptions = PushSubscription::whereIn('user_id', array_unique($userIds))->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = self::getWebPushInstance();
            if (!$webPush) {
                return;
            }

            $payload = json_encode([
                'title'              => $title,
                'body'               => $body,
                'icon'               => asset('images/logo5.png'),
                'badge'              => asset('images/logo5.png'),
                'url'                => $url,
                'tag'                => $tag . '-' . time(),
                'requireInteraction' => $requireInteraction,
            ], JSON_UNESCAPED_UNICODE);

            foreach ($subscriptions as $sub) {
                $subscription = Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush Send Error: ' . $e->getMessage());
        }
    }

    /**
     * ส่งแจ้งเตือนไปยังบุคลากรส่วนกลางของ รพ. (เช่น พยาบาล ER, OPD, Admin, เจ้าหน้าที่เวชกรรม)
     */
    public static function sendToHospitalRoles(array $targetRoles, string $title, string $body, string $url, string $tag = 'ksvr-er-alert', bool $requireInteraction = true): void
    {
        $users = User::all()->filter(function ($u) use ($targetRoles) {
            $roles = is_string($u->roles) ? json_decode($u->roles, true) : (array)($u->roles ?? $u->role);
            return !empty(array_intersect($targetRoles, (array)$roles));
        });

        self::sendToUsers($users->pluck('id')->toArray(), $title, $body, $url, $tag, $requireInteraction);
    }

    /**
     * ส่งแจ้งเตือนไปยังเสนารักษ์ประจำหน่วยฝึก (และผลัดที่ระบุ)
     */
    public static function sendToUnitMedics(?int $unitId, ?string $intakeBatch, string $title, string $body, string $url, string $tag = 'ksvr-unit-alert'): void
    {
        if (!$unitId) {
            return;
        }

        $users = User::where('military_unit_id', $unitId)->get()->filter(function ($u) use ($intakeBatch) {
            $roles = is_string($u->roles) ? json_decode($u->roles, true) : (array)($u->roles ?? $u->role);
            if (!in_array('medic', (array)$roles)) {
                return false;
            }
            if (!empty($u->intake_batch) && !empty($intakeBatch) && $u->intake_batch !== $intakeBatch) {
                return false;
            }
            return true;
        });

        self::sendToUsers($users->pluck('id')->toArray(), $title, $body, $url, $tag, false);
    }
}