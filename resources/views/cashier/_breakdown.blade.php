{{--
    Rincian penjualan per metode bayar + voucher untuk satu sesi kasir.
    Dipakai di History Kasir dan modal Tutup kasir.
    Variabel: $breakdown (CashierSession::breakdownFor / summary()['breakdown']).
--}}
@php($rp = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.'))
<div class="row">
    <div class="col-md-7">
        <table class="table table-condensed table-bordered" style="margin-bottom:6px; background:#fff;">
            <thead>
                <tr>
                    <th>Metode bayar</th>
                    <th class="text-right">Transaksi</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breakdown['payments'] as $payment)
                    <tr>
                        <td>
                            {{ $payment['name'] }}
                            @if ($payment['is_cash'])
                                <span class="label label-success">tunai</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($payment['count'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $rp($payment['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Belum ada penjualan.</td></tr>
                @endforelse
            </tbody>
            @if (count($breakdown['payments']) > 0)
                <tfoot>
                    <tr>
                        <th>Total semua metode</th>
                        <th class="text-right">{{ number_format($breakdown['sales_count'], 0, ',', '.') }}</th>
                        <th class="text-right">{{ $rp($breakdown['sales_total']) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="col-md-5">
        <table class="table table-condensed table-bordered" style="margin-bottom:6px; background:#fff;">
            <thead>
                <tr><th colspan="2">Voucher yang keluar</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Jumlah voucher</td>
                    <td class="text-right">{{ number_format($breakdown['voucher_count'], 0, ',', '.') }} lembar</td>
                </tr>
                <tr>
                    <td>Nominal potongan</td>
                    <td class="text-right">{{ $rp($breakdown['voucher_total']) }}</td>
                </tr>
            </tbody>
        </table>
        <small class="text-muted">Penjualan non-tunai (tidak masuk uang fisik laci): <strong>{{ $rp($breakdown['non_cash_total']) }}</strong></small>
    </div>
</div>