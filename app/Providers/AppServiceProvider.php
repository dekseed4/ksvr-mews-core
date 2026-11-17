<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🌟 ระบบสลับ URL อัตโนมัติ รองรับทั้งการเข้าผ่านวงแลน (192.168.x.x) และโดเมนภายนอก (ksvrhospital.rta.mi.th)
        if (!$this->app->runningInConsole() && request()->getHost()) {
            $host = request()->getHost();
            $port = request()->getPort();

            // ตรวจสอบว่าเป็นการเข้าใช้งานผ่าน IP ภายในวงแลนโรงพยาบาลหรือไม่
            $isInternalLan = in_array($host, ['192.168.4.249', '127.0.0.1', 'localhost'], true)
                || str_starts_with($host, '192.168.')
                || str_starts_with($host, '10.');

            if ($isInternalLan) {
                $scheme = request()->isSecure() ? 'https' : 'http';
                $portPart = ($port && !in_array($port, [80, 443], true)) ? ":{$port}" : '';

                // ล็อกให้ลิงก์, ปุ่มกด, CSS/JS และการ Redirect ทั้งหมดวิ่งอยู่บน IP วงแลน
                URL::forceRootUrl("{$scheme}://{$host}{$portPart}/ksvr-mews");
                URL::forceScheme($scheme);

                // ป้องกันปัญหา 419 Page Expired เวลาล็อกอินผ่าน HTTP ในวงแลน
                config([
                    'session.secure' => false,
                    'session.domain' => null,
                ]);
            } else {
                // กรณีเข้าผ่านอินเทอร์เน็ตภายนอกด้วยโดเมน ksvrhospital.rta.mi.th
                $externalUrl = rtrim(config('app.url', 'https://ksvrhospital.rta.mi.th/ksvr-mews'), '/');
                URL::forceRootUrl($externalUrl);
                URL::forceScheme('https');
            }
        }
    }
}