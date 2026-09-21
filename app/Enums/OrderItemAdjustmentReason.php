<?php

namespace App\Enums;

enum OrderItemAdjustmentReason: string
{
    case FoodContamination = 'food_contamination';
    case WrongItemServed = 'wrong_item_served';
    case CustomerComplaint = 'customer_complaint';
    case KitchenError = 'kitchen_error';
    case DuplicateOrder = 'duplicate_order';
    case OutOfStock = 'out_of_stock';
    case ComplimentaryReplacement = 'complimentary_replacement';
    case CustomerRequest = 'customer_request';
    case WrongOrder = 'wrong_order';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FoodContamination => __('Food contamination'),
            self::WrongItemServed => __('Wrong item served'),
            self::CustomerComplaint => __('Customer complaint'),
            self::KitchenError => __('Kitchen error'),
            self::DuplicateOrder => __('Duplicate order'),
            self::OutOfStock => __('Out of stock'),
            self::ComplimentaryReplacement => __('Complimentary replacement'),
            self::CustomerRequest => __('Customer request'),
            self::WrongOrder => __('Wrong order'),
            self::Other => __('Other'),
        };
    }

    /**
     * The short list the Kitchen Display offers — the reasons a cook can
     * actually vouch for at the pass. The fuller list stays on Order
     * Management, where a cashier is dealing with a complaint.
     *
     * @return array<int, self>
     */
    public static function kitchenPresets(): array
    {
        return [self::OutOfStock, self::CustomerRequest, self::WrongOrder, self::Other];
    }

    /**
     * "Other" says nothing on its own, so it is the one reason that must
     * carry a written explanation.
     */
    public function requiresNotes(): bool
    {
        return $this === self::Other;
    }
}
