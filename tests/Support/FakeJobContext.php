<?php
/**
 * Filename: FakeJobContext.php
 * Author: Krafty Sprouts Media, LLC
 * Description: A JobContext for tests: a payload, a person, and the progress
 *              a run reported.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

use Teme\Contracts\Jobs\JobContext;

/**
 * @since 1.0.0
 */
final class FakeJobContext implements JobContext
{
    /** @var list<array{0: int, 1: int, 2: string}> */
    public array $progress = [];

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  What the job was queued with.
     */
    public function __construct(
        private readonly array $payload = [],
        private readonly string $personId = '01JD00000000000000000ADMIN',
    ) {}

    /**
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @since 1.0.0
     */
    public function attempt(): int
    {
        return 1;
    }

    /**
     * @since 1.0.0
     */
    public function progress(int $done, int $total, string $message = ''): void
    {
        $this->progress[] = [$done, $total, $message];
    }

    /**
     * @since 1.0.0
     */
    public function personId(): string
    {
        return $this->personId;
    }
}
