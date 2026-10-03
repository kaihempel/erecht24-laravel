<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Console;

use Illuminate\Console\Command;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;
use KaiHempel\ERecht24\Status\StatusInspector;
use KaiHempel\ERecht24\Status\StatusReport;
use KaiHempel\ERecht24\Support\PushUri;

final class StatusCommand extends Command
{
    protected $signature = 'erecht24:status {--test-push}';

    protected $description = 'Show the eRecht24 integration health: configuration, push clients, and stored texts.';

    public function handle(StatusInspector $inspector, PushClientRegistrar $registrar): int
    {
        $report = $inspector->inspect();

        $this->line('Configuration:');

        foreach ($report->configuration as $key => $present) {
            $this->line(sprintf('  %s: %s', $key, $present ? 'set' : 'missing'));
        }

        $this->line('Languages: '.($report->languages === [] ? 'none (invalid configuration)' : implode(', ', $report->languages)));

        $this->line('Push clients:');

        if ($report->clientsError !== null) {
            $this->warn('  Could not list clients: '.$report->clientsError);
        } elseif ($report->clients === []) {
            $this->line('  none registered');
        }

        foreach ($report->clients as $client) {
            $this->line(sprintf('  id=%s push_uri=%s', $client->id, PushUri::redact($client->pushUri)));
        }

        $this->line('Stored legal texts:');

        foreach ($report->texts as $text) {
            $this->line(sprintf(
                '  %s [%s]: %s',
                $text->type->value,
                $text->language,
                $text->stored ? 'stored, fetched at '.($text->fetchedAt?->toIso8601String() ?? 'unknown') : 'not stored',
            ));
        }

        if ($this->option('test-push')) {
            $this->reportTestPush($inspector, $registrar, $report);
        }

        return self::SUCCESS;
    }

    private function reportTestPush(StatusInspector $inspector, PushClientRegistrar $registrar, StatusReport $report): void
    {
        $result = $inspector->testPush($report, $registrar->currentPushUri());

        if (! $result->matched) {
            $this->warn('Test push skipped: no matching client is registered for the current push URI.');

            return;
        }

        if ($result->success) {
            $this->info(sprintf('Test push to client id=%d succeeded.', $result->clientId));

            return;
        }

        $this->error(sprintf('Test push to client id=%d failed: %s', $result->clientId, $result->errorMessage));
    }
}
