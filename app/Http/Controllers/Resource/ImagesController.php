<?php

namespace App\Http\Controllers\Resource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImagesController extends Controller
{
    public function download(Request $request, string $fileName)
    {
        $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/') . '/';
        $filePath = $publicImagesPath . $fileName;
        if (Storage::exists($filePath)) {
            return Storage::download($filePath);
        }
        return response('Not Found', 404);
    }
}
