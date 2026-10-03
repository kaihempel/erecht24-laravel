<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

/*
 * The push route is registered while the service provider boots, so these
 * tests recreate the application with the config applied before boot.
 */

it('serves the push endpoint on a custom push path', function (): void {
    $this->rebootWithConfig([
        'erecht24.push_secret' => 'correct-push-secret',
        'erecht24.push_path' => 'hooks/legal/',
    ]);
    Queue::fake();

    $this->postJson('/hooks/legal', ['erecht24_secret' => 'correct-push-secret', 'erecht24_type' => 'ping'])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'pong']);

    $this->postJson('/api/erecht24/push', ['erecht24_secret' => 'correct-push-secret', 'erecht24_type' => 'ping'])
        ->assertNotFound();
});

it('does not register the push route when push is disabled', function (mixed $disabled): void {
    $this->rebootWithConfig([
        'erecht24.push_secret' => 'correct-push-secret',
        'erecht24.push_enabled' => $disabled,
    ]);
    Queue::fake();

    expect(app('router')->getRoutes()->getByName('erecht24.push'))->toBeNull();

    $this->postJson('/api/erecht24/push', ['erecht24_secret' => 'correct-push-secret', 'erecht24_type' => 'imprint'])
        ->assertNotFound();

    Queue::assertNothingPushed();
})->with([false, 'false', '0']);

it('registers the push route by default', function (): void {
    expect(app('router')->getRoutes()->getByName('erecht24.push'))->not->toBeNull()
        ->and(route('erecht24.push', absolute: false))->toBe('/api/erecht24/push');
});
