<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\QCModel;
use App\Models\WOModel; // Jangan lupa use WOModel

class QCController extends ResourceController
{
    use ResponseTrait;

    public function options()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setStatusCode(200);
    }

    public function generateNumber()
    {
        header('Access-Control-Allow-Origin: *');
        date_default_timezone_set('Asia/Jakarta');

        $model = new QCModel();
        $dateCode = date('dmy'); 
        $prefix = "QC" . $dateCode; 

        $lastData = $model->like('qc_number', $prefix, 'after')->orderBy('id', 'DESC')->first();
        $nextSequence = $lastData ? intval(substr($lastData['qc_number'], -3)) + 1 : 1;
        $newNumber = $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

        return $this->respond(['qcNumber' => $newNumber]);
    }

    // --- UPDATE DI SINI: Logika Validasi Qty ---
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        $json = $this->request->getJSON();
        if (!$json) return $this->fail('Tidak ada data JSON', 400);

        // --- VALIDASI TAMBAHAN: CHECKER NAME ---
        if (empty($json->checkerName)) {
            return $this->fail('Nama Petugas (Checker Name) wajib diisi!', 400);
        }

        // 1. Ambil Data WO Terkait untuk cek target Qty
        $woModel = new WOModel();
        $woData = $woModel->where('wo_number', $json->poNumber)->first();

        if (!$woData) {
            return $this->failNotFound('Nomor WO tidak ditemukan di database.');
        }

        // 2. Hitung jumlah yang SUDAH Pass (OK) sebelumnya
        $db = \Config\Database::connect();
        $query = $db->query("
            SELECT COALESCE(SUM(checked_quantity), 0) as total_ok 
            FROM qc_reports 
            WHERE po_number = ? AND result = 'OK'
        ", [$json->poNumber]);
        
        $row = $query->getRow();
        $currentTotalOK = (int) $row->total_ok;
        
        // 3. Hitung Sisa (Target - Sudah OK)
        $remainingQty = (int) $woData['quantity'] - $currentTotalOK;
        $inputQty = (int) $json->checkedQuantity;

        // 4. VALIDASI: Jika hasil QC 'OK' dan input melebihi sisa
        if ($json->result === 'OK') {
            if ($inputQty > $remainingQty) {
                return $this->fail(
                    "Gagal! Qty yang diinput ($inputQty) melebihi sisa kuota WO ($remainingQty).", 
                    400
                );
            }
        }

        // 5. Jika lolos validasi, Simpan Data
        $model = new QCModel();
        $dataToSave = [
            'qc_number'        => $json->qcNumber,
            'po_number'        => $json->poNumber,
            'product_code'     => $json->productCode,
            'checked_quantity' => $inputQty,
            'result'           => $json->result,
            'checker_name'     => $json->checkerName,
            'notes'            => $json->notes,
        ];

        try {
            $model->insert($dataToSave);
            return $this->respondCreated([
                'message' => 'Laporan QC berhasil disimpan!',
                'remaining_qty' => ($json->result === 'OK') ? ($remainingQty - $inputQty) : $remainingQty
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}