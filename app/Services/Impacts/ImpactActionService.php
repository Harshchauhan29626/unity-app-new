<?php

namespace App\Services\Impacts;

use App\Models\ImpactAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImpactActionService
{
    public function availableActions(): array
    {
        if (! Schema::hasTable('impact_actions')) {
            return array_values((array) config('impact.actions', []));
        }

        return ImpactAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->values()
            ->all();
    }

    public function listForAdmin(): Collection
    {
        if (! Schema::hasTable('impact_actions')) {
            return collect($this->availableActions())->map(fn (string $name) => (object) [
                'name' => $name,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        return ImpactAction::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'sort_order', 'created_at']);
    }

    public function createAction(string $name): ImpactAction
    {
        if (! Schema::hasTable('impact_actions')) {
            throw new \RuntimeException('impact_actions table is not available.');
        }

        $normalized = trim($name);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Action name is required.');
        }

        $exists = ImpactAction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($normalized)])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('This impact action already exists.');
        }

        return ImpactAction::query()->create([
            'name' => $normalized,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
