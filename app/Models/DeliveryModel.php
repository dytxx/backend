<?php namespace App\Models;

use CodeIgniter\Model;

class DeliveryModel extends Model
{
    protected $table            = 'delivery_orders';
    protected $primaryKey       = 'id';
    
    // Pastikan 'destination' ada di sini!
    protected $allowedFields    = [
        'do_number', 
        'customer_name', 
        'destination',  // <--- TAMBAHKAN INI
        'storage_id', 
        'product_name', 
        'qty_delivery', 
        'notes'
    ];
    
    protected $useTimestamps    = false;
}