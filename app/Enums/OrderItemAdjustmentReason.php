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
            self::Other => __('Other'),
        };
    }
}
