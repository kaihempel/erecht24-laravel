<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Console;

use Illuminate\Console\Command;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;
use KaiHempel\ERecht24\Sync\SyncResult;

final class SyncLegalTextCommand extends Command
{
    protected $signature = 'erecht24:sync {type?}';

    protected $description = 'Synchronize legal text(s) from the eRecht24 API into local storage.';

    public function handle(LegalTextSynchronizer $synchronizer): int
    {
        $rawType = $this->argument('type');

        if ($rawType !== null) {
            $rawType = is_scalar($rawType) ? (string) $rawType : '';
            $type = LegalTextType::tryFrom($rawType);

            if ($type === null) {
                $this->error(sprintf('Unknown legal text type [%s].', $rawType));

                return self::FAILURE;
            }

            try {
                $this->report($synchronizer->sync($type));
            } catch (\Throwable $e) {
                $this->error(sprintf('Failed to synchronize [%s]: %s', $type->value, $e->getMessage()));

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        foreach ($synchronizer->syncAll() as $result) {
            $this->report($result);
        }

        return self::SUCCESS;
    }

    private function report(SyncResult $result): void
    {
        $this->info(sprintf(
            '%s: written=[%s] skipped=[%s]',
            $result->type->value,
            implode(', ', $result->written),
            implode(', ', $result->skipped),
        ));
    }
}
