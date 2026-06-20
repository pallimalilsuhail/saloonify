<?php

declare(strict_types=1);

use App\Modules\Staff\Support\SyntheticEmail;

test('builds a synthetic email from username + business slug', function (): void {
    expect(SyntheticEmail::make('ali', 'glow-salon'))
        ->toBe('ali@glow-salon.saloonify.local');
});

test('slugifies the username and the business slug', function (): void {
    expect(SyntheticEmail::make('Ali Hassan', 'Glow Salon!'))
        ->toBe('ali-hassan@glow-salon.saloonify.local');
});
