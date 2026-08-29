@php
    $containerClass = $containerClass ?? 'd-flex flex-wrap gap-2';
@endphp

<div class="{{ $containerClass }}">
    @foreach($items as $item)
        @php
            $isDisabled = (bool) ($item['disabled'] ?? false);
            $isActive = (bool) ($item['active'] ?? false);
            $linkClasses = trim(collect([
                'btn',
                $isActive ? 'btn-primary text-white border-0' : 'btn-light border',
                'shadow-sm rounded-3 px-4 py-2 d-inline-flex align-items-center payment-toolbar-link',
                $item['class'] ?? null,
            ])->filter()->implode(' '));
            $iconPosition = $item['icon_position'] ?? 'left';
        @endphp

        @if($isDisabled)
            <span class="{{ $linkClasses }}" aria-disabled="true">
                @if(!empty($item['icon']) && $iconPosition === 'left')
                    <i class="{{ $item['icon'] }} me-2 {{ $isActive ? 'text-white' : 'text-primary' }}"></i>
                @endif
                <span class="{{ $iconPosition === 'right' ? 'text-start' : '' }}">
                    <small class="d-block {{ $isActive ? 'text-white-50' : 'text-muted' }}">{{ $item['label'] ?? '' }}</small>
                    <strong>{{ $item['value'] ?? '' }}</strong>
                </span>
                @if(!empty($item['icon']) && $iconPosition === 'right')
                    <i class="{{ $item['icon'] }} ms-2 {{ $isActive ? 'text-white' : 'text-primary' }}"></i>
                @endif
            </span>
        @else
            <a href="{{ $item['url'] ?? 'javascript:void(0);' }}"
               class="{{ $linkClasses }}"
               @if(!empty($item['target'])) target="{{ $item['target'] }}" @endif
               @if(!empty($item['rel'])) rel="{{ $item['rel'] }}" @endif>
                @if(!empty($item['icon']) && $iconPosition === 'left')
                    <i class="{{ $item['icon'] }} me-2 {{ $isActive ? 'text-white' : 'text-primary' }}"></i>
                @endif
                <span class="{{ $iconPosition === 'right' ? 'text-start' : '' }}">
                    <small class="d-block {{ $isActive ? 'text-white-50' : 'text-muted' }}">{{ $item['label'] ?? '' }}</small>
                    <strong>{{ $item['value'] ?? '' }}</strong>
                </span>
                @if(!empty($item['icon']) && $iconPosition === 'right')
                    <i class="{{ $item['icon'] }} ms-2 {{ $isActive ? 'text-white' : 'text-primary' }}"></i>
                @endif
            </a>
        @endif
    @endforeach
</div>
