<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Console;

use Illuminate\Console\Command;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\LocalPushUriException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;
use KaiHempel\ERecht24\Exceptions\TooManyPushClientsException;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;
use KaiHempel\ERecht24\Support\EnvFileWriter;
use KaiHempel\ERecht24\Support\PushUri;

final class RegisterPushClientCommand extends Command
{
    protected $signature = 'erecht24:register {--push-uri=} {--write-env}';

    protected $description = 'Register (or update) this environment as an eRecht24 push client.';

    public function handle(PushClientRegistrar $registrar, EnvFileWriter $envWriter): int
    {
        try {
            $client = $registrar->register($this->pushUriOption());
        } catch (LocalPushUriException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (TooManyPushClientsException $e) {
            $this->error($e->getMessage());
            $this->line('Existing clients:');

            foreach ($e->clients as $existing) {
                $this->line(sprintf('  - id=%s push_uri=%s', $existing->id, PushUri::redact($existing->pushUri)));
            }

            $this->line('Remove one with erecht24:unregister before retrying.');

            return self::FAILURE;
        } catch (MissingConfigurationException|InvalidConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Erecht24ApiException $e) {
            $this->error(sprintf('eRecht24 API request failed with status %d.', $e->status));

            return self::FAILURE;
        } catch (\UnexpectedValueException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Registered push client id=%d', $client->id));

        $secret = (string) $client->secret;
        $written = false;

        if ($this->option('write-env')) {
            $written = $envWriter->write(base_path('.env'), 'ERECHT24_PUSH_SECRET', $secret);
        }

        if ($written) {
            $this->info('ERECHT24_PUSH_SECRET written to .env.');
        } else {
            $this->line(sprintf('ERECHT24_PUSH_SECRET=%s', $client->secret));
        }

        return self::SUCCESS;
    }

    private function pushUriOption(): ?string
    {
        $option = $this->option('push-uri');

        return is_string($option) && trim($option) !== '' ? trim($option) : null;
    }
}
