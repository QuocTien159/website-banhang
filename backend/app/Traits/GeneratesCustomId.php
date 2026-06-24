<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait GeneratesCustomId
{
    /**
     * Generate a custom ID like KH000001, SP000002, etc.
     * $prefix: e.g. 'KH', 'SP', 'DM'
     * $table: the table name
     * $pkColumn: the PK column name
     */
    public static function generateId(string $prefix, string $table, string $pkColumn): string
    {
        $last = DB::table($table)
            ->where($pkColumn, 'like', $prefix . '%')
            ->orderBy($pkColumn, 'desc')
            ->value($pkColumn);

        if ($last) {
            $num = (int) substr($last, strlen($prefix));
            return $prefix . str_pad($num + 1, 6, '0', STR_PAD_LEFT);
        }

        return $prefix . '000001';
    }

    /**
     * Boot the trait — auto-generate PK on creating.
     * Subclass must define: static string $idPrefix, static string $pkColumn.
     */
    protected static function bootGeneratesCustomId(): void
    {
        static::creating(function ($model) {
            $pk = $model->getKeyName();
            if (empty($model->$pk)) {
                $model->$pk = static::generateId(
                    static::$idPrefix,
                    $model->getTable(),
                    $pk
                );
            }
        });
    }
}
