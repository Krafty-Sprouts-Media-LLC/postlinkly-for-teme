<?php
/**
 * Filename: FakeHooks.php
 * Author: Krafty Sprouts Media, LLC
 * Description: A hook registry for tests that keeps each listener so a test
 *              can send a hook the way Teme would.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

use Teme\Contracts\Hooks\Hooks;
use Teme\Contracts\Hooks\Order;
use Teme\Contracts\Hooks\Subscription;

/**
 * @since 1.0.0
 */
final class FakeHooks implements Hooks
{
    /** @var array<string, list<callable>> Listeners by hook class. */
    private array $listeners = [];

    /**
     * @since 1.0.0
     */
    public function on(string $hook, callable $listener, ?Order $order = null): Subscription
    {
        $this->listeners[$hook][] = $listener;

        return new Subscription($hook.'#'.count($this->listeners[$hook]));
    }

    /**
     * @since 1.0.0
     */
    public function off(Subscription $subscription): void {}

    /**
     * Sends a hook to its listeners, as Teme does after a change commits.
     *
     * @since 1.0.0
     */
    public function send(object $hook): void
    {
        foreach ($this->listeners[$hook::class] ?? [] as $listener) {
            $listener($hook);
        }
    }

    /**
     * The hook classes something listens to.
     *
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function heard(): array
    {
        return array_keys($this->listeners);
    }
}
