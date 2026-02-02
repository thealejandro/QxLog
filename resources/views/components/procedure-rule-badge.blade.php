@php($b = $badge())
<flux:badge size="sm" :color="$b['color']">
    {{ $b['label'] }}
</flux:badge>