<?php

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Model;

class TempTable extends Model
{

    /**
     * @var string
     */
    protected $table = 'temp_table';

    /**
     * @var array
     */
    protected $guarded = ['product_id'];
}
