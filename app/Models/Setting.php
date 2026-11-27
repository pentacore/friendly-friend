<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppSetting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One stored owner setting. Read it through `App\Access\AppSettings`, which
 * falls back to the default and clamps to the setting's bounds.
 *
 * @property AppSetting $key
 * @property int $value
 */
#[Table('app_settings', key: 'key', keyType: 'string', incrementing: false)]
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => AppSetting::class,
            'value' => 'integer',
        ];
    }
}
