<?php

namespace Filament\Forms\Components\Contracts;

use Filament\Actions\Action;

/**
 * Compatibility interface for Filament Google Maps package
 * In Filament v4, this interface moved to Filament\Schemas\Components\Contracts\HasAffixActions
 * This is a compatibility shim that matches the v4 interface
 */
interface HasAffixActions
{
    /**
     * @return array<Action>
     */
    public function getPrefixActions(): array;

    /**
     * @return array<Action>
     */
    public function getSuffixActions(): array;
}

