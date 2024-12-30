<?php

namespace App\Traits;
use Illuminate\Support\Str;
trait HasUUID
{
    //

protected static function bootHasUUID()
    {
        static::creating(function ($model) {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
  }
}
