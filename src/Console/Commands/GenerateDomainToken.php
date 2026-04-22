<?php

namespace Equidna\DomainTokenAuth\Console\Commands;

use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Equidna\DomainTokenAuth\DomainToken;
use Equidna\DomainTokenAuth\Support\ActionMatcher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class GenerateDomainToken extends Command
{
    protected $signature = 'domain-token:generate
        {domain : Configured domain}
        {owner_id : Owner model key}
        {--roles= : CSV roles list (example: admin,viewer)}
        {--actions= : CSV actions list (example: users.read,users.write)}
        {--data= : Optional JSON object payload (example: {"client":"mobile","scope":"sync"})}
        {--name= : Optional token name}
        {--starts-at= : Start datetime (Y-m-d H:i:s)}
        {--expires-at= : Expiration datetime (Y-m-d H:i:s)}';

    protected $description = 'Generate a secure domain token';

    public function handle(DomainToken $domainToken): int
    {
        $domain = (string) $this->argument('domain');
        $ownerId = (string) $this->argument('owner_id');
        $rolesCsv = (string) ($this->option('roles') ?? '');
        $actionsCsv = (string) ($this->option('actions') ?? '');

        $domains = (array) Config::get('domain-token-auth.domains', []);
        $domainConfig = $domains[$domain] ?? null;

        if (! is_array($domainConfig)) {
            $this->error(sprintf('Domain "%s" is not configured.', $domain));
            return self::FAILURE;
        }

        $modelClass = $domainConfig['model'] ?? null;
        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            $this->error(sprintf('Domain "%s" model is invalid.', $domain));
            return self::FAILURE;
        }

        /** @var Model|null $owner */
        $owner = $modelClass::query()->find($ownerId);
        if (! $owner) {
            $this->error(sprintf('Owner with id "%s" not found in model %s.', $ownerId, $modelClass));
            return self::FAILURE;
        }

        if (! $owner instanceof TokenOwner) {
            $this->error(sprintf('Model %s must implement %s.', $modelClass, TokenOwner::class));
            return self::FAILURE;
        }

        $roles = $rolesCsv !== ''
            ? ActionMatcher::parseCsv($rolesCsv)
            : [];

        $actions = $actionsCsv !== ''
            ? ActionMatcher::parseCsv($actionsCsv)
            : [];

        $startsAt = $this->parseDateOption('starts-at');
        $expiresAt = $this->parseDateOption('expires-at');
        $data = $this->parseDataOption();

        if ($startsAt === false || $expiresAt === false || $data === false) {
            return self::FAILURE;
        }

        $issued = $domainToken->issue(
            domain: $domain,
            owner: $owner,
            roles: $roles,
            actions: $actions,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            name: $this->option('name') ? (string) $this->option('name') : null,
            data: $data,
        );

        $this->info('Token generated successfully. Copy this value now, it will not be shown again:');
        $this->line($issued->plainTextToken);

        return self::SUCCESS;
    }

    private function parseDateOption(string $key): Carbon|false|null
    {
        $raw = $this->option($key);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            $this->error(sprintf('Invalid date format for --%s. Use Y-m-d H:i:s or ISO8601.', $key));
            return false;
        }
    }

    private function parseDataOption(): array|false
    {
        $raw = $this->option('data');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->error('Invalid JSON for --data. Expected an object, for example: {"client":"mobile"}.');
            return false;
        }

        if (! is_array($decoded)) {
            $this->error('Invalid --data payload. JSON must decode to an object.');
            return false;
        }

        return $decoded;
    }
}
