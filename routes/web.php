<?php

use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('news', NewsController::class)->name('news.index');
