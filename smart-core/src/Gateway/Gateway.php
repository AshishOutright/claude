<?php

namespace SmartCore\Gateway;

use InvalidArgumentException;
use SmartCore\Workflow\SelectPhase;
use SmartCore\Workflow\SelectStage;

/**
 * The Smart Gateway: one endpoint for all of an app's data. The React app (and any other client)
 * sends {"action": ..., ...} and gets {"ok": true, "data": ...} or {"ok": false, "error": ...}.
 *
 * Actions: ping, login, meta, list, get, create, update, delete, move, advance.
 */
class Gateway
{
    private readonly RecordValidator $validator;

    /** @var (callable(string): void)|null */
    private $actingAs;

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly DataStore $store,
        private readonly Authenticator $authenticator,
        private readonly TokenIssuer $tokens,
        private readonly SelectPhase $phases,
        private readonly SelectStage $stages,
        private readonly string $appName,
        ?callable $actingAs = null,
    ) {
        $this->validator = new RecordValidator($modules, $store);
        $this->actingAs = $actingAs;
    }

    /** @return array{status: int, body: array} */
    public function handle(array $request, ?string $bearerToken): array
    {
        try {
            $action = (string) ($request['action'] ?? '');

            if ($action === 'ping') {
                return $this->ok(['app' => $this->appName]);
            }

            if ($action === 'login') {
                return $this->ok($this->login($request));
            }

            $this->authenticate($bearerToken);

            return $this->ok(match ($action) {
                'meta' => $this->meta(),
                'list' => $this->list($request),
                'get' => $this->get($request),
                'create' => $this->create($request),
                'update' => $this->update($request),
                'delete' => $this->delete($request),
                'move' => $this->move($request),
                'advance' => $this->advance($request),
                default => throw new GatewayException('unknown_action', "Unknown action \"{$action}\".", 400),
            });
        } catch (GatewayException $e) {
            return ['status' => $e->status, 'body' => ['ok' => false, 'error' => array_filter([
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
                'fields' => $e->fields ?: null,
            ])]];
        }
    }

    private function login(array $request): array
    {
        $userId = $this->authenticator->attempt((string) ($request['username'] ?? ''), (string) ($request['password'] ?? ''));

        if ($userId === null) {
            throw new GatewayException('bad_login', 'That username and password do not match.', 401);
        }

        return ['token' => $this->tokens->issue($userId)];
    }

    private function authenticate(?string $bearerToken): void
    {
        $userId = $bearerToken ? $this->tokens->verify($bearerToken) : null;

        if ($userId === null) {
            throw new GatewayException('unauthenticated', 'Log in first.', 401);
        }

        if ($this->actingAs) {
            ($this->actingAs)($userId);
        }
    }

    private function meta(): array
    {
        $journey = [];

        foreach ($this->phases->all() as $phase) {
            $journey[] = [
                'key' => $phase->key(),
                'label' => $phase->label(),
                'stages' => array_map(fn ($stage) => [
                    'key' => $stage->key(),
                    'label' => $stage->label(),
                    'statuses' => array_map(fn ($s) => ['key' => $s->key, 'label' => $s->label, 'final' => $s->final], $stage->statuses()),
                ], $phase->stages()),
            ];
        }

        return [
            'app' => $this->appName,
            'modules' => array_map(fn (ModuleDefinition $m) => $m->toArray(), $this->modules->all()),
            'journey' => $journey,
        ];
    }

    private function list(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);
        $filters = [];

        foreach ((array) ($request['filters'] ?? []) as $key => $value) {
            $allowed = $module->field((string) $key) !== null || ($module->journey && in_array($key, ['journey_phase', 'journey_stage', 'journey_status'], true));

            if (! $allowed || ! is_scalar($value)) {
                throw new GatewayException('invalid_filter', "You cannot filter by \"{$key}\".", 400);
            }

            $filters[$key] = (string) $value;
        }

        $page = max(1, (int) ($request['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request['per_page'] ?? 25)));
        $result = $this->store->list($module, $filters, $page, $perPage);

        return $result + ['page' => $page, 'per_page' => $perPage];
    }

    private function get(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);

        return $this->findOrFail($module, (string) ($request['id'] ?? ''));
    }

    private function create(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);
        $data = $this->validator->validate($module, (array) ($request['data'] ?? []), true);

        if ($module->journey) {
            $phase = $this->phases->first();
            $stage = $phase->firstStage();
            $data += ['journey_phase' => $phase->key(), 'journey_stage' => $stage->key(), 'journey_status' => $stage->initialStatus()->key];
        }

        return $this->store->insert($module, $data);
    }

    private function update(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);
        $id = (string) ($request['id'] ?? '');
        $this->findOrFail($module, $id);

        return $this->store->update($module, $id, $this->validator->validate($module, (array) ($request['data'] ?? []), false));
    }

    private function delete(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);
        $id = (string) ($request['id'] ?? '');
        $this->findOrFail($module, $id);
        $this->store->delete($module, $id);

        return ['deleted' => $id];
    }

    /** Change the status inside the record's current stage. */
    private function move(array $request): array
    {
        [$module, $record] = $this->journeyRecord($request);
        $status = (string) ($request['status'] ?? '');

        try {
            $this->stages->assertCanMove($record['journey_phase'], $record['journey_stage'], $record['journey_status'], $status);
        } catch (InvalidArgumentException $e) {
            throw new GatewayException('journey', $e->getMessage(), 409);
        }

        return $this->store->update($module, $record['id'], ['journey_status' => $status]);
    }

    /** Move to the next stage (or the next phase) once the current status is final. */
    private function advance(array $request): array
    {
        [$module, $record] = $this->journeyRecord($request);
        $stage = $this->stages->find($record['journey_phase'], $record['journey_stage']);

        if (! $stage->status($record['journey_status'])->final) {
            throw new GatewayException('journey', 'Finish this stage (reach a final status) before moving on.', 409);
        }

        $next = $this->stages->next($record['journey_phase'], $record['journey_stage']);

        if ($next === null) {
            throw new GatewayException('journey', 'This record has finished the journey.', 409);
        }

        [$phase, $nextStage] = $next;

        return $this->store->update($module, $record['id'], [
            'journey_phase' => $phase->key(),
            'journey_stage' => $nextStage->key(),
            'journey_status' => $nextStage->initialStatus()->key,
        ]);
    }

    /** @return array{0: ModuleDefinition, 1: array} */
    private function journeyRecord(array $request): array
    {
        $module = $this->modules->get($request['module'] ?? null);

        if (! $module->journey) {
            throw new GatewayException('journey', "{$module->label} does not move through the journey.", 400);
        }

        return [$module, $this->findOrFail($module, (string) ($request['id'] ?? ''))];
    }

    private function findOrFail(ModuleDefinition $module, string $id): array
    {
        $record = $id === '' ? null : $this->store->find($module, $id);

        if ($record === null) {
            throw new GatewayException('not_found', "No {$module->label} record with this id.", 404);
        }

        return $record;
    }

    private function ok(array $data): array
    {
        return ['status' => 200, 'body' => ['ok' => true, 'data' => $data]];
    }
}
