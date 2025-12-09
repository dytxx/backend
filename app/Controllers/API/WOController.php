<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\WOModel;

class WOController extends ResourceController
{
    use ResponseTrait;

    // 1. Preflight Check (CORS)
    public function options()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setStatusCode(200);
    }

    // 2. Generate Nomor Otomatis (Format: WO-YYMMDD-XXXX)
    public function generateNumber()
    {
        header('Access-Control-Allow-Origin: *');
        $model = new WOModel();

        // Format: WO + 2 digit tahun + bulan + tanggal (Contoh: WO251209)
        $dateCode = date('ymd'); 
        $prefix = "WO" . $dateCode; 

        // Cari nomor terakhir hari ini
        $lastData = $model->like('wo_number', $prefix, 'after')
                          ->orderBy('id', 'DESC')
                          ->first();

        if ($lastData) {
            // Ambil 4 digit terakhir
            $lastSequence = intval(substr($lastData['wo_number'], -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        // Format jadi 4 digit (cth: 0001)
        $newNumber = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

        return $this->respond(['woNumber' => $newNumber]);
    }

    // 3. Simpan Data
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('Tidak ada data JSON', 400);
        }

        $model = new WOModel();
        
        $dataToSave = [
            'wo_number'    => $json->woNumber,
            'customer'     => $json->customer,
            'project_name' => $json->projectName,
            'part_number'  => $json->partNumber,
            'quantity'     => $json->quantity,
            'remarks'      => $json->remarks,
        ];

        try {
            $model->insert($dataToSave);
            return $this->respondCreated(['message' => 'Work Order berhasil dibuat!']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
    public function index()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');

        $db = \Config\Database::connect();

        // Query Logika:
        // 1. Ambil data WO.
        // 2. Hitung jumlah barang yang sudah QC 'OK' (Pass) di tabel qc_reports untuk WO tersebut.
        // 3. Hitung sisa (Target - Sudah Pass).
        // 4. Filter hanya tampilkan yang sisanya > 0.

        $sql = "
            SELECT 
                w.id, 
                w.wo_number, 
                w.part_number, 
                w.quantity as target_qty,
                /* Hitung total yang sudah OK */
                (SELECT COALESCE(SUM(q.checked_quantity), 0) 
                 FROM qc_reports q 
                 WHERE q.po_number = w.wo_number AND q.result = 'OK') as total_pass,
                 
                /* Hitung Sisa */
                (w.quantity - (SELECT COALESCE(SUM(q.checked_quantity), 0) 
                               FROM qc_reports q 
                               WHERE q.po_number = w.wo_number AND q.result = 'OK')) as remaining_qty
            FROM work_orders w
            HAVING remaining_qty > 0  /* Filter: Hilangkan jika sisa 0 atau minus */
            ORDER BY w.id DESC
        ";

        $query = $db->query($sql);
        $data = $query->getResultArray();

        return $this->respond($data);
    }
}

