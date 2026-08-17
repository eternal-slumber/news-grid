<?php

use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsController::class, 'index'])->name('news.index');
Route::redirect('news', '/');
Route::get('news/{news}', [NewsController::class, 'show'])->name('news.show');
