<?php

namespace App\Exceptions;

use RuntimeException;

class HargaBeliGuardException extends RuntimeException
{
    /** Pesan yang tampil ke user: kembali ke halaman sebelumnya (web) atau 422 (JSON). */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['harga_beli' => $this->getMessage()]);
    }
}