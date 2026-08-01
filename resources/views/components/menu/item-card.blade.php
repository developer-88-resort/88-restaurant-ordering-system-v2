{{--
    Shared customer-facing item card — used by customer/menu.blade.php,
    welcome-menu-preview.blade.php, and welcome-takeout.blade.php, which
    previously each duplicated this markup identically. Assumes the
    enclosing Alpine x-data scope defines `itemAvailability` (an object
    mapping menu_item_id => availability_status string) and, when
    $clickable, `isOrderable(id)` and `openAddConfirm({id,name,price,
    imageUrl,hasVariants,variants})` — both plain and variant items open
    the same unified add-to-cart confirmation modal; the modal itself
    handles variant selection, quantity, and the unavailable state.
--}}
@props(['item', 'clickable' => true])

@php
    $imageUrl = $item->primaryImageUrl();
    $hasVariants = $item->hasVariants();
    $variantsPayload = $hasVariants ? $item->variants->map(fn ($variant) => [
        'id' => $variant->id,
        'name' => $variant->name,
        'description' => $variant->description,
        'price' => (float) $variant->price,
        'imageUrl' => $variant->imageUrl(),
        'isDefault' => (bool) $variant->is_default,
    ]) : [];
    $confirmPayload = [
        'id' => $item->id,
        'name' => $item->name,
        'description' => $item->description,
        'imageUrl' => $imageUrl,
        'price' => (float) $item->price,
        'hasVariants' => $hasVariants,
        'variants' => $variantsPayload,
    ];
@endphp

@if ($clickable)
    <button type="button"
            @click="openAddConfirm({{ Js::from($confirmPayload) }})"
            :class="isOrderable({{ $item->id }}) ? 'hover:border-[#8A3330] hover:shadow-md' : 'opacity-60'"
            class="w-full flex items-center gap-3 text-left bg-white border border-[#E5DDD0] rounded-xl p-3 shadow-sm transition">
        <x-menu.item-card-body :item="$item" :image-url="$imageUrl" />
    </button>
@else
    <div class="w-full flex items-center gap-3 bg-white border border-[#E5DDD0] rounded-xl p-3 shadow-sm transition"
         :class="isOrderable({{ $item->id }}) ? '' : 'opacity-60'">
        <x-menu.item-card-body :item="$item" :image-url="$imageUrl" />
    </div>
@endif
