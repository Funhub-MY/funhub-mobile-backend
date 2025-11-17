<?php

namespace Konnco\FilamentImport\Actions;

use Closure;

/**
 * Compatibility stub for Konnco\FilamentImport\Actions\ImportField
 * This is a temporary compatibility layer until the package supports Filament v4
 */
class ImportField
{
    protected string $name;
    protected ?string $label = null;
    protected bool $required = false;
    protected ?string $helperText = null;
    protected ?Closure $mutateBeforeCreate = null;

    public static function make(string $name): static
    {
        $static = new static();
        $static->name = $name;
        return $static;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function helperText(string $text): static
    {
        $this->helperText = $text;
        return $this;
    }

    public function mutateBeforeCreate(Closure $callback): static
    {
        $this->mutateBeforeCreate = $callback;
        return $this;
    }

    public function toFormComponent(): \Filament\Forms\Components\TextInput
    {
        $component = \Filament\Forms\Components\TextInput::make($this->name)
            ->label($this->label ?? ucfirst(str_replace('_', ' ', $this->name)));

        if ($this->required) {
            $component->required();
        }

        if ($this->helperText) {
            $component->helperText($this->helperText);
        }

        return $component;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMutateBeforeCreate(): ?Closure
    {
        return $this->mutateBeforeCreate;
    }
}

