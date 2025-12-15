<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    // ...
    
    public array $default = [
        // 1. Izinkan origin frontend Anda (atau '*' untuk semua)
        'allowedOrigins' => ['http://localhost:3000', 'http://localhost:3001'], 
        
        'allowedOriginsPatterns' => [],
        
        'supportsCredentials' => true,
        
        // 2. Izinkan header yang biasa dipakai Next.js/Axios
        'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        
        'exposedHeaders' => [],
        
        // 3. Izinkan semua method CRUD
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        
        'maxAge' => 7200,
    ];
}