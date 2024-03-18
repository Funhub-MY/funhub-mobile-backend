<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\Page;
use App\Filament\Resources\UserResource;

class ViewUsers extends Page
{
    protected $user;

    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.view-users';
}
