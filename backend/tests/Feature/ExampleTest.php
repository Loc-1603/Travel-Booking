<?php

it('redirects guests from the root to the role chooser', function () {
    $this->get('/')->assertRedirect(route('login.choose'));
});
