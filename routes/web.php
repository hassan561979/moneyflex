<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This is an API-only application. The single web route points visitors at
| the Swagger UI once it is installed, and falls back to a short pointer
| while the documentation package is not yet in place.
|
*/

Route::get('/', function () {
    if (Route::has('l5-swagger.default.api')) {
        return redirect()->route('l5-swagger.default.api');
    }

    return response()->json([
        'service' => config('app.name'),
        'documentation' => 'not installed yet',
        'health' => url('/api/v1/health'),
    ]);
});
