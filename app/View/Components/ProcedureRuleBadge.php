<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProcedureRuleBadge extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public bool $videosurgery = false,
        public ?string $rule = null,
    ) {
    }

    public function badge(): array
    {
        if ($this->videosurgery) {
            return [
                'label' => __('Video'),
                'color' => 'blue',
                'tooltip' => __('Video surgery procedure'),
            ];
        }

        return match ($this->rule) {
            'default_rate' => [
                'label' => __('Standard'),
                'color' => 'indigo',
                'tooltip' => __('Standard'),
            ],
            'night_rate' => [
                'label' => __('Night'),
                'color' => 'violet',
                'tooltip' => __('Night'),
            ],
            'long_case_rate' => [
                'label' => __('Long'),
                'color' => 'yellow',
                'tooltip' => __('Long'),
            ],
            default => [
                'label' => __('Standard'),
                'color' => 'indigo',
                'tooltip' => __('Standard'),
            ],
        };
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.procedure-rule-badge');
    }
}
