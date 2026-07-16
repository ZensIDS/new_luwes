<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Data Barcode Pembelian</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="icon" href="{{ asset('img/logo.png') }}" type="image/png">

    <!-- Bootstrap 3.3.5 -->
    <link rel="stylesheet" href="{{ asset('assets/adminlte/bootstrap/css/bootstrap.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('assets/adminlte/plugins/datatables/dataTables.bootstrap.css') }}">
    <link rel="stylesheet"
        href="{{ asset('assets/adminlte/plugins/datatables/extensions/Responsive/css/dataTables.responsive.css') }}">
    <!-- Datetimepicker -->
    <link href="{{ asset('assets/adminlte/plugins/datepicker/bootstrap-datepicker.min.css') }}" rel="stylesheet"
        type="text/css" />
    <link href="{{ asset('assets/adminlte/plugins/datepicker/datepicker3.css') }}" rel="stylesheet" type="text/css" />
    <!-- Daterangepicker -->
    <link href="{{ asset('assets/adminlte/plugins/daterangepicker/daterangepicker-bs3.css') }}" rel="stylesheet"
        type="text/css" />

    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/adminlte/plugins/select2/select2.min.css') }}">
    <!-- Theme style -->
    {{-- <link rel="stylesheet" href="{{ asset('/AdminLTE-2/dist/css/AdminLTE.min.css') }}"> --}}
    <!-- AdminLTE Skins. Choose a skin from the css/skins folder instead of downloading all of them to reduce the load. -->
    {{-- <link rel="stylesheet" href="{{ asset('/AdminLTE-2/dist/css/skins/_all-skins.min.css') }}"> --}}

    <!-- Google Font -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('assets/zenTheme/css/AdminLTE.min.css') }}">
    <!-- Skins -->
    <link rel="stylesheet" href="{{ asset('assets/zenTheme/css/_all-skins.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/zenTheme/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/zenTheme/css/admin-style.css') }}">
    @viteReactRefresh
    @vite(['resources/js/app.js'])

    <style type="text/css">
        body {
            padding-top: 0px;
            background: #f1f4f7;
            color: #000;
        }

        @media all {
            .page-break {
                //display: none; }
            }

            @media print {
                #bkpos_wrp {
                    display: none;
                }

                .page-break {
                    display: block;
                    page-break-before: always;
                }
            }

            td,
            th {
                padding: 0px;
            }
    </style>
</head>

<body>

    <div class="row" id="bkpos_wrp" style="padding-bottom: 10px; padding-top: 10px;">
        <div class="col-md-12">
            <center>
                <a href="{{ route('pembelian.index') }}" style="text-decoration: none;">
                    <button class="btn btn-primary" style="padding: 6px 12px; border-radius: 2px;">Dashboard</button>
                </a>
                <h1 style="color: #00598c; margin-top: 10px;">Data Barcode Pembelian #{{ $pembelian->code }}</h1>
                <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Laudantium autem ut exercitationem vero facere!</p>
            </center>
        </div>
    </div><!-- /.row -->

    <div style="width: 50%; margin: auto;">
        <div class="row">
            @foreach($pembelian->stocks as $stock)
                <div class="col-md-6">
                    <center>
                        <div>
                            <table border="0" style="border-collapse: collapse; margin-bottom: 20px; background-color: white" width="150px" height="auto">
                                <tr>
                                    <td style="font-family: Arial, Helvetica, sans-serif; text-align: center; font-size: 12px;">{{$stock->product->name}}</td>
                                </tr>
                                <tr>
                                    <td style="font-family: Arial, Helvetica, sans-serif; text-align: center; font-size: 12px;">{!!DNS1D::getBarcodeHTML($stock->product->code,'C39')!!}</td>
                                </tr>
                                <tr>
                                    <td style="font-family: Arial, Helvetica, sans-serif; text-align: center; font-size: 11px;">{{$stock->product->code}}</td>
                                </tr>
                                <tr>
                                    <td style="font-family: Arial, Helvetica, sans-serif; text-align: center; font-size: 12px;">@currency($stock->product->harga_jual)</td>
                                </tr>
                            </table>
                        </div>
                    </center>
                </div>
            @endforeach
        </div>
    </div>
</body>

</html>
