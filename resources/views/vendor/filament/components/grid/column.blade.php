@props([
    'default' => null,
    'sm' => null,
    'md' => null,
    'lg' => null,
    'xl' => null,
    'twoXl' => null,
    'defaultStart' => null,
    'smStart' => null,
    'mdStart' => null,
    'lgStart' => null,
    'xlStart' => null,
    'twoXlStart' => null,
])

@php
    $columnAttributes = (new \Illuminate\View\ComponentAttributeBag())
        ->gridColumn(
            [
                'default' => $default,
                'sm' => $sm,
                'md' => $md,
                'lg' => $lg,
                'xl' => $xl,
                '2xl' => $twoXl,
            ],
            [
                'default' => $defaultStart,
                'sm' => $smStart,
                'md' => $mdStart,
                'lg' => $lgStart,
                'xl' => $xlStart,
                '2xl' => $twoXlStart,
            ]
        );
@endphp

<div {{ $columnAttributes->merge($attributes) }}>
    {{ $slot }}
</div>

