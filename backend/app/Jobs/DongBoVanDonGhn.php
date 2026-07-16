<?php

namespace App\Jobs;

use App\Models\VanDonVanChuyen;
use App\Services\GhnShipmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DongBoVanDonGhn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $shipmentId)
    {
    }

    public function handle(GhnShipmentService $shipmentService): void
    {
        $shipment = VanDonVanChuyen::find($this->shipmentId);
        if (!$shipment || $shipment->isTerminal() || !$shipment->ma_van_don_ghn) {
            return;
        }

        $shipmentService->sync($shipment->ma_dh);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('GHN shipment sync job failed.', [
            'shipment_id' => $this->shipmentId,
            'error' => mb_substr($exception->getMessage(), 0, 300),
        ]);
    }
}
