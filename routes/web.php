<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This is an API-only application. The single web route redirects visitors
| to the Swagger UI so the browsable documentation is easy to find.
|
*/

Route::get('/', fn () => redirect('/api/documentation'));
