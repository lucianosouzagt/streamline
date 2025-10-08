<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rota para documentação da API - removida pois o L5-Swagger já gerencia automaticamente
