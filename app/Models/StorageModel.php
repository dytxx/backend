<?php namespace App\Models;

use CodeIgniter\Model;

class StorageModel extends Model
{
    protected $table            = 'storage_items';
    protected $primaryKey       = 'id';
    
    // Aktifkan return array (bukan object) agar lebih mudah jadi JSON
    protected $returnType       = 'array'; 
    
    // Fitur otomatis isi tanggal
    protected $useTimestamps    = true; 
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // KOLOM YANG BOLEH DIISI (PENTING!)
    protected $allowedFields    = [
        'sku', 
        'name', 
        'category', 
        'quantity', 
        'location', 
        'min_stock'
    ];
}