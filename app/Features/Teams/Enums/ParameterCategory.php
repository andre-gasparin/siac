<?php

namespace App\Features\Teams\Enums;

enum ParameterCategory: string
{
    case Online = 'ON';
    case Lab = 'LAB';
    case Operation = 'OP';
    case Calculated = 'CALC';
    case Portable = 'PORT';

    /**
     * Get the display label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Lab => 'Laboratório',
            self::Operation => 'Operação',
            self::Calculated => 'Calculado',
            self::Portable => 'Portátil',
        };
    }

    /**
     * Get all parameter categories as selectable options.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases(),
        );
    }
}
