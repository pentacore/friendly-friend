<?php

declare(strict_types=1);

test('the home page sends people to the dashboard', function (): void {
    $this->get(route('home'))->assertRedirect('/dashboard');
});

test('the health check responds', function (): void {
    $this->get('/up')->assertOk();
});
