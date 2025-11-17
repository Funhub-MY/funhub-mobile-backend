<?php

namespace Konnco\FilamentImport\Concerns;

/**
 * Compatibility stub for Konnco\FilamentImport\Concerns\HasActionMutation
 * This is a temporary compatibility layer until the package supports Filament v4
 */
trait HasActionMutation
{
    protected ?\Closure $mutation = null;

    public function mutation(\Closure $mutation): static
    {
        $this->mutation = $mutation;
        return $this;
    }

    public function getMutation(): ?\Closure
    {
        return $this->mutation;
    }
}

