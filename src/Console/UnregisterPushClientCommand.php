<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Console;

use Illuminate\Console\Command;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;
use KaiHempel\ERecht24\Exceptions\PushClientNotFoundException;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;
use KaiHempel\ERecht24\Support\PushUri;

final class UnregisterPushClientCommand extends Command
{
    protected $signature = 'erecht24:unregister {client-id? : Push client ID; defaults to the client matching the current push URI} {--force : Skip the confirmation prompt}';

    protected $description = 'Remove a push client registration from eRecht24.';

    public function handle(PushClientRegistrar $registrar): int
    {
        $rawId = $this->argument('client-id');
        $clientId = null;

        if ($rawId !== null) {
            if (! is_scalar($rawId) || filter_var($rawId, FILTER_VALIDATE_INT) === false) {
                $this->error('The client ID must be an integer.');

                return self::FAILURE;
            }

            $clientId = (int) $rawId;
        }

        $target = $clientId !== null
            ? sprintf('push client id=%d', $clientId)
            : 'the push client matching the current push URI';

        if (! $this->option('force') && ! $this->confirm(sprintf('Really remove %s?', $target))) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        try {
            $removed = $registrar->unregister($clientId);
        } catch (PushClientNotFoundException|MissingConfigurationException|InvalidConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Erecht24ApiException $e) {
            $this->error(sprintf('eRecht24 API request failed with status %d.', $e->status));

            return self::FAILURE;
        }

        $this->info(sprintf('Removed push client id=%d (%s).', $removed->id, PushUri::redact($removed->pushUri)));

        return self::SUCCESS;
    }
}
