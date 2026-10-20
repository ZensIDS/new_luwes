<?php

namespace App\Services\DbSync;

use RuntimeException;

/** Kolom yang sama diubah berbeda di lokal & hosting: baris dilewati (tidak disinkron) sampai diselesaikan. */
class SyncConflict extends RuntimeException {}
