<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Service untuk integrasi dengan Odoo menggunakan REST API
 */
class OdooService
{
    private $url;
    private $db;
    private $username;
    private $password;
    private $uid;
    private $client;
    private $cookieJar;

    public function __construct()
    {
        // Baca konfigurasi dari environment atau config
        $this->url = getenv('ODOO_URL') ?: 'http://odoo:8069';
        $this->db = getenv('ODOO_DB') ?: 'odoo';
        $this->username = getenv('ODOO_USERNAME') ?: 'admin@example.com';
        $this->password = getenv('ODOO_PASSWORD') ?: 'admin';
        
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'cookies' => $this->cookieJar,
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * Authenticate dengan Odoo
     * 
     * @return int User ID
     * @throws \Exception jika authentication gagal
     */
    public function authenticate()
    {
        try {
            $response = $this->client->post($this->url . '/web/session/authenticate', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'db' => $this->db,
                        'login' => $this->username,
                        'password' => $this->password
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['result']['uid']) && $result['result']['uid']) {
                $this->uid = $result['result']['uid'];
                return $this->uid;
            }
            
            // Debug: log what we received
            error_log('Odoo auth result: ' . print_r($result, true));
            throw new \Exception('Authentication failed: Invalid credentials or wrong response format');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \Exception('Odoo connection error: ' . $e->getMessage());
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Odoo') === 0) {
                throw $e;
            }
            throw new \Exception('Odoo authentication error: ' . $e->getMessage());
        }
    }

    /**
     * Cari dan baca records dari Odoo
     * 
     * @param string $model Model Odoo (contoh: 'res.partner', 'product.product')
     * @param array $domain Filter domain ([['field', 'operator', 'value']])
     * @param array $fields Fields yang ingin diambil
     * @param int $limit Limit records
     * @param int $offset Offset untuk pagination
     * @return array Records
     */
    public function searchRead($model, $domain = [], $fields = [], $limit = 100, $offset = 0)
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            // Use call_kw for Odoo 17 compatibility
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => 'search_read',
                        'args' => [$domain],
                        'kwargs' => [
                            'fields' => $fields,
                            'limit' => $limit,
                            'offset' => $offset,
                            'context' => []
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['result']) && is_array($result['result'])) {
                return $result['result'];
            }
            
            return [];
        } catch (\Exception $e) {
            throw new \Exception('Odoo search_read error: ' . $e->getMessage());
        }
    }

    /**
     * Buat record baru di Odoo
     * 
     * @param string $model Model Odoo
     * @param array $values Data untuk record baru
     * @return int ID record yang dibuat
     */
    public function create($model, $values)
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => 'create',
                        'args' => [$values],
                        'kwargs' => [
                            'context' => []
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            // Check for error
            if (isset($result['error'])) {
                $errorMsg = $result['error']['data']['message'] ?? $result['error']['message'] ?? 'Unknown error';
                throw new \Exception('Odoo error: ' . $errorMsg);
            }
            
            throw new \Exception('Failed to create record: No result returned');
        } catch (\Exception $e) {
            throw new \Exception('Odoo create error: ' . $e->getMessage());
        }
    }

    /**
     * Update record di Odoo
     * 
     * @param string $model Model Odoo
     * @param int $id ID record yang akan diupdate
     * @param array $values Data baru
     * @return bool Success status
     */
    public function update($model, $id, $values)
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => 'write',
                        'args' => [[$id], $values],
                        'kwargs' => [
                            'context' => []
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            return isset($result['result']) && $result['result'] === true;
        } catch (\Exception $e) {
            throw new \Exception('Odoo update error: ' . $e->getMessage());
        }
    }

    /**
     * Hapus record di Odoo
     * 
     * @param string $model Model Odoo
     * @param int $id ID record yang akan dihapus
     * @return bool Success status
     */
    public function delete($model, $id)
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => 'unlink',
                        'args' => [[$id]],
                        'kwargs' => [
                            'context' => []
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            return isset($result['result']) && $result['result'] === true;
        } catch (\Exception $e) {
            throw new \Exception('Odoo delete error: ' . $e->getMessage());
        }
    }

    /**
     * Call method custom di Odoo
     * 
     * @param string $model Model Odoo
     * @param string $method Method name
     * @param array $args Arguments
     * @param array $kwargs Keyword arguments
     * @return mixed Result
     */
    public function call($model, $method, $args = [], $kwargs = [])
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => $method,
                        'args' => $args,
                        'kwargs' => $kwargs
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            return null;
        } catch (\Exception $e) {
            throw new \Exception('Odoo call error: ' . $e->getMessage());
        }
    }

    /**
     * Get informasi model Odoo
     * 
     * @param string $model Model name
     * @return array Model fields info
     */
    public function getModelFields($model)
    {
        return $this->call($model, 'fields_get', [], [
            'attributes' => ['string', 'type', 'required', 'readonly']
        ]);
    }

    /**
     * Search record IDs saja (tanpa read data)
     * 
     * @param string $model Model Odoo
     * @param array $domain Filter domain
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array of IDs
     */
    public function search($model, $domain = [], $limit = 100, $offset = 0)
    {
        return $this->call($model, 'search', [$domain], [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Count records
     * 
     * @param string $model Model Odoo
     * @param array $domain Filter domain
     * @return int Count
     */
    public function searchCount($model, $domain = [])
    {
        return $this->call($model, 'search_count', [$domain]);
    }
    
    /**
     * Execute a method on a specific record (wizard execution, etc)
     * 
     * @param string $model Model Odoo
     * @param int $id Record ID
     * @param string $method Method name to execute
     * @param array $args Additional arguments
     * @param array $kwargs Additional keyword arguments
     * @return mixed Result dari method execution
     */
    public function execute($model, $id, $method, $args = [], $kwargs = [])
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        try {
            $response = $this->client->post($this->url . '/web/dataset/call_kw', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'model' => $model,
                        'method' => $method,
                        'args' => array_merge([[$id]], $args),
                        'kwargs' => array_merge(['context' => []], $kwargs)
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['error'])) {
                throw new \Exception('Odoo error: ' . json_encode($result['error']));
            }
            
            return $result['result'] ?? null;
        } catch (\Exception $e) {
            throw new \Exception('Odoo execute error: ' . $e->getMessage());
        }
    }
}
