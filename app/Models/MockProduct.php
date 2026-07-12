<?php

namespace App\Models;

use Database\Factories\MockProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockProduct extends Model
{
    /** @use HasFactory<MockProductFactory> */
    use HasFactory;
}
