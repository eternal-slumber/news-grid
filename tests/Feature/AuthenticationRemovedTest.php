<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('starter authentication routes are not registered', function () {
    expect(Route::has('login'))->toBeFalse()
        ->and(Route::has('logout'))->toBeFalse()
        ->and(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('profile.edit'))->toBeFalse();

    $this->get('/login')->assertNotFound();
});

test('authentication tables are removed while database sessions remain available', function () {
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasColumn('sessions', 'user_id'))->toBeTrue();
});
