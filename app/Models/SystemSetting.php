<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use RecordsSystemLog;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'updated_by',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}