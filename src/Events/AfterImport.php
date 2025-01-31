<?php

namespace Maatwebsite\Excel\Events;

use App\Events\KegiatanDestroy;
use App\Kegiatan;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Reader;

class AfterImport extends Event
{
    /**
     * @var Reader
     */
    public $reader;

    /**
     * @var object
     */
    private $importable;

    /**
     * @param Reader $reader
     * @param object $importable
     */
    public function __construct(Reader $reader, $importable)
    {
        $this->reader     = $reader;
        $this->importable = $importable;
    }

    /**
     * @return Reader
     */
    public function getReader(): Reader
    {
        return $this->reader;
    }

    /**
     * @return object
     */
    public function getConcernable()
    {
        return $this->importable;
    }

    /**
     * @return mixed
     */
    public function getDelegate()
    {
        return $this->reader;
    }
}
