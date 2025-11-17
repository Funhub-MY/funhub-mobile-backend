<?php

namespace Konnco\FilamentImport\Concerns;

/**
 * Compatibility stub for Konnco\FilamentImport\Concerns\HasActionUniqueField
 * This is a temporary compatibility layer until the package supports Filament v4
 */
trait HasActionUniqueField
{
    protected ?string $uniqueField = null;

    public function uniqueField(string $field): static
    {
        $this->uniqueField = $field;
        return $this;
    }

    public function getUniqueField(): ?string
    {
        return $this->uniqueField;
    }
}

