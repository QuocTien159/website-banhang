<?php

namespace App\Console\Commands;

use App\Jobs\DongBoVanDonGhn as DongBoVanDonGhnJob;
use App\Models\VanDonVanChuyen;
use App\Services\GhnShipmentService;
use Illuminate\Console\Command;

class DongBoVanDonGhn extends Command
{
    protected $signature = 'ghn:sync-shipments {--limit=25 : Số vận đơn tối đa trong một lượt} {--now : Đồng bộ ngay, không đưa vào queue}';

    protected $description = 'Đồng bộ các vận đơn GHN chưa kết thúc';

    public function handle(GhnShipmentService $shipmentService): int
    {
        $limit = min(100, max(1, (int) $this->option('limit')));
        $shipments = VanDonVanChuyen::query()
            ->where('nha_van_chuyen', 'ghn')
            ->whereNotNull('ma_van_don_ghn')
            ->whereNotIn('trang_thai_van_chuyen', ['delivered', 'cancelled', 'returned'])
            ->orderBy('ngay_dong_bo')
            ->limit($limit)
            ->get();

        foreach ($shipments as $shipment) {
            if ($this->option('now')) {
                try {
                    $shipmentService->sync($shipment->ma_dh);
                    $this->line("Đã đồng bộ {$shipment->ma_dh}.");
                } catch (\Throwable $exception) {
                    $this->warn("Chưa đồng bộ được {$shipment->ma_dh}: {$exception->getMessage()}");
                }
                continue;
            }

            DongBoVanDonGhnJob::dispatch($shipment->ma_van_chuyen);
        }

        $this->info("Đã xử lý {$shipments->count()} vận đơn GHN.");

        return self::SUCCESS;
    }
}
