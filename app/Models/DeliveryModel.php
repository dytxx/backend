<?php namespace App\Models;

use CodeIgniter\Model;

class DeliveryModel extends Model
{
    protected $table            = 'delivery_orders';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'do_number', 'customer_name', 'destination', // <--- Tambah destination
        'storage_id', 'product_name', 'qty_delivery', 'notes'
    ];
    protected $useTimestamps    = false;
}