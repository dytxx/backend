<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StorageModel;

class StorageController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = 'App\Models\StorageModel';
    protected $format    = 'json';

    // 1. GET: Ambil Semua Data (Tampil di Tabel & Visual Map)
    public function index()
    {
        header('Access-Control-Allow-Origin: *');
        // Ambil semua data termasuk yang qty 0 (untuk history), tapi frontend yang akan filter visualnya
        $data = $this->model->orderBy('updated_at', 'DESC')->findAll();
        return $this->respond($data);
    }

    // 2. GET: Auto-Index (Rekomendasi Slot)
    public function getRecommendation()
    {
        header('Access-Control-Allow-Origin: *');
        $productName = $this->request->getGet('product_name');

        if (!$productName) return $this->fail('Product Name required', 400);

        // A. Cek apakah barang ini sudah ada DAN Qty > 0? (Gabung Stok)
        $existingStorage = $this->model->where('name', $productName)
                                       ->where('location !=', '') 
                                       ->where('quantity >', 0) // Hanya rekomendasikan jika stok masih ada
                                       ->first();

        if ($existingStorage) {
            return $this->respond([
                'status' => 'exist',
                'location' => $existingStorage['location'],
                'message' => "Barang sudah ada di Rak {$existingStorage['location']}. Disarankan digabung."
            ]);
        }

        // B. Jika belum ada, Cari Slot Kosong Pertama
        $rows = ['A', 'B', 'C', 'D', 'E'];
        $levels = [1, 2, 3, 4];
        
        // Ambil slot yang BENAR-BENAR terisi (Qty > 0)
        $occupied = $this->model->where('location !=', '')
                                ->where('quantity >', 0) // Slot dengan qty 0 dianggap kosong
                                ->findColumn('location') ?? [];

        foreach ($rows as $row) {
            foreach ($levels as $level) {
                $slot = "$row-0$level";
                // Jika slot tidak ada di daftar occupied, berarti boleh dipakai
                if (!in_array($slot, $occupied)) {
                    return $this->respond([
                        'status' => 'new',
                        'location' => $slot,
                        'message' => "Slot kosong ditemukan di $slot."
                    ]);
                }
            }
        }

        return $this->respond([
            'status' => 'full', 
            'location' => '', 
            'message' => 'Gudang Penuh! Tidak ada slot kosong tersisa.'
        ]);
    }

    // 3. POST: Create / Putaway (Logic Overwrite)
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $json = $this->request->getJSON();
        if (!$json) return $this->fail('Invalid JSON', 400);

        // Cek data lama di lokasi ini
        $existingItem = $this->model->where('location', $json->location)->first();
        
        // LOGIC UTAMA: Cek apakah slot dianggap "Terisi"?
        // Hanya terisi jika ada datanya DAN Quantity > 0
        if ($existingItem && $existingItem['quantity'] > 0) {
            
            // SKENARIO 1: RESTOCK (Barang Sama)
            if ($existingItem['name'] === $json->productName) {
                $newQty = $existingItem['quantity'] + (int)$json->quantity;
                $this->model->update($existingItem['id'], ['quantity' => $newQty]);
                $message = "Stok berhasil ditambahkan (Restock) ke FG ID: " . $existingItem['fg_number'];
            } 
            // SKENARIO 2: CONFLICT (Barang Beda & Masih Ada Stok)
            else {
                return $this->fail("Gagal! Slot {$json->location} masih berisi barang lain: {$existingItem['name']}", 409);
            }

        } else {
            // SKENARIO 3: SLOT KOSONG / STOK 0 -> TIMPA (OVERWRITE)
            
            // Generate ID Baru
            $newFG = $this->generateFGNumber();

            $dataToSave = [
                'fg_number' => $newFG,
                'sku'       => $json->qcNumber, 
                'name'      => $json->productName,
                'quantity'  => $json->quantity,
                'location'  => $json->location,
                'category'  => 'Finished Goods'
            ];

            if ($existingItem) {
                // Update data lama (yang qty 0) dengan data baru
                $this->model->update($existingItem['id'], $dataToSave);
            } else {
                // Insert data baru murni
                $this->model->insert($dataToSave);
            }

            $message = "Barang berhasil disimpan di {$json->location} dengan ID: $newFG";
        }

        return $this->respondCreated(['message' => $message]);
    }

    // Helper: Generate ID
    private function generateFGNumber()
    {
        date_default_timezone_set('Asia/Jakarta'); 
        $dateCode = date('dmy'); 
        $prefix = "FG" . $dateCode; 

        $lastData = $this->model->like('fg_number', $prefix, 'after')->orderBy('id', 'DESC')->first();

        if ($lastData) {
            $lastSequence = intval(substr($lastData['fg_number'], -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }
        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    // ... (Fungsi update, delete, options standar bisa tetap ada di bawah sini) ...
    public function getPendingQC()
    {
        header('Access-Control-Allow-Origin: *');
        $db = \Config\Database::connect();
        $query = $db->query("SELECT id, qc_number, product_code, checked_quantity FROM qc_reports WHERE result = 'OK' ORDER BY id DESC");
        return $this->respond($query->getResultArray());
    }
}