{{--
    Modal filter laporan.
    Variabel: $r (definisi laporan), $outlets, $cashiers, $rafaksis (opsional).
    Setiap input memakai atribut data-param, nama parameter query saat export.
--}}
<div id="modal_{{ $r['id'] }}" class="modal fade rpt-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title rpt-c-{{ $r['color'] }}">
                    <span class="rpt-icon rpt-c-{{ $r['color'] }}"><i class="fa {{ $r['icon'] }}"></i></span>
                    {{ $r['title'] }}
                </h4>
            </div>

            <div class="modal-body">
                <p class="rpt-desc">{{ $r['desc'] }}</p>

                @if (!empty($r['date']))
                    <div class="rpt-presets">
                        <button type="button" class="btn btn-default btn-xs js-preset" data-range="today">Hari ini</button>
                        <button type="button" class="btn btn-default btn-xs js-preset" data-range="7d">7 hari terakhir</button>
                        <button type="button" class="btn btn-default btn-xs js-preset" data-range="month">Bulan ini</button>
                        <button type="button" class="btn btn-default btn-xs js-preset" data-range="lastmonth">Bulan lalu</button>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 form-group">
                            <label>Tanggal mulai</label>
                            <input type="date" class="form-control js-start" data-param="tanggal_mulai"
                                value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                        </div>
                        <div class="col-sm-6 form-group">
                            <label>Tanggal selesai</label>
                            <input type="date" class="form-control js-end" data-param="tanggal_selesai"
                                value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                @endif

                @if (!empty($r['outlet']))
                    @php $outletRequired = !empty($r['outlet_required']); @endphp
                    <div class="form-group">
                        <label>Outlet @if ($outletRequired)<span class="text-red">*</span>@endif</label>
                        <select class="form-control" data-param="outlet_id" @if ($outletRequired) data-required="1" @endif>
                            <option value="">{{ $outletRequired ? '-- Pilih outlet --' : 'Semua outlet' }}</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ $outletRequired && $outlets->count() === 1 ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (!empty($r['folder']))
                    <div class="form-group">
                        <label>Cakupan (nama awal barang)</label>
                        <select class="form-control js-folder" data-param="folder" data-url="{{ route('laporan.outlet.product-folders') }}" disabled>
                            <option value="">Semua barang</option>
                        </select>
                        <p class="help-block" style="margin:6px 0 0;font-size:12px;">Pilih outlet dulu. Folder = kata pertama nama barang, sama seperti folder di Master Harga Jual.</p>
                    </div>
                @endif

                @if (!empty($r['cashier']))
                    <div class="form-group">
                        <label>Akun kasir</label>
                        <select class="form-control" data-param="kasir_id">
                            <option value="">Semua kasir</option>
                            @foreach ($cashiers as $cashier)
                                <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (!empty($r['rafaksi']))
                    <div class="form-group">
                        <label>Periode rafaksi</label>
                        <select class="form-control" data-param="promotion_id">
                            <option value="">Semua rafaksi</option>
                            @foreach ($rafaksis as $rafaksi)
                                <option value="{{ $rafaksi->id }}">
                                    {{ $rafaksi->name }}
                                    ({{ optional($rafaksi->start_at)->format('d/m/Y') ?? '-' }} - {{ optional($rafaksi->end_at)->format('d/m/Y') ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (empty($r['date']) && empty($r['outlet']) && empty($r['cashier']) && empty($r['rafaksi']))
                    <p class="text-muted" style="margin:0;">Laporan ini tidak memerlukan filter.</p>
                @endif

                <p class="rpt-error rpt-error-date"><i class="fa fa-exclamation-circle"></i> Tanggal mulai tidak boleh lebih besar dari tanggal selesai.</p>
                <p class="rpt-error rpt-error-outlet"><i class="fa fa-exclamation-circle"></i> Pilih outlet terlebih dahulu.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <a href="{{ route($r['xls']) }}" class="btn btn-success js-export" data-url="{{ route($r['xls']) }}">
                    <i class="fa fa-file-excel-o"></i> Unduh Excel
                </a>
            </div>
        </div>
    </div>
</div>