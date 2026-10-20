<?php

namespace App\Enums;

enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Select => 'Single Select',
            self::MultiSelect => 'Multi Select',
            self::Boolean => 'Yes / No (Boolean)',
        };
    }

    public function supportsOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }
}
