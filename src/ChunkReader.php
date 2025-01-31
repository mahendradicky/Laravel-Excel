<?php

namespace Maatwebsite\Excel;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithProgressBar;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Files\TemporaryFile;
use Maatwebsite\Excel\Imports\HeadingRowExtractor;
use Maatwebsite\Excel\Jobs\AfterImportJob;
use Maatwebsite\Excel\Jobs\QueueImport;
use Maatwebsite\Excel\Jobs\ReadChunk;
use Throwable;

class  ChunkReader
{
    /**
     * @param  WithChunkReading  $import
     * @param  Reader  $reader
     * @param  TemporaryFile  $temporaryFile
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch|null
     */
    public function read(WithChunkReading $import, Reader $reader, TemporaryFile $temporaryFile, $path, $type)
    {
        if ($import instanceof WithEvents && isset($import->registerEvents()[BeforeImport::class])) {
            $reader->beforeImport($import);
        }

        $chunkSize  = $import->chunkSize();
        $totalRows  = $reader->getTotalRows();
        $worksheets = $reader->getWorksheets($import);


        if ($import instanceof WithProgressBar) {
            $import->getConsoleOutput()->progressStart(array_sum($totalRows));
        }

        $jobs = new Collection();
        foreach ($worksheets as $name => $sheetImport) {
            $startRow         = HeadingRowExtractor::determineStartRow($sheetImport);
            $totalRows[$name] = $sheetImport instanceof WithLimit ? $sheetImport->limit() : $totalRows[$name];

            for ($currentRow = $startRow; $currentRow <= $totalRows[$name]; $currentRow += $chunkSize) {
                $jobs->push(new ReadChunk(
                    $import,
                    $reader->getPhpSpreadsheetReader(),
                    $temporaryFile,
                    $name,
                    $sheetImport,
                    $currentRow,
                    $chunkSize
                ));
            }
        }

        if ($type === "anggotaCU") {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $ba_cu = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 2)->getValue();
            $no_tp = $spreadsheet->getSheet(0)->getCellByColumnAndRow(3, 2)->getValue();
            $id = \Auth::user()->getId();
            $jobs->push(new AfterImportJob($import, $reader, $ba_cu, $no_tp, $id, $type));
        } else if ($type === "laporanCuAll") {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $ba_cu = $spreadsheet->getSheet(0)->getCellByColumnAndRow(1, 2)->getValue();
            $no_tp = null;
            $id = \Auth::user()->getId();
            $jobs->push(new AfterImportJob($import, $reader, $ba_cu, $no_tp, $id, $type));
        } else if ($type === "laporanTpAll") {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $ba_cu = $spreadsheet->getSheet(0)->getCellByColumnAndRow(1, 2)->getValue();
            $no_tp = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 2)->getValue();
            $id = \Auth::user()->getId();
            $jobs->push(new AfterImportJob($import, $reader, $ba_cu, $no_tp, $id, $type));
        }


        if ($import instanceof ShouldQueue) {
            return QueueImport::withChain($jobs->toArray())->dispatch($import);
        }

        $jobs->each(function ($job) {
            try {
                dispatch_now($job);
            } catch (Throwable $e) {
                if (method_exists($job, 'failed')) {
                    $job->failed($e);
                }
                throw $e;
            }
        });

        if ($import instanceof WithProgressBar) {
            $import->getConsoleOutput()->progressFinish();
        }

        unset($jobs);

        return null;
    }
}
