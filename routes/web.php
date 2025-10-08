<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rota para documentação da API
Route::get('/api/documentation', function () {
    return redirect('/api/documentation/');
});
