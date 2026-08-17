<?php

use Inertia\Testing\AssertableInertia as Assert;

test('news application is served from the root page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('news/index'));
});

test('legacy news path redirects to the root page', function () {
    $this->get('/news')->assertRedirect('/');
});
