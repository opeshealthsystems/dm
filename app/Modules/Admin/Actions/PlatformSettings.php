<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Exceptions\AdminException;
use App\Modules\Admin\Models\Setting;
use App\Modules\Admin\Support\SettingsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Typed, cached key/value platform settings (whitelisted keys only). */
class PlatformSettings
{
    private const CACHE_KEY = 'platform.settings';

    public function __construct(private AuditLogger $audit) {}

    /** @return array<string,mixed> all settings with defaults applied */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        $out = [];
        foreach (SettingsRegistry::definitions() as $key => $def) {
            $out[$key] = array_key_exists($key, $stored)
                ? $this->cast($stored[$key], $def['type'])
                : $def['default'];
        }

        return $out;
    }

    public function get(string $key): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : throw new AdminException("Unknown setting [$key].", 422);
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function update(array $values): array
    {
        $defs = SettingsRegistry::definitions();
        $unknown = array_diff(array_keys($values), array_keys($defs));
        if ($unknown) {
            throw new AdminException('Unknown setting(s): ' . implode(', ', $unknown), 422);
        }

        DB::transaction(function () use ($values, $defs) {
            $before = $this->all();
            foreach ($values as $key => $value) {
                $type = $defs[$key]['type'];
                Setting::query()->updateOrCreate(['key' => $key], [
                    'value' => $value === null ? null : ($type === 'bool' ? ($value ? '1' : '0') : (string) $value),
                    'type' => $type,
                ]);
            }
            Cache::forget(self::CACHE_KEY);
            $after = $this->all();
            $changed = array_keys(array_filter($values, fn ($v, $k) => $before[$k] !== $after[$k], ARRAY_FILTER_USE_BOTH));
            $this->audit->record(
                'settings.updated', 'setting', null,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)),
            );
        });

        return $this->all();
    }

    private function cast(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $raw,
            'bool' => $raw === '1',
            default => $raw,
        };
    }
}
