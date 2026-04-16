<?php

use App\Http\Controllers\Api\SaebMunicipioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (prefixo /api)
|--------------------------------------------------------------------------
|
| SAEB por município — alinhado com IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE, ex.:
| {APP_URL}/api/saeb/municipio/{ibge}.json
|
*/

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/saeb/municipio/{code}', [SaebMunicipioController::class, 'show'])
        ->where('code', '[0-9]{7}(\.json)?');
});
