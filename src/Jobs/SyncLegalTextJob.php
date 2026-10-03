<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

final class SyncLegalTextJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * Safety ceiling on the unique lock, in seconds — longer than the worst-case
     * retry window. Laravel releases the unique lock on completion/failure, so
     * this is not the expected hold time.
     */
    public int $uniqueFor = 600;

    /**
     * @var array<int, int>
     */
    private readonly array $backoff;

    public function __construct(public readonly LegalTextType $type, ?Erecht24Settings $settings = null)
    {
        $settings ??= app(Erecht24Settings::class);

        $this->tries = $settings->syncTries();
        $this->backoff = $settings->syncBackoff();

        $this->onConnection($settings->queueConnection())
            ->onQueue($settings->queueName());
    }

    public function uniqueId(): string
    {
        return $this->type->value;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    public function handle(LegalTextSynchronizer $synchronizer): void
    {
        $synchronizer->sync($this->type);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('eRecht24 legal text sync failed permanently', [
            'type' => $this->type->value,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * FR-015: safe entry point for an incoming push's raw type value. Logs a
     * warning and does not dispatch when $rawType is not a known LegalTextType.
     */
    public static function dispatchForPushType(string $rawType): void
    {
        $type = LegalTextType::tryFrom($rawType);

        if ($type === null) {
            Log::warning('Unrecognized legal text type in push notification', ['type' => $rawType]);

            return;
        }

        self::dispatch($type);
    }
}
