# Quickstart: eRecht24 Artisan Commands

## Register this environment

```bash
php artisan erecht24:register
# Computed push URI from config('app.url') + erecht24.push_path

php artisan erecht24:register --push-uri=https://staging.example.com/api/erecht24/push

php artisan erecht24:register --write-env
# Writes ERECHT24_PUSH_SECRET=... into .env (only if writable)
```

Running it again with the same push URI updates the existing client instead of creating a duplicate. With three unrelated clients already registered, it aborts and lists them instead of creating a fourth.

## Check integration health

```bash
php artisan erecht24:status
php artisan erecht24:status --test-push
```

Shows configuration presence (booleans only), configured languages, registered push clients (no secrets), stored legal texts with their last-fetched timestamps, and — with `--test-push` — whether a ping to the matching client succeeded.

## Remove a registration

```bash
php artisan erecht24:unregister             # deletes the client matching the current push URI
php artisan erecht24:unregister 42          # deletes client ID 42
php artisan erecht24:unregister --force     # skip the confirmation prompt (scripted/CI use)
```

## Manual resync (pre-existing command, unchanged)

```bash
php artisan erecht24:sync            # all types
php artisan erecht24:sync imprint    # one type
```

## Use the registrar programmatically

```php
use KaiHempel\ERecht24\Registration\PushClientRegistrar;

$client = app(PushClientRegistrar::class)->register();
$client->id;     // int
$client->secret; // issued once — persist it yourself if not using --write-env
```

## Verifying the acceptance criteria locally

1. `Http::fake()` an empty `GET /clients` response, run `erecht24:register`, assert `POST /clients` was called and the output contains `ERECHT24_PUSH_SECRET=`.
2. Fake `GET /clients` returning one client whose `push_uri` matches the computed URI; run `erecht24:register` again; assert `PUT /clients/{id}` was called, not `POST /clients`.
3. Fake `GET /clients` returning three clients with no matching `push_uri`; run `erecht24:register`; assert exit code is non-zero and no `POST`/`PUT` call was made.
4. Run `erecht24:register` with `config(['app.url' => 'http://localhost'])` and no `--push-uri`; assert it fails before any HTTP call.
5. Run `erecht24:register --write-env` against a temp `.env` fixture file; assert only the `ERECHT24_PUSH_SECRET` line changed.
6. Run `erecht24:unregister 42` and answer "no" at the confirmation prompt (`$this->confirm` under test); assert no `DELETE` call was made and exit code is non-zero. Repeat with `--force` and assert the `DELETE` call happens with no prompt.
7. Fake `GET /clients` and legal text storage fixtures; run `erecht24:status`; assert output never contains the configured API key/plugin key/push secret values, only presence labels.
8. Run `erecht24:status --test-push` with no matching client in the faked `GET /clients` response; assert it reports "no matching client" and still exits `0`.
