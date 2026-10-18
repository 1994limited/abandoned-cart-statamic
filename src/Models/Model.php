<?php

namespace NineteenNinetyFour\AbandonedCart\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class Model extends Eloquent
{
    public function getConnectionName(): ?string
    {
        return config('abandoned-cart.database.connection') ?? parent::getConnectionName();
    }
}
