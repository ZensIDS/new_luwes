<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankRequest;
use App\Models\Bank;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    public function index()
    {
        return view('banks.index', [
            'banks' => Bank::get(),
        ]);
    }

    public function create()
    {
        return view('banks.create', []);
    }

    public function store(BankRequest $request)
    {
        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('pic')) {
            // Get the uploaded file
            $file = $request->file('pic');

            // Generate a unique file name
            $fileName = time().'.'.$file->getClientOriginalExtension();

            // Store the file
            $file->storeAs('public/pics', $fileName);

            // Add the file path to the data array
            $data['pic'] = 'storage/pics/'.$fileName;
        }

        Bank::create($data);

        return redirect(route('bank.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Bank $bank)
    {
        dd($bank);
    }

    public function edit(Bank $bank)
    {
        return view('banks.edit', [
            'bank' => $bank,
        ]);
    }

    public function update(BankRequest $request, Bank $bank)
    {
        $data = $request->validated();
        if ($request->hasFile('pic')) {
            // Delete the old image file
            if ($bank->pic) {
                Storage::delete(str_replace('storage', 'public', $bank->pic));
            }
            // Store the new image file
            $file = $request->file('pic');
            $fileName = time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pics', $fileName);
            $data['pic'] = 'storage/pics/'.$fileName;
        }
        $bank->update($data);

        return redirect(route('bank.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Bank $bank)
    {
        // Delete the image file
        if ($bank->pic) {
            Storage::delete(str_replace('storage', 'public', $bank->pic));
        }

        $bank->delete();

        return redirect(route('bank.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
