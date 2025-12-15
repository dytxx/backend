<?php 
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AuthController extends ResourceController {
    use ResponseTrait;

    public function options()
    {
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setStatusCode(200);
    }
    
    public function login() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        $model = new UserModel();
        $json = $this->request->getJSON();

        if (!$json || !isset($json->username) || !isset($json->password)) {
            return $this->fail('Username dan Password wajib diisi', 400);
        }

        $user = $model->where('username', $json->username)->first();

        if ($user) {
            // Cek Password (gunakan password_verify jika hash, atau == jika plain text sementara)
            if (password_verify($json->password, $user['password'])) {
                return $this->respond([
                    'status' => 200,
                    'message' => 'Login Berhasil',
                    'data' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ]
                ]);
            }
        }
        return $this->failNotFound('Username atau Password salah');
    }
}