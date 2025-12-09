<?php

namespace App\Models;

use CodeIgniter\Model;

class QCModel extends Model
{
    protected $table            = 'qc_reports';
    protected $primaryKey       = 'id';
    protected $allowedFields    =   ['qc_number', 
                                    'po_number', 
                                    'product_code', 
                                    'checked_quantity', 
                                    'result', 
                                    'checker_name', 
                                    'notes'];
}