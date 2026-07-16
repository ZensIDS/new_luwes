@extends('layouts.master')

@section('title', 'Bayar Pembelian')

@section('container')
    <section class="content">
        <div class="row">
            <div class="col-sm-12">
                <div class="box">
                    <div class="box-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="box-title">{{ $title }}</h4>
                        </div>
                    </div>
                    <div class="box-body gap-2 align-items-start">
                        <div class="row">
                            <div class="col-md-8 col-sm-12">
                                <div class="border border-2 d-flex flex-column gap-2 rounded-3 overflow-hidden">
                                    <div class="d-flex p-3 border-bottom border-bottom-1">
                                        <div class="d-flex flex-column">
                                            <h4 class="fw-bold">{{ $pembelian->code }}</h4>
                                            <strong>Tanggal Terima :
                                                {{ $pembelian->receipt_date?->format('d/m/Y') ?? '-' }}</strong>
                                            <strong>Pemasok : {{ $pembelian->supplier?->name }}</strong>
                                            {{-- <small>Bank & No Rek : {{ $pembelian->supplier?->bank_name }} {{ $pembelian->supplier?->bank_account }}</small> --}}
                                        </div>
                                        <div class="ms-auto align-self-start">
                                            <h5>
                                                @if ($pembelian->pembelianTransaction?->status == 'unpaid')
                                                    <span class="btn rounded bg-danger">Unpaid</span>
                                                @elseif($pembelian->pembelianTransaction?->status == 'partial')
                                                    <span class="btn rounded bg-warning">Partial</span>
                                                @elseif($pembelian->pembelianTransaction?->status == 'paid')
                                                    <span class="btn rounded bg-success">Paid</span>
                                                @else
                                                    <span class="btn rounded bg-secondary">No Payment</span>
                                                @endif
                                            </h5>
                                        </div>
                                    </div>
                                    <div class="p-3 border-bottom border-bottom-1">
                                        <div class="overflow-auto">
                                            <table id="example1" class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Produk</th>
                                                        <th>Satuan</th>
                                                        <th>Harga Beli</th>
                                                        <th>Qty</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($pembelian->pembelianProducts as $item)
                                                        <tr>
                                                            <td>{{ $item->product?->name }}</td>
                                                            <td>{{ $item->product?->unit }}</td>
                                                            <td>Rp.{{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                                                            <td>{{ $item->qty }}</td>
                                                            <td>Rp.{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div
                                        class="d-flex justify-content-between align-items-center py-2 px-3 border-bottom border-bottom-1">
                                        <h5 class="fw-bold">Grand Total:</h5>
                                        <h5 class="fw-bold">Rp.{{ number_format($pembelian->total, 0, ',', '.') }}</h5>
                                    </div>
                                    <div class="d-flex flex-column pt-2 pb-3 px-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>Paid Amount: </strong>
                                            <h5>
                                                <span class="btn bg-success">Rp.
                                                    {{ number_format($pembelian->pembelianTransaction?->amount ?? 0, 0, ',', '.') }}</span>
                                                <a href="{{ route('laporan.pdf.faktur-pembelian', $pembelian->id) }}" class="btn btn-danger" title="Faktur PDF"
                                                    target="_blank">
                                                    <i class="fa fa-file-pdf-o"></i> Faktur
                                                </a>
                                            </h5>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>Outstanding Balance: </strong>
                                            <h5>
                                                <span
                                                    class="btn bg-secondary">Rp.{{ number_format($pembelian->total - ($pembelian->pembelianTransaction?->amount ?? 0), 0, ',', '.') }}</span>
                                            </h5>
                                        </div>
                                    </div>
                                </div>

                                @if ($pembelian->pembelianTransaction && !empty($paymentHistory))
                                    <div class="box mt-1 border border-2">
                                        <div class="box-header">
                                            <h5 class="mb-0">Riwayat Pembayaran</h5>
                                        </div>
                                        <div class="box-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Tanggal</th>
                                                            <th>Jumlah</th>
                                                            <th>Metode</th>
                                                            <th>Referensi</th>
                                                            <th>Bukti</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($paymentHistory as $history)
                                                            <tr>
                                                                <td>{{ \Carbon\Carbon::parse($history['payment_date'])->format('d/M/Y
                                                                                                                                                                                                                                                                                                                                                                                        H:i T') }}
                                                                </td>
                                                                <td>Rp {{ number_format($history['amount'], 0, ',', '.') }}
                                                                </td>
                                                                <td>{{ ucfirst(str_replace('_', ' ', $history['payment_method'])) }}
                                                                </td>
                                                                <td>{{ $history['payment_reference'] ?? '-' }}</td>
                                                                <td>
                                                                    @if (!empty($history['bukti_transfer']))
                                                                        <a href="{{ Storage::disk('public')->url($history['bukti_transfer']) }}"
                                                                            target="_blank">Lihat</a>
                                                                    @else
                                                                        -
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="fw-bold">
                                                            <td>Total Dibayar</td>
                                                            <td colspan="4">Rp
                                                                {{ number_format($pembelian->pembelianTransaction->amount, 0, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                        <tr class="text-danger">
                                                            <td>Sisa</td>
                                                            <td colspan="4">Rp
                                                                {{ number_format($pembelian->total - $pembelian->pembelianTransaction->amount, 0, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-4 col-sm-12 bg-success">
                                <div class="border border-2 d-flex flex-column gap-2 rounded-3 overflow-hidden">
                                    <h4 class="fw-bold p-3 border-bottom border-bottom-1">
                                        Pembayaran
                                    </h4>
                                    <form id="pembayaranForm" class="p-3" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')

                                        <div class="form-group">
                                            <label class="form-label">Tanggal Bayar</label>
                                            <input type="datetime-local" name="payment_date" class="form-control"
                                                value="{{ $pembelian->pembelianTransaction?->payment_date?->format('Y-m-d\TH:i') }}"
                                                required @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif />
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Metode Pembayaran</label>
                                            <select name="payment_method" class="form-select shadow-none" id="paymentMethod"
                                                required @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif>
                                                <option value="">Pilih Metode Pembayaran</option>
                                                <option value="cash"
                                                    {{ $pembelian->pembelianTransaction?->payment_method == 'cash' ? 'selected' : '' }}>
                                                    Cash</option>
                                                <option value="bank_transfer"
                                                    {{ $pembelian->pembelianTransaction?->payment_method == 'bank_transfer' ? 'selected' : '' }}>
                                                    Bank Transfer</option>
                                                <option value="giro_cek"
                                                    {{ $pembelian->pembelianTransaction?->payment_method == 'giro_cek' ? 'selected' : '' }}>
                                                    Giro/Cek</option>
                                                <option value="lainnya"
                                                    {{ $pembelian->pembelianTransaction?->payment_method == 'lainnya' ? 'selected' : '' }}>
                                                    Lainnya</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">No. Bukti / Referensi</label>
                                            <input type="text" name="payment_reference" class="form-control"
                                                id="paymentReference" value="" placeholder="Mis. TRF-0925-00123"
                                                required @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif />
                                            @if ($pembelian->supplier?->bank_name && $pembelian->supplier?->bank_account)
                                                <small class="text-muted">
                                                    Rekening: {{ $pembelian->supplier->bank_name }} -
                                                    {{ $pembelian->supplier->bank_account }}
                                                </small>
                                            @endif
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Jumlah Pembayaran</label>
                                            <input type="number" name="amount" class="form-control" id="amountInput"
                                                value="0" step="0.01" min="0"
                                                max="{{ $pembelian->total - ($pembelian->pembelianTransaction?->amount ?? 0) }}"
                                                placeholder="Masukkan jumlah yang dibayar" required
                                                @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif />
                                            <small class="text-muted">
                                                @if ($pembelian->pembelianTransaction && $pembelian->pembelianTransaction->status === 'partial')
                                                    Sudah dibayar: Rp
                                                    {{ number_format($pembelian->pembelianTransaction->amount, 0, ',', '.') }}<br>
                                                    Sisa: Rp
                                                    {{ number_format($pembelian->total - $pembelian->pembelianTransaction->amount, 0, ',', '.') }}<br>
                                                    Max input: Rp
                                                    {{ number_format($pembelian->total - $pembelian->pembelianTransaction->amount, 0, ',', '.') }}
                                                @endif
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Bukti Transfer</label>
                                            <input type="file" name="bukti_transfer" class="form-control"
                                                accept="image/*,.pdf" @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif />
                                            @if ($pembelian->pembelianTransaction?->bukti_transfer)
                                                <small class="text-muted">
                                                    <a href="{{ Storage::disk('public')->url($pembelian->pembelianTransaction->bukti_transfer) }}"
                                                        target="_blank">Lihat bukti saat ini</a>
                                                </small>
                                            @endif
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select shadow-none" id="paymentStatus"
                                                required @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif>
                                                <option value="">Pilih Status Pembayaran</option>
                                                <option value="unpaid"
                                                    {{ $pembelian->pembelianTransaction?->status == 'unpaid' ? 'selected' : '' }}>
                                                    Unpaid</option>
                                                <option value="paid"
                                                    {{ $pembelian->pembelianTransaction?->status == 'paid' ? 'selected' : '' }}>
                                                    Paid</option>
                                                <option value="partial"
                                                    {{ $pembelian->pembelianTransaction?->status == 'partial' ? 'selected' : '' }}>
                                                    Partial</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Catatan</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Catatan tambahan (opsional)"
                                                @if ($pembelian->pembelianTransaction?->status === 'paid') disabled @endif>{{ $pembelian->pembelianTransaction?->notes }}</textarea>
                                        </div>

                                        @if ($pembelian->pembelianTransaction?->status !== 'paid')
                                            <div class="form-group">
                                                <div class="d-flex justify-content-end align-items-center">
                                                    <button type="submit" class="btn btn-success">Simpan
                                                        Pembayaran</button>
                                                </div>
                                            </div>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script>
        $('#paymentMethod').change(function() {
            const method = $(this).val();
            if (method === 'bank_transfer' && '{{ $pembelian->supplier?->bank_account }}') {
                const date = new Date();
                const ref = 'TRF-' + date.getFullYear().toString().substr(-2) +
                    ('0' + (date.getMonth() + 1)).slice(-2) + '-' +
                    Math.floor(Math.random() * 100000).toString().padStart(5, '0');
                $('#paymentReference').val(ref);
            }
        });

        $('#amountInput').on('input', function() {
            let value = $(this).val().replace(/[^0-9.]/g, '');
            if (value) {
                const max = {{ $pembelian->total - ($pembelian->pembelianTransaction?->amount ?? 0) }};
                if (parseFloat(value) > max) {
                    $(this).val(max);
                }
            }
        });

        $('#amountInput').on('change', function() {
            const currentPaid = {{ $pembelian->pembelianTransaction?->amount ?? 0 }};
            const inputAmount = parseFloat($(this).val()) || 0;
            const totalPaid = currentPaid + inputAmount;
            const grandTotal = {{ $pembelian->total }};

            if (totalPaid === 0) {
                $('#paymentStatus').val('unpaid');
            } else if (totalPaid >= grandTotal) {
                $('#paymentStatus').val('paid');
            } else {
                $('#paymentStatus').val('partial');
            }
        });

        $('#pembayaranForm').submit(function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            $.ajax({
                url: '{{ route('pembelian.pembayaran.update', $pembelian) }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.href = '{{ route('pembelian.index') }}';
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.message || 'Terjadi kesalahan';
                    alert(errors);
                }
            });
        });
    </script>
@endsection
