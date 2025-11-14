<x-filament-panels::page class="fi-dashboard-page">
    @if (method_exists($this, 'filtersForm'))
        {{ $this->filtersForm }}
    @endif

    {{-- Widgets disabled - empty array passed to prevent any widgets from displaying --}}
    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="[]"
        :widgets="[]"
    />
</x-filament-panels::page>

