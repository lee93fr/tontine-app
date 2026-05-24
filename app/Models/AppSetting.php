<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_encrypted'];

    protected $casts = [
        'is_encrypted' => 'bool',
    ];

    public static function getGroup(string $group): array
    {
        if (! static::tableExists()) {
            return [];
        }

        return static::where('group', $group)->get()->mapWithKeys(function ($s) {
            return [$s->key => $s->decryptedValue()];
        })->all();
    }

    public static function tableExists(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('app_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function setGroup(string $group, array $values, array $encryptedKeys = []): void
    {
        if (! static::tableExists()) {
            throw new \RuntimeException("La table app_settings n'existe pas. Lancez : php artisan migrate");
        }

        foreach ($values as $key => $value) {
            $isEncrypted = in_array($key, $encryptedKeys, true);
            $storedValue = $value;

            if ($isEncrypted && $value !== null && $value !== '') {
                $storedValue = Crypt::encryptString($value);
            }

            static::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $storedValue, 'is_encrypted' => $isEncrypted]
            );
        }
    }

    public function decryptedValue(): ?string
    {
        if ($this->value === null || $this->value === '') {
            return $this->value;
        }

        if ($this->is_encrypted) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $this->value;
    }

    public static function get(string $group, string $key, $default = null)
    {
        $row = static::where('group', $group)->where('key', $key)->first();
        return $row ? $row->decryptedValue() : $default;
    }
}
