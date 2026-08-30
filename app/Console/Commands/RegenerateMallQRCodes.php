<?php

namespace App\Console\Commands;

use App\Models\Mall;
use App\Services\QRService;
use Illuminate\Console\Command;

class RegenerateMallQRCodes extends Command
{
    protected $signature = 'mall:regenerate-qr {--type=all : mall, supermarket, or all}';
    protected $description = 'إعادة توليد جميع رموز QR للمولات باستخدام FRONTEND_URL الحالي';

    public function handle(QRService $qrService)
    {
        $type = $this->option('type');
        $frontendUrl = config('app.frontend_url');

        $this->info("🔗 FRONTEND_URL المستخدم: {$frontendUrl}");
        $this->newLine();

        $query = Mall::query();
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $malls = $query->get();
        $count = $malls->count();

        if ($count === 0) {
            $this->warn('⚠️ لا يوجد أي مولات لإعادة توليد QR.');
            return;
        }

        $this->info("📦 تم العثور على {$count} مول/سوبرماركت");
        $this->newLine();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;
        foreach ($malls as $mall) {
            try {
                $url = $frontendUrl . '/mall/' . $mall->slug;
                $path = $qrService->generateQR($url, 'mall_' . $mall->id);
                $mall->update(['qr_code_path' => $path]);
                $updated++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ فشل للمول #{$mall->id} ({$mall->name_ar}): {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ تم إعادة توليد QR لـ {$updated} مول/سوبرماركت بنجاح!");
        $this->info("🖨️ اطبع الـ QR من لوحة الأدمن.");
    }
}