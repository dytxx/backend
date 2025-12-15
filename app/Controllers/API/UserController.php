<?php 
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class UserController extends ResourceController {
    use ResponseTrait;
    
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';

    // === METHOD OPTIONS (WAJIB ADA) ===
    public function options()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setStatusCode(200);
    }

    public function index() {
        header('Access-Control-Allow-Origin: *');
        $users = $this->model->findAll();
        // Sembunyikan password dari output
        foreach($users as &$user) { unset($user['password']); }
        return $this->respond($users);
    }

    public function create() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $json = $this->request->getJSON();

        if (!$json || !isset($json->username) || !isset($json->password) || !isset($json->role)) {
            return $this->fail('Data tidak lengkap', 400);
        }

        if ($this->model->where('username', $json->username)->first()) {
            return $this->fail('Username sudah digunakan!', 409);
        }

        // === SIMPAN PASSWORD BIASA (TANPA HASH SEMENTARA) ===
        $data = [
            'username' => $json->username,
            'password' => $json->password, // Jangan di-hash dulu
            'role'     => $json->role
        ];

        $this->model->insert($data);
        return $this->respondCreated(['message' => 'User berhasil dibuat']);
    }

    public function delete($id = null) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: DELETE, OPTIONS');
        
        $user = $this->model->find($id);
        if (!$user) return $this->failNotFound('User tidak ditemukan');
        if ($user['role'] === 'superuser') return $this->fail('Superuser tidak bisa dihapus!', 403);

        $this->model->delete($id);
        return $this->respondDeleted(['message' => 'User berhasil dihapus']);
    }
}