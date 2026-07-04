<?php

it('renders the admin login page', function () {
    $this->get('/admin/login')
        ->assertOk();
});

it('redirects guests from the admin dashboard to the login page', function () {
    $this->get('/admin')
        ->assertRedirect();
});
