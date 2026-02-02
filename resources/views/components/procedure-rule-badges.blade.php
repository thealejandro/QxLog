<div class="flex items-center justify-end gap-1 whitespace-nowrap">
    @foreach($badges() as $b)
        <flux:badge size="sm" :color="$b['color']">
            {{ $b['label'] }}
        </flux:badge>
    @endforeach
</div>