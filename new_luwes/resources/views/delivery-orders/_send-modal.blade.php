{{-- $do: DeliveryOrder model with items.product loaded --}}
<div class="modal fade" id="sendModal{{ $do->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="form-send-{{ $do->id }}" class="delivery-send-form" data-delivery-code="{{ $do->code }}" action="{{ route('delivery-orders.send', $do->id) }}"
                method="post" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Konfirmasi Pengiriman — {{ $do->code }}</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted"><small>Periksa dan sesuaikan qty yang benar-benar dikirim.</small></p>
                    <table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th class="text-center">Qty Pick</th>
                                <th class="text-center" style="width:110px">Qty Kirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($do->items as $item)
                            <tr>
                                <td>{{ $item->product->name }}</td>
                                <td>{{ $item->sku ?? '-' }}</td>
                                <td class="text-center">
                                    {{ $item->qty }}
                                    @php $k = $item->product->konversiDisplay($item->qty); @endphp
                                    @if($k !== '-')
                                        <span class="label label-info">{{ $k }}</span>
                                    @endif
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][qty_sent]"
                                        class="form-control input-sm text-center"
                                        value="{{ $item->qty }}"
                                        min="0" max="{{ $item->qty }}" required>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label>Upload Dispatch Photo (Optional)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    @if ($do->requestOrder?->additionalNotes?->isNotEmpty())
                        <hr>
                        <h4>Sample <small class="text-muted">(qty sample harus tepat sama dengan qty yang diminta)</small></h4>
                        <table class="table table-bordered table-condensed" style="max-width:600px">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kategori</th>
                                    <th class="text-center">Qty Diminta</th>
                                    <th>Qty Sample</th>
                                    <th>Nama PJ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($do->requestOrder->additionalNotes as $i => $note)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $note->kategori }}</td>
                                        <td class="text-center">{{ $note->qty }}</td>
                                        <td>
                                            <input type="number"
                                                name="samples[{{ $note->id }}][qty_sample]"
                                                class="form-control input-sm text-center qty-sample-input"
                                                value="{{ $note->qty }}"
                                                data-required="{{ $note->qty }}"
                                                data-kategori="{{ $note->kategori }}"
                                                min="0">
                                            <div class="sample-error" style="display:none;color:#a94442;font-size:11px;margin-top:3px;"></div>
                                        </td>
                                        <td>{{ $note->nama_pj ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-confirm-send" data-form-id="form-send-{{ $do->id }}">
                        <i class="fa fa-paper-plane-o"></i> Confirm Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
