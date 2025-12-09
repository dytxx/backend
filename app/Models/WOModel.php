<?php

namespace App\Models;

use CodeIgniter\Model;

class WOModel extends Model
{
    protected $table            = 'work_orders';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['wo_number', 'customer', 'project_name', 'part_number', 'quantity', 'remarks'];
}