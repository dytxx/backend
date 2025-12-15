<?php 
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class UserController extends ResourceController {
    use ResponseTrait;
    
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';

    // 1. GET: Ambil Semua User
    public function index() {
        header('Access-Control-Allow-Origin: *');
        
        // Ambil semua data (kecuali password biar aman)
        $users = $this->model->findAll();
        
        // Opsional: Hapus field password dari response
        foreach($users as &$user) {
            unset($user['password']);
        }
        
        return $this->respond($users);
    }

    // 2. POST: Tambah User Baru
    public function create() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $json = $this->request->getJSON();

        if (!$json || !isset($json->username) || !isset($json->password) || !isset($json->role)) {
            return $this->fail('Data tidak lengkap', 400);
        }

        // Cek apakah username sudah ada
        if ($this->model->where('username', $json->username)->first()) {
            return $this->fail('Username sudah digunakan!', 409);
        }

        $data = [
            'username' => $json->username,
            // HASH PASSWORD WAJIB
            'password' => password_hash($json->password, PASSWORD_BCRYPT), 
            'role'     => $json->role
        ];

        $this->model->insert($data);
        return $this->respondCreated(['message' => 'User berhasil dibuat']);
    }

    // 3. DELETE: Hapus User
    public function delete($id = null) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        $user = $this->model->find($id);
        
        if (!$user) return $this->failNotFound('User tidak ditemukan');
        if ($user['role'] === 'superuser') return $this->fail('Tidak bisa menghapus Super User!', 403);

        $this->model->delete($id);
        return $this->respondDeleted(['message' => 'User berhasil dihapus']);
    }
}