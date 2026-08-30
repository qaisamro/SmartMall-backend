<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\ProductImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ImportCompleted extends Notification implements ShouldBroadcast
{
    use Queueable;

    public ProductImport $import;

    public function __construct(ProductImport $import)
    {
        $this->import = $import;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray($notifiable): array
    {
        $status = $this->import->status;
        $success = $status === 'completed' || $status === 'completed_with_errors';

        $title = $success ? 'تم استيراد المنتجات بنجاح' : 'فشل استيراد المنتجات';
        $body = '';

        if ($success) {
            $parts = [];
            $inserted = $this->import->inserted_rows ?? $this->import->imported_rows ?? 0;
            $updated = $this->import->updated_rows ?? 0;
            $skipped = $this->import->skipped_rows ?? 0;
            $failed  = $this->import->failed_rows ?? 0;
            if ($inserted > 0) $parts[] = 'إدراج: ' . number_format($inserted);
            if ($updated > 0) $parts[] = 'تحديث: ' . number_format($updated);
            if ($skipped > 0) $parts[] = 'تخطي: ' . number_format($skipped);
            if ($failed > 0) $parts[] = 'أخطاء: ' . number_format($failed);
            $body = implode(' | ', $parts);
        } else {
            $body = $this->import->errors[0]['message'] ?? 'حدث خطأ غير متوقع';
        }

        $type = $this->import->import_type ?? 'main';
        $url = $type === 'sub' ? '/admin/excel-upload' : '/admin/excel-upload';

        return [
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ];
    }

    public function toWebPush($notifiable): array
    {
        $data = $this->toArray($notifiable);
        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'],
        ];
    }
}
