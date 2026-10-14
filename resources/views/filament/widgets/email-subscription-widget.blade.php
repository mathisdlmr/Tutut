@php($accepts = auth()->user()->accepts_emails)
<x-filament-widgets::widget>
    <x-filament::section :heading="__('resources.widgets.email_subscription.heading')" icon="heroicon-o-envelope">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm">
                {{ $accepts ? __('resources.widgets.email_subscription.subscribed') : __('resources.widgets.email_subscription.unsubscribed') }}
            </p>
            <x-filament::button wire:click="toggle" :color="$accepts ? 'danger' : 'primary'">
                {{ $accepts ? __('resources.widgets.email_subscription.unsubscribe') : __('resources.widgets.email_subscription.subscribe') }}
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
