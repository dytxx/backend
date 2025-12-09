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

    // Setup CORS
    public function options()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, DELETE')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setStatusCode(200);
    }

    // 1. GET: Ambil semua data slot yang terisi untuk ditampilkan di Visual Map
    public function index()
    {
        header('Access-Control-Allow-Origin: *');
        
        // Ambil semua data storage
        $data = $this->model->findAll();
        
        // Format agar mudah dibaca Frontend (key by location)
        // Contoh output: ['A-01' => {product: '...', qty: 100}, 'B-02' => ...]
        $mappedData = [];
        foreach ($data as $item) {
            if (!empty($item['location'])) {
                $mappedData[$item['location']] = $item;
            }
        }

        return $this->respond($mappedData);
    }

    public function getPendingQC()
    {
        header('Access-Control-Allow-Origin: *');
        
        $db = \Config\Database::connect();
        
        // Ambil data QC yang result-nya OK
        // Opsional: Bisa ditambahkan filter 'WHERE NOT EXISTS' agar QC yang sudah masuk storage tidak muncul lagi
        $query = $db->query("
            SELECT id, qc_number, product_code, checked_quantity 
            FROM qc_reports 
            WHERE result = 'OK' 
            ORDER BY id DESC
        ");

        return $this->respond($query->getResultArray());
    }
    

    // 3. POST: Simpan (Submit)
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $json = $this->request->getJSON();
        if (!$json) return $this->fail('Invalid JSON', 400);

        // Cek apakah lokasi sudah terisi?
        $existingItem = $this->model->where('location', $json->location)->first();
        
        if ($existingItem) {
            // KONDISI 1: BARANG SUDAH ADA (RESTOCK/GABUNG)
            // Validasi: Pastikan barangnya sama
            if ($existingItem['name'] === $json->productName) {
                $newQty = $existingItem['quantity'] + (int)$json->quantity;
                $this->model->update($existingItem['id'], ['quantity' => $newQty]);
                
                $message = "Stok berhasil ditambahkan ke FG ID: " . $existingItem['fg_number'];
            } else {
                return $this->fail("Slot {$json->location} sudah terisi barang lain!", 409);
            }
        } else {
            // KONDISI 2: SLOT KOSONG (BARANG BARU MASUK)
            
            // Generate FG ID Baru di sini!
            $newFG = $this->generateFGNumber();

            $this->model->insert([
                'fg_number' => $newFG, // Simpan ID otomatis
                'sku'       => $json->qcNumber, 
                'name'      => $json->productName,
                'quantity'  => $json->quantity,
                'location'  => $json->location,
                'category'  => 'Finished Goods'
            ]);

            $message = "Barang berhasil disimpan dengan ID: $newFG";
        }

        return $this->respondCreated(['message' => $message]);
    }

    public function getRecommendation()
    {
        header('Access-Control-Allow-Origin: *');
        $productName = $this->request->getGet('product_name');

        if (!$productName) return $this->fail('Product Name required', 400);

        // A. Cek apakah barang ini sudah ada di gudang DAN punya lokasi yang valid?
        // Tambahkan filter where location != '' agar data "hantu" tanpa lokasi tidak terambil
        $existingStorage = $this->model->where('name', $productName)
                                       ->where('location !=', '') 
                                       ->where('location IS NOT NULL')
                                       ->first();

        if ($existingStorage) {
            return $this->respond([
                'status' => 'exist',
                'location' => $existingStorage['location'],
                'message' => "Barang sudah ada di Rak {$existingStorage['location']}. Disarankan digabung."
            ]);
        }

        // B. Jika belum ada (atau lokasi sebelumnya null), Cari Slot Kosong Pertama
        $rows = ['A', 'B', 'C', 'D', 'E'];
        $levels = [1, 2, 3, 4];
        
        // Ambil hanya lokasi yang benar-benar terisi
        $occupied = $this->model->where('location !=', '')
                                ->where('location IS NOT NULL')
                                ->findColumn('location') ?? [];

        foreach ($rows as $row) {
            foreach ($levels as $level) {
                // Format Slot: A-01, A-02 ...
                $slot = "$row-0$level";
                
                // Jika slot ini TIDAK ada di daftar occupied, berarti kosong
                if (!in_array($slot, $occupied)) {
                    return $this->respond([
                        'status' => 'new',
                        'location' => $slot,
                        'message' => "Slot kosong ditemukan di $slot."
                    ]);
                }
            }
        }

        // Jika semua loop selesai dan tidak ada return, berarti penuh
        return $this->respond([
            'status' => 'full', 
            'location' => '', 
            'message' => 'Gudang Penuh! Tidak ada slot kosong tersisa.'
        ]);
    }

    private function generateFGNumber()
    {
        // 1. Pastikan Timezone Indonesia agar ganti hari tepat 00:00 WIB
        date_default_timezone_set('Asia/Jakarta'); 

        // 2. Format: FG + Tanggal (ddmmyy) -> Contoh: FG101225
        $dateCode = date('dmy'); 
        $prefix = "FG" . $dateCode; 

        // 3. Cari nomor terakhir di database yang punya prefix HARI INI
        $lastData = $this->model->like('fg_number', $prefix, 'after')
                                ->orderBy('id', 'DESC') // Ambil yang paling baru dibuat
                                ->first();

        if ($lastData) {
            // Ambil 4 digit terakhir, ubah jadi integer, lalu tambah 1
            // Contoh: FG1012250005 -> ambil 0005 -> jadi 5 -> +1 = 6
            $lastSequence = intval(substr($lastData['fg_number'], -4));
            $nextSequence = $lastSequence + 1;
        } else {
            // Jika hari ini belum ada, mulai dari 1
            $nextSequence = 1;
        }

        // 4. Gabungkan Prefix + Sequence (dipad dengan 0)
        // Hasil: FG1012250001
        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }
}