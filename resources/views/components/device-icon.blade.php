@props(['type' => 'desktop'])

@switch($type)
    @case('phone')
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" {{ $attributes }}>
            <rect x="7" y="2.5" width="10" height="19" rx="2" />
            <line x1="11" y1="18.5" x2="13" y2="18.5" />
        </svg>
        @break
    @case('tablet')
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" {{ $attributes }}>
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <line x1="11" y1="18.5" x2="13" y2="18.5" />
        </svg>
        @break
    @default
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" {{ $attributes }}>
            <rect x="3" y="4" width="18" height="12" rx="1.5" />
            <line x1="9" y1="20" x2="15" y2="20" />
            <line x1="12" y1="16" x2="12" y2="20" />
        </svg>
@endswitch
