<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StorageModel;
use App\Models\DeliveryModel;

class DeliveryController extends ResourceController
{
    use ResponseTrait;

    protected $format = 'json';

    // 1. Generate DO Number (DO + Tanggal + Urutan)
    public function generateNumber()
    {
        header('Access-Control-Allow-Origin: *');
        date_default_timezone_set('Asia/Jakarta');
        
        $model = new DeliveryModel();
        $dateCode = date('dmy'); 
        $prefix = "DO" . $dateCode; 

        $lastData = $model->like('do_number', $prefix, 'after')
                          ->orderBy('id', 'DESC')->first();

        $nextSequence = $lastData ? intval(substr($lastData['do_number'], -4)) + 1 : 1;
        $newNumber = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

        return $this->respond(['doNumber' => $newNumber]);
    }

    // 2. CREATE DELIVERY (Kurangi Stok)
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $storageModel = new StorageModel();
        $deliveryModel = new DeliveryModel();
        $json = $this->request->getJSON();

        if (!$json) return $this->fail('Invalid Data', 400);

        // A. Cek Stok di Gudang
        $storageItem = $storageModel->find($json->storage_id);

        if (!$storageItem) {
            return $this->failNotFound('Barang tidak ditemukan di gudang.');
        }

        $currentQty = (int)$storageItem['quantity'];
        $deliveryQty = (int)$json->qty_delivery;

        // B. Validasi Stok Cukup?
        if ($currentQty < $deliveryQty) {
            return $this->fail("Stok tidak cukup! Sisa stok hanya: $currentQty", 400);
        }

        // C. Proses Transaksi (Update Stok & Simpan DO)
        $db = \Config\Database::connect();
        $db->transStart();

        // 1. Kurangi Stok
        $newQty = $currentQty - $deliveryQty;
        $storageModel->update($storageItem['id'], ['quantity' => $newQty]);

        // 2. Simpan Bukti Delivery
        $deliveryModel->insert([
            'do_number'     => $json->do_number,
            'customer_name' => $json->customer_name,
            'storage_id'    => $json->storage_id,
            'product_name'  => $storageItem['name'],
            'qty_delivery'  => $deliveryQty,
            'notes'         => $json->notes
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Gagal memproses delivery.');
        }

        return $this->respondCreated(['message' => 'Delivery berhasil! Stok telah dikurangi.']);

        $deliveryModel->insert([
        'do_number'     => $json->do_number,
        'customer_name' => $json->customer_name, // Ini dikirim dari frontend (yg sudah auto-fill)
        'destination'   => $json->destination,   // <--- Simpan Tujuan
        'storage_id'    => $json->storage_id,
        'product_name'  => $storageItem['name'],
        'qty_delivery'  => $deliveryQty,
        'notes'         => $json->notes
    ]);
    }
}