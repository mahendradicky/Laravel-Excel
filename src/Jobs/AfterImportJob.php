<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\HasEventBus;
use Maatwebsite\Excel\Reader;
use App\Support\NotificationHelper;
use Throwable;

class AfterImportJob implements ShouldQueue
{
    public $counter = 0, $id, $ba_cu, $no_tp, $type;
    use Queueable, HasEventBus;

    /**
     * @var WithEvents
     */
    private $import;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param object $import
     * @param Reader $reader
     */
    public function __construct($import, Reader $reader, $ba_cu, $no_tp, $id, $type)
    {
        $this->import = $import;
        $this->reader = $reader;
        $this->id = $id;
        $this->ba_cu = $ba_cu;
        $this->no_tp = $no_tp;
        $this->type = $type;
    }

    public function handle()
    {
        if ($this->import instanceof WithEvents) {
            $this->reader->registerListeners($this->import->registerEvents());
        }

        $this->reader->afterImport($this->import);

        if ($this->type === "anggotaCU") {
            NotificationHelper::upload_anggota_cu($this->ba_cu, $this->no_tp, $this->id, 'data anggota cu telah selesai diupload');
        } else if ($this->type === "laporanCuAll") {
            NotificationHelper::upload_laporan_cu_all($this->ba_cu, $this->no_tp, $this->id, 'data laporan konsolidasi cu telah selesai diupload');
        } else if ($this->type === "laporanTpAll") {
            NotificationHelper::upload_laporan_tp_all($this->ba_cu, $this->no_tp, $this->id, 'data laporan tp telah selesai diupload');
        }
    }

    /**
     * @param Throwable $e
     */
    public function failed(Throwable $e)
    {
        if ($this->import instanceof WithEvents) {
            $this->registerListeners($this->import->registerEvents());
            $this->raise(new ImportFailed($e));

            if (method_exists($this->import, 'failed')) {
                $this->import->failed($e);
            }
        }
    }
    public function getTypeNotif($type)
    {
        return $type;
    }
}
