@props([
    'default' => 1,
    'sm' => null,
    'md' => null,
    'lg' => null,
    'xl' => null,
    'twoXl' => null,
])

@php
    $gridAttributes = (new \Illuminate\View\ComponentAttributeBag())->grid([
        'default' => $default,
        'sm' => $sm,
        'md' => $md,
        'lg' => $lg,
        'xl' => $xl,
        '2xl' => $twoXl,
    ]);
@endphp

<div {{ $gridAttributes->merge($attributes) }}>
    {{ $slot }}
</div>

