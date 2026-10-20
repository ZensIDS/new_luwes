<?php

namespace App\Services\DbSync;

use RuntimeException;

/** Baris berubah saat sedang disinkron; dilewati dan diproses lagi pada sinkron berikutnya. */
class SyncDeferred extends RuntimeException {}
