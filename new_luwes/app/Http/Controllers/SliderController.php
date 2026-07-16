<?php

namespace App\Http\Controllers;

use App\Http\Requests\SliderRequest;
use App\Models\Slider;
use Illuminate\Support\Facades\Storage;

class SliderController extends Controller
{
    public function index()
    {
        return view('sliders.index', ['sliders' => Slider::get()]);
    }

    public function create()
    {
        return view('sliders.create', []);
    }

    public function store(SliderRequest $request)
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

        Slider::create($data);

        return redirect(route('slider.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Slider $slider)
    {
        dd($slider);
    }

    public function edit(Slider $slider)
    {
        return view('sliders.edit', [
            'slider' => $slider,
        ]);
    }

    public function update(SliderRequest $request, Slider $slider)
    {
        $data = $request->validated();
        if ($request->hasFile('pic')) {
            // Delete the old image file
            if ($slider->pic) {
                Storage::delete(str_replace('storage', 'public', $slider->pic));
            }
            // Store the new image file
            $file = $request->file('pic');
            $fileName = time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pics', $fileName);
            $data['pic'] = 'storage/pics/'.$fileName;
        }
        $slider->update($data);

        return redirect(route('slider.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Slider $slider)
    {
        // Delete the image file
        if ($slider->pic) {
            Storage::delete(str_replace('storage', 'public', $slider->pic));
        }

        $slider->delete();

        return redirect(route('slider.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}
