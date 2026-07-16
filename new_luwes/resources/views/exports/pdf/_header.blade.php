<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; color: #222; }
    .header-wrap { display: table; width: 100%; margin-bottom: 4px; }
    .header-logo { display: table-cell; width: 70px; vertical-align: middle; }
    .header-logo img { max-height: 55px; max-width: 65px; }
    .header-info { display: table-cell; vertical-align: middle; }
    .company-name { font-size: 13px; font-weight: bold; text-align: center; }
    .company-sub { text-align: center; font-size: 8px; color: #444; }
    .divider { border: none; border-top: 2px solid #333; margin: 4px 0; }
    .report-title { text-align: center; font-size: 11px; font-weight: bold; margin: 5px 0 2px; }
    .report-periode { text-align: center; font-size: 8px; margin-bottom: 6px; color: #555; }
    table { width: 100%; border-collapse: collapse; font-size: 8px; }
    th { background-color: #4472C4; color: #fff; font-weight: bold; text-align: center; padding: 4px 3px; border: 0.5px solid #2F5597; }
    td { padding: 3px 4px; border: 0.5px solid #aaa; vertical-align: top; }
    .alt td { background-color: #EEF3FB; }
    .tc { text-align: center; }
    .tr { text-align: right; }
</style>

@php
    $companyName  = $settings['name'] ?? 'NAMA PERUSAHAAN';
    $companyAddr  = $settings['address'] ?? '';
    $contactParts = array_filter([$settings['telp'] ?? '', $settings['email'] ?? '', $settings['website'] ?? '']);
    $contactInfo  = implode(' | ', $contactParts);

    $logoPath = $settings['logo'] ?? null;
    $logoSrc  = null;
    if ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
        $logoSrc = \Illuminate\Support\Facades\Storage::disk('public')->path($logoPath);
    } elseif (file_exists(public_path('img/logo.jpeg'))) {
        $logoSrc = public_path('img/logo.jpeg');
    }
@endphp

<div class="header-wrap">
    <div class="header-logo">
        @if ($logoSrc)
            <img src="{{ $logoSrc }}">
        @endif
    </div>
    <div class="header-info">
        <div class="company-name">{{ $companyName }}</div>
        @if ($companyAddr)<div class="company-sub">{{ $companyAddr }}</div>@endif
        @if ($contactInfo)<div class="company-sub">{{ $contactInfo }}</div>@endif
    </div>
</div>
<hr class="divider">