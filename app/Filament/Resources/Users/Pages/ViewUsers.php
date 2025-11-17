<?php

namespace App\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\Page;
use App\Filament\Resources\Users\UserResource;

class ViewUsers extends Page
{
    protected $user;

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.user-resource.pages.view-users';
}
