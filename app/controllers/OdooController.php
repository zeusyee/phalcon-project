<?php

use Phalcon\Mvc\Controller;

/**
 * Controller untuk demo integrasi dengan Odoo
 */
class OdooController extends Controller
{
    private $odooService;

    public function onConstruct()
    {
        $this->odooService = new \App\Services\OdooService();
    }

    /**
     * Halaman utama - Dashboard menu
     */
    public function indexAction()
    {
        // Dashboard hanya menampilkan menu, tidak query data
    }

    /**
     * List customers dari Odoo
     */
    public function customersAction()
    {
        try {
            // Ambil data customers/partners dari Odoo (termasuk WiFi users)
            $customers = $this->odooService->searchRead(
                'res.partner',  // Model
                [],  // No filter - ambil semua contacts
                ['name', 'email', 'phone', 'mobile', 'ref', 'comment', 'city', 'country_id'],  // Fields + ref
                50  // Limit lebih besar
            );

            $this->view->customers = $customers;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->customers = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/customers');
    }

    /**
     * List products dari Odoo
     */
    public function productsAction()
    {
        try {
            $products = $this->odooService->searchRead(
                'product.template',
                [],  // Ambil semua produk
                ['name', 'default_code', 'list_price', 'standard_price', 'type', 'active'],
                50
            );

            $this->view->products = $products;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->products = [];
        }
    }

    /**
     * Create customer baru di Odoo
     */
    public function createCustomerAction()
    {
        if ($this->request->isPost()) {
            try {
                $data = [
                    'name' => $this->request->getPost('name'),
                    'email' => $this->request->getPost('email'),
                    'phone' => $this->request->getPost('phone'),
                    'is_company' => (bool)$this->request->getPost('is_company', 'int', 0),
                ];

                // Optional fields
                if ($this->request->getPost('mobile')) {
                    $data['mobile'] = $this->request->getPost('mobile');
                }
                if ($this->request->getPost('website')) {
                    $data['website'] = $this->request->getPost('website');
                }
                if ($this->request->getPost('street')) {
                    $data['street'] = $this->request->getPost('street');
                }
                if ($this->request->getPost('city')) {
                    $data['city'] = $this->request->getPost('city');
                }
                if ($this->request->getPost('zip')) {
                    $data['zip'] = $this->request->getPost('zip');
                }
                if ($this->request->getPost('country_id')) {
                    $data['country_id'] = (int)$this->request->getPost('country_id');
                }
                if ($this->request->getPost('comment')) {
                    $data['comment'] = $this->request->getPost('comment');
                }

                $customerId = $this->odooService->create('res.partner', $data);

                // Set success message dan redirect
                $this->view->success = true;
                $this->view->customerId = $customerId;
                $this->view->error = null;
            } catch (\Exception $e) {
                $this->view->error = $e->getMessage();
                $this->view->success = false;
            }
        } else {
            // Show form - render the view for GET requests
            $this->view->success = false;
            $this->view->error = null;
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/createCustomer');
    }

    /**
     * Update customer di Odoo
     */
    public function updateCustomerAction($id)
    {
        if ($this->request->isPut() || $this->request->isPost()) {
            try {
                $data = [];
                
                if ($this->request->getPost('name')) {
                    $data['name'] = $this->request->getPost('name');
                }
                if ($this->request->getPost('email')) {
                    $data['email'] = $this->request->getPost('email');
                }
                if ($this->request->getPost('phone')) {
                    $data['phone'] = $this->request->getPost('phone');
                }

                $success = $this->odooService->update('res.partner', (int)$id, $data);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Customer berhasil diupdate' : 'Gagal update customer'
                ]);
            } catch (\Exception $e) {
                return $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Sync WiFi passwords ke Odoo sebagai products
     * Contoh: Sync data dari Phalcon ke Odoo
     */
    public function syncWifiPasswordsAction()
    {
        return $this->syncWifiAction();
    }
    
    /**
     * Alias untuk sync-wifi route
     */
    public function syncWifiAction()
    {
        try {
            // Ambil data dari model Phalcon
            $wifiPasswords = WifiPassword::find([
                'limit' => 10
            ]);

            $synced = 0;
            $errors = [];
            $skipped = 0;

            foreach ($wifiPasswords as $wifi) {
                try {
                    // Check apakah sudah ada di Odoo sebagai contact
                    $existing = $this->odooService->search(
                        'res.partner',
                        [['ref', '=', 'WIFI-' . $wifi->id]]
                    );

                    if (empty($existing)) {
                        // Buat contact baru di Odoo
                        $partnerId = $this->odooService->create('res.partner', [
                            'name' => 'WiFi User - ' . ($wifi->user_name ?? 'User'),
                            'ref' => 'WIFI-' . $wifi->id,
                            'comment' => 'WiFi Password ID: ' . $wifi->id . ' | Created: ' . $wifi->created_at . ' | Password: ' . $wifi->password,
                            'is_company' => false
                        ]);

                        // Update Phalcon record dengan Odoo ID
                        $wifi->odoo_partner_id = $partnerId;
                        $wifi->save();

                        $synced++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = 'WiFi ID ' . $wifi->id . ': ' . $e->getMessage();
                }
            }

            return $this->response->setJsonContent([
                'success' => true,
                'synced' => $synced,
                'skipped' => $skipped,
                'total' => count($wifiPasswords),
                'message' => $synced > 0 ? "$synced WiFi users synced successfully!" : "All WiFi users already exist in Odoo (skipped: $skipped)",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get sales orders dari Odoo
     */
    public function salesOrdersAction()
    {
        try {
            $orders = $this->odooService->searchRead(
                'sale.order',
                [],  // Ambil semua sales orders
                ['name', 'partner_id', 'date_order', 'amount_total', 'state'],
                20
            );

            $this->view->orders = $orders;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->orders = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/salesOrders');
    }

    /**
     * Get purchase orders dari Odoo
     */
    public function purchaseOrdersAction()
    {
        try {
            $orders = $this->odooService->searchRead(
                'purchase.order',
                [],
                ['name', 'partner_id', 'date_order', 'amount_total', 'state', 'invoice_status'],
                20
            );

            $this->view->orders = $orders;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->orders = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/purchaseOrders');
    }

    /**
     * Get invoices dari Odoo
     */
    public function invoicesAction()
    {
        try {
            $invoices = $this->odooService->searchRead(
                'account.move',
                [
                    ['move_type', '=', 'out_invoice'],
                    ['state', '!=', 'cancel']
                ],
                ['name', 'partner_id', 'invoice_date', 'amount_total', 'payment_state', 'state'],
                20
            );

            $this->view->invoices = $invoices;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->invoices = [];
        }
    }

    /**
     * Get inventory/stock dari Odoo
     */
    public function inventoryAction()
    {
        try {
            // Get products with stock info
            $products = $this->odooService->searchRead(
                'product.product',
                [['type', '=', 'product']],  // Only stockable products
                ['name', 'default_code', 'qty_available', 'virtual_available', 'list_price'],
                50
            );

            $this->view->inventory = $products;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->inventory = [];
        }
    }

    /**
     * API endpoint - Get customer by ID
     */
    public function getCustomerAction($id)
    {
        try {
            $customer = $this->odooService->searchRead(
                'res.partner',
                [['id', '=', (int)$id]],
                ['name', 'email', 'phone', 'mobile', 'website', 'street', 'city', 'country_id'],
                1
            );

            return $this->response->setJsonContent([
                'success' => true,
                'customer' => $customer[0] ?? null
            ]);
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test connection to Odoo
     */
    public function testConnectionAction()
    {
        return $this->testAction();
    }
    
    /**
     * Alias untuk /odoo/test route
     */
    public function testAction()
    {
        try {
            $uid = $this->odooService->authenticate();
            
            // Get Odoo version info
            $info = [
                'connected' => true,
                'user_id' => $uid,
                'message' => 'Successfully connected to Odoo!'
            ];

            return $this->response->setJsonContent($info);
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'connected' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Debug - test raw authentication
     */
    public function debugAuthAction()
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post('http://odoo:8069/web/session/authenticate', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'db' => getenv('ODOO_DB') ?: 'odoo',
                        'login' => getenv('ODOO_USERNAME') ?: 'admin',
                        'password' => getenv('ODOO_PASSWORD') ?: 'admin'
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            return $this->response->setJsonContent([
                'success' => true,
                'raw_response' => $result,
                'env_vars' => [
                    'ODOO_URL' => getenv('ODOO_URL'),
                    'ODOO_DB' => getenv('ODOO_DB'),
                    'ODOO_USERNAME' => getenv('ODOO_USERNAME'),
                    'ODOO_PASSWORD' => '***'
                ]
            ]);
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * List WiFi users yang sudah di-sync ke Odoo
     */
    public function wifiUsersAction()
    {
        try {
            // Ambil semua contacts dengan reference WIFI-*
            $wifiUsers = $this->odooService->searchRead(
                'res.partner',
                [['ref', 'like', 'WIFI-%']],
                ['name', 'ref', 'comment', 'create_date'],
                100
            );
            
            $this->view->wifiUsers = $wifiUsers;
            $this->view->total = count($wifiUsers);
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->wifiUsers = [];
        }
    }
}
