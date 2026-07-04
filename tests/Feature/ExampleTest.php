<?php

it('returns a successful response for the welcome page', function () {
    $this->get('/')
        ->assertOk();
});
