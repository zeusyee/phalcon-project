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
            // Ambil data customers/partners dari Odoo
            // Filter: Customer Rank > 0 ATAU Supplier Rank = 0 (untuk menangkap contact lama/baru yang belum ada rank)
            $customers = $this->odooService->searchRead(
                'res.partner',  // Model
                ['|', ['customer_rank', '>', 0], ['supplier_rank', '=', 0]],  // Filter inclusive
                ['name', 'email', 'phone', 'ref', 'comment', 'city', 'country_id', 'customer_rank', 'supplier_rank'],
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
     * List vendors dari Odoo
     */
    public function vendorsAction()
    {
        try {
            // Ambil data vendors/partners dari Odoo (Strict Vendor)
            // Note: Jika vendor lama tidak muncul, pastikan supplier_rank > 0 di Odoo
            $vendors = $this->odooService->searchRead(
                'res.partner',  // Model
                [['supplier_rank', '>', 0]],  // Filter vendor only
                ['name', 'email', 'phone', 'ref', 'comment', 'city', 'country_id'],
                50  // Limit lebih besar
            );

            $this->view->vendors = $vendors;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->vendors = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/vendors');
    }

    /**
     * List products dari Odoo (with inventory data)
     */
    public function productsAction()
    {
        try {
            // Get all products from product.template
            $products = $this->odooService->searchRead(
                'product.template',
                [],  // Ambil semua produk
                ['name', 'default_code', 'list_price', 'standard_price', 'type', 'active', 'product_variant_ids'],
                50
            );

            // Get inventory data from product.product (variants)
            foreach ($products as &$product) {
                if (isset($product['product_variant_ids']) && !empty($product['product_variant_ids'])) {
                    try {
                        $variantId = is_array($product['product_variant_ids']) ? $product['product_variant_ids'][0] : $product['product_variant_ids'];
                        $variants = $this->odooService->searchRead(
                            'product.product',
                            [['id', '=', $variantId]],
                            ['qty_available', 'virtual_available'],
                            1
                        );
                        
                        if (!empty($variants)) {
                            $product['qty_available'] = $variants[0]['qty_available'] ?? 0;
                            $product['virtual_available'] = $variants[0]['virtual_available'] ?? 0;
                        }
                    } catch (\Exception $e) {
                        $product['qty_available'] = 0;
                        $product['virtual_available'] = 0;
                    }
                } else {
                    $product['qty_available'] = 0;
                    $product['virtual_available'] = 0;
                }
            }

            $this->view->products = $products;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->products = [];
        }
    }

    /**
     * Create product baru di Odoo
     */
    public function createProductAction()
    {
        if ($this->request->isPost()) {
            try {
                $inputType = $this->request->getPost('type', 'string', 'consu');
                
                // Odoo 19: type field only accepts 'consu' or 'service'
                $validTypes = ['consu', 'service'];
                $type = in_array($inputType, $validTypes) ? $inputType : 'consu';
                
                $data = [
                    'name' => $this->request->getPost('name'),
                    'type' => $type,
                    'list_price' => (float)$this->request->getPost('list_price', 'float', 0),
                ];

                // Optional fields
                if ($this->request->getPost('default_code')) {
                    $data['default_code'] = $this->request->getPost('default_code');
                }
                if ($this->request->getPost('standard_price')) {
                    $data['standard_price'] = (float)$this->request->getPost('standard_price');
                }
                if ($this->request->getPost('description')) {
                    $data['description'] = $this->request->getPost('description');
                }
                if ($this->request->getPost('description_sale')) {
                    $data['description_sale'] = $this->request->getPost('description_sale');
                }
                if ($this->request->getPost('categ_id')) {
                    $data['categ_id'] = (int)$this->request->getPost('categ_id');
                }
                if ($this->request->getPost('uom_id')) {
                    $data['uom_id'] = (int)$this->request->getPost('uom_id');
                }
                if ($this->request->getPost('weight')) {
                    $data['weight'] = (float)$this->request->getPost('weight');
                }
                if ($this->request->getPost('volume')) {
                    $data['volume'] = (float)$this->request->getPost('volume');
                }

                $productId = $this->odooService->create('product.template', $data);

                $message = 'Product berhasil dibuat';

                // Note: Stock management for Odoo 19 must be done via Odoo UI directly
                // REST API tidak support stockable products dengan baik

                return $this->response->setJsonContent([
                    'success' => true,
                    'productId' => $productId,
                    'message' => $message
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
     * Update product di Odoo
     */
    public function updateProductAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $data = [];

                if ($this->request->getPost('name')) {
                    $data['name'] = $this->request->getPost('name');
                }
                if ($this->request->getPost('list_price') !== null) {
                    $data['list_price'] = (float)$this->request->getPost('list_price');
                }
                if ($this->request->getPost('standard_price') !== null) {
                    $data['standard_price'] = (float)$this->request->getPost('standard_price');
                }
                if ($this->request->getPost('default_code')) {
                    $data['default_code'] = $this->request->getPost('default_code');
                }

                $success = $this->odooService->update('product.template', (int)$id, $data);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Product berhasil diupdate' : 'Gagal update product'
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
     * Delete product di Odoo
     */
    public function deleteProductAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $success = $this->odooService->delete('product.template', (int)$id);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Product berhasil dihapus' : 'Gagal hapus product'
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
                    'customer_rank' => 1, // Tandai sebagai customer
                    'supplier_rank' => 0,
                ];

                // Optional fields
                if ($this->request->getPost('mobile')) {
                    $data['mobile'] = $this->request->getPost('mobile');
                }
                if ($this->request->getPost('title')) {
                    $data['title'] = $this->request->getPost('title');
                }
                if ($this->request->getPost('function')) {
                    $data['function'] = $this->request->getPost('function');
                }
                if ($this->request->getPost('vat')) {
                    $data['vat'] = $this->request->getPost('vat');
                }
                if ($this->request->getPost('website')) {
                    $data['website'] = $this->request->getPost('website');
                }
                if ($this->request->getPost('street')) {
                    $data['street'] = $this->request->getPost('street');
                }
                if ($this->request->getPost('street2')) {
                    $data['street2'] = $this->request->getPost('street2');
                }
                if ($this->request->getPost('city')) {
                    $data['city'] = $this->request->getPost('city');
                }
                if ($this->request->getPost('state_id')) {
                    $data['state_id'] = (int)$this->request->getPost('state_id');
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

                return $this->response->setJsonContent([
                    'success' => true,
                    'customerId' => $customerId,
                    'message' => 'Customer berhasil dibuat'
                ]);
            } catch (\Exception $e) {
                return $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
        } else {
            // Show form - render the view for GET requests
            $this->view->success = false;
            $this->view->error = null;
            
            // Explicitly set view path
            $this->view->pick('odoo/createCustomer');
        }
    }

    /**
     * Create vendor baru di Odoo
     */
    public function createVendorAction()
    {
        if ($this->request->isPost()) {
            try {
                $data = [
                    'name' => $this->request->getPost('name'),
                    'email' => $this->request->getPost('email'),
                    'phone' => $this->request->getPost('phone'),
                    'is_company' => (bool)$this->request->getPost('is_company', 'int', 0),
                    'customer_rank' => 0,
                    'supplier_rank' => 1, // Tandai sebagai vendor
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
                if ($this->request->getPost('country_id')) {
                    $data['country_id'] = (int)$this->request->getPost('country_id');
                }

                $vendorId = $this->odooService->create('res.partner', $data);

                return $this->response->setJsonContent([
                    'success' => true,
                    'vendorId' => $vendorId,
                    'message' => 'Vendor berhasil dibuat'
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
     * Delete customer di Odoo
     */
    public function deleteCustomerAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $this->odooService->delete('res.partner', (int)$id);

                return $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'Customer berhasil dihapus'
                ]);
            } catch (\Exception $e) {
                // If delete fails, try to archive (soft delete)
                try {
                    $this->odooService->update('res.partner', (int)$id, ['active' => false]);
                    return $this->response->setJsonContent([
                        'success' => true,
                        'message' => 'Customer berhasil diarsipkan (karena terhubung dengan data lain)'
                    ]);
                } catch (\Exception $e2) {
                    return $this->response->setJsonContent([
                        'success' => false,
                        'message' => 'Gagal menghapus atau mengarsipkan: ' . $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Delete vendor di Odoo
     */
    public function deleteVendorAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $this->odooService->delete('res.partner', (int)$id);

                return $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'Vendor berhasil dihapus'
                ]);
            } catch (\Exception $e) {
                // If delete fails, try to archive (soft delete)
                try {
                    $this->odooService->update('res.partner', (int)$id, ['active' => false]);
                    return $this->response->setJsonContent([
                        'success' => true,
                        'message' => 'Vendor berhasil diarsipkan (karena terhubung dengan data lain)'
                    ]);
                } catch (\Exception $e2) {
                    return $this->response->setJsonContent([
                        'success' => false,
                        'message' => 'Gagal menghapus atau mengarsipkan: ' . $e->getMessage()
                    ]);
                }
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
                [],
                ['name', 'partner_id', 'date_order', 'amount_total', 'state'],
                20
            );

            // Fetch customers for dropdown (hanya customer atau neutral)
            $customers = $this->odooService->searchRead(
                'res.partner',
                ['|', ['customer_rank', '>', 0], ['supplier_rank', '=', 0]],
                ['id', 'name'],
                100
            );

            // Fetch salespersons (users with sales access)
            $salespersons = $this->odooService->searchRead(
                'res.users',
                [['active', '=', true]],
                ['id', 'name'],
                50
            );

            // Fetch products for dropdown
            $products = $this->odooService->searchRead(
                'product.product',
                [['sale_ok', '=', true]],
                ['id', 'name', 'list_price'],
                100
            );

            $this->view->orders = $orders;
            $this->view->customers = $customers;
            $this->view->salespersons = $salespersons;
            $this->view->products = $products;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->orders = [];
            $this->view->customers = [];
            $this->view->salespersons = [];
            $this->view->products = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/salesOrders');
    }

    /**
     * Create sales order baru di Odoo
     */
    public function createSalesOrderAction()
    {
        if ($this->request->isPost()) {
            try {
                $data = [
                    'partner_id' => (int)$this->request->getPost('partner_id'),
                ];

                // Optional fields
                if ($this->request->getPost('user_id')) {
                    $data['user_id'] = (int)$this->request->getPost('user_id');
                }
                if ($this->request->getPost('client_order_ref')) {
                    $data['client_order_ref'] = $this->request->getPost('client_order_ref');
                }

                // Add order lines if products specified
                $productIds = $this->request->getPost('product_ids');
                $quantities = $this->request->getPost('quantities');
                
                if (!empty($productIds) && is_array($productIds)) {
                    $orderLines = [];
                    foreach ($productIds as $index => $productId) {
                        if (!empty($productId)) {
                            $qty = isset($quantities[$index]) ? (float)$quantities[$index] : 1;
                            $orderLines[] = [0, 0, [
                                'product_id' => (int)$productId,
                                'product_uom_qty' => $qty,
                            ]];
                        }
                    }
                    if (!empty($orderLines)) {
                        $data['order_line'] = $orderLines;
                    }
                }

                $orderId = $this->odooService->create('sale.order', $data);

                return $this->response->setJsonContent([
                    'success' => true,
                    'orderId' => $orderId,
                    'message' => 'Sales Order berhasil dibuat'
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
     * Update sales order di Odoo
     */
    public function updateSalesOrderAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                
                if ($this->request->getPost('partner_id')) {
                    $data['partner_id'] = (int)$this->request->getPost('partner_id');
                }
                if ($this->request->getPost('note')) {
                    $data['note'] = $this->request->getPost('note');
                }

                $success = $this->odooService->update('sale.order', (int)$id, $data);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Sales Order berhasil diupdate' : 'Gagal update Sales Order'
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
     * Delete sales order di Odoo
     */
    public function deleteSalesOrderAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $success = $this->odooService->delete('sale.order', (int)$id);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Sales Order berhasil dihapus' : 'Gagal hapus Sales Order'
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
     * Confirm sales order (change state to 'sale')
     */
    public function confirmSalesOrderAction($id)
    {
        if ($this->request->isPost()) {
            try {
                // Call action_confirm method
                $result = $this->odooService->call('sale.order', 'action_confirm', [[(int)$id]]);

                return $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'Sales Order berhasil dikonfirmasi'
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

            // Fetch vendors (suppliers) for dropdown
            $vendors = $this->odooService->searchRead(
                'res.partner',
                [['supplier_rank', '>', 0]],
                ['id', 'name'],
                100
            );

            // Fetch products for dropdown
            $products = $this->odooService->searchRead(
                'product.product',
                [['purchase_ok', '=', true]],
                ['id', 'name', 'standard_price'],
                100
            );

            $this->view->orders = $orders;
            $this->view->vendors = $vendors;
            $this->view->products = $products;
            $this->view->error = null;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->orders = [];
            $this->view->vendors = [];
            $this->view->products = [];
        }
        
        // Explicitly set view path
        $this->view->pick('odoo/purchaseOrders');
    }

    /**
     * Create purchase order baru di Odoo
     */
    public function createPurchaseOrderAction()
    {
        if ($this->request->isPost()) {
            try {
                $data = [
                    'partner_id' => (int)$this->request->getPost('partner_id'),
                ];

                // Optional partner reference
                if ($this->request->getPost('partner_ref')) {
                    $data['partner_ref'] = $this->request->getPost('partner_ref');
                }

                // Add order lines if products specified
                $productIds = $this->request->getPost('product_ids');
                $quantities = $this->request->getPost('quantities');
                
                if (!empty($productIds) && is_array($productIds)) {
                    $orderLines = [];
                    foreach ($productIds as $index => $productId) {
                        if (!empty($productId)) {
                            $qty = isset($quantities[$index]) ? (float)$quantities[$index] : 1;
                            $orderLines[] = [0, 0, [
                                'product_id' => (int)$productId,
                                'product_qty' => $qty,
                            ]];
                        }
                    }
                    if (!empty($orderLines)) {
                        $data['order_line'] = $orderLines;
                    }
                }

                $orderId = $this->odooService->create('purchase.order', $data);

                return $this->response->setJsonContent([
                    'success' => true,
                    'orderId' => $orderId,
                    'message' => 'Purchase Order berhasil dibuat'
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
     * Update purchase order di Odoo
     */
    public function updatePurchaseOrderAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                
                if ($this->request->getPost('partner_id')) {
                    $data['partner_id'] = (int)$this->request->getPost('partner_id');
                }
                if ($this->request->getPost('notes')) {
                    $data['notes'] = $this->request->getPost('notes');
                }

                $success = $this->odooService->update('purchase.order', (int)$id, $data);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Purchase Order berhasil diupdate' : 'Gagal update Purchase Order'
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
     * Delete purchase order di Odoo
     */
    public function deletePurchaseOrderAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $success = $this->odooService->delete('purchase.order', (int)$id);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Purchase Order berhasil dihapus' : 'Gagal hapus Purchase Order'
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
     * Confirm purchase order
     */
    public function confirmPurchaseOrderAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->odooService->call('purchase.order', 'button_confirm', [[(int)$id]]);

                return $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'Purchase Order berhasil dikonfirmasi'
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

            // Fetch customers for dropdown (hanya customer atau neutral)
            $customers = $this->odooService->searchRead(
                'res.partner',
                ['|', ['customer_rank', '>', 0], ['supplier_rank', '=', 0]],
                ['id', 'name'],
                100
            );

            // Fetch products for dropdown
            $products = $this->odooService->searchRead(
                'product.product',
                [['sale_ok', '=', true]],
                ['id', 'name', 'list_price'],
                100
            );

            $this->view->invoices = $invoices;
            $this->view->customers = $customers;
            $this->view->products = $products;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->invoices = [];
            $this->view->customers = [];
            $this->view->products = [];
        }
    }

    /**
     * Create invoice baru di Odoo
     */
    public function createInvoiceAction()
    {
        if ($this->request->isPost()) {
            try {
                $data = [
                    'partner_id' => (int)$this->request->getPost('partner_id'),
                    'move_type' => 'out_invoice',
                ];

                // Optional payment reference
                if ($this->request->getPost('payment_reference')) {
                    $data['payment_reference'] = $this->request->getPost('payment_reference');
                }

                // Prepare invoice lines
                $productIds = $this->request->getPost('product_ids');
                $quantities = $this->request->getPost('quantities');
                $invoiceLines = [];
                if (!empty($productIds) && is_array($productIds)) {
                    foreach ($productIds as $index => $productId) {
                        if (!empty($productId)) {
                            $qty = isset($quantities[$index]) ? (float)$quantities[$index] : 1;
                            $products = $this->odooService->searchRead(
                                'product.product',
                                [['id', '=', (int)$productId]],
                                ['name', 'list_price', 'property_account_income_id'],
                                1
                            );
                            if (!empty($products)) {
                                $product = $products[0];
                                $line = [
                                    'product_id' => (int)$productId,
                                    'quantity' => $qty,
                                    'price_unit' => $product['list_price'] ?? 0,
                                    'name' => $product['name'] ?? 'Item',
                                ];
                                if (isset($product['property_account_income_id']) && !empty($product['property_account_income_id'])) {
                                    $accountId = is_array($product['property_account_income_id'])
                                        ? $product['property_account_income_id'][0]
                                        : $product['property_account_income_id'];
                                    $line['account_id'] = (int)$accountId;
                                }
                                $invoiceLines[] = [0, 0, $line];
                            }
                        }
                    }
                }
                if (!empty($invoiceLines)) {
                    $data['invoice_line_ids'] = $invoiceLines;
                }

                $invoiceId = $this->odooService->create('account.move', $data);

                return $this->response->setJsonContent([
                    'success' => true,
                    'invoiceId' => $invoiceId,
                    'message' => 'Invoice berhasil dibuat'
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
     * Update invoice di Odoo
     */
    public function updateInvoiceAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                
                if ($this->request->getPost('partner_id')) {
                    $data['partner_id'] = (int)$this->request->getPost('partner_id');
                }
                if ($this->request->getPost('invoice_date')) {
                    $data['invoice_date'] = $this->request->getPost('invoice_date');
                }
                if ($this->request->getPost('narration')) {
                    $data['narration'] = $this->request->getPost('narration');
                }

                $success = $this->odooService->update('account.move', (int)$id, $data);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Invoice berhasil diupdate' : 'Gagal update Invoice'
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
     * Delete invoice di Odoo
     */
    public function deleteInvoiceAction($id)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            try {
                $success = $this->odooService->delete('account.move', (int)$id);

                return $this->response->setJsonContent([
                    'success' => $success,
                    'message' => $success ? 'Invoice berhasil dihapus' : 'Gagal hapus Invoice'
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
     * Post invoice (confirm)
     */
    public function postInvoiceAction($id)
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->odooService->call('account.move', 'action_post', [[(int)$id]]);

                return $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'Invoice berhasil dipost'
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

    /**
     * Process payment for invoice
     */
    public function processPaymentAction()
    {
        $this->view->disable();
        
        if (!$this->request->isPost()) {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        try {
            $invoiceId = (int) $this->request->getPost('invoice_id');
            $amount = (float) $this->request->getPost('amount');
            $journalId = (int) $this->request->getPost('journal_id', 'int', 1);
            $memo = $this->request->getPost('memo', 'string', '');

            if (!$invoiceId || !$amount) {
                throw new \Exception('Invoice ID dan jumlah pembayaran harus diisi');
            }

            // 1. Cek Status Invoice & Post jika masih Draft
            // Invoice harus dalam status 'posted' agar bisa dibayar
            $invoices = $this->odooService->searchRead(
                'account.move', 
                [['id', '=', $invoiceId]], 
                ['state']
            );
            
            if (empty($invoices)) {
                throw new \Exception('Invoice tidak ditemukan');
            }
            
            if ($invoices[0]['state'] === 'draft') {
                $this->odooService->call('account.move', 'action_post', [[$invoiceId]]);
            }

            // 2. Gunakan account.payment.register Wizard
            // Ini adalah cara standar Odoo memproses pembayaran invoice
            
            // Context sangat PENTING: memberitahu wizard invoice mana yang dibayar
            $context = [
                'active_model' => 'account.move', 
                'active_ids' => [$invoiceId]
            ];

            // Panggil default_get untuk mendapatkan line_ids (tagihan) yang akan dibayar
            // Tanpa line_ids, pembayaran akan terbuat tapi tidak linked ke invoice (unreconciled)
            $defaults = $this->odooService->call(
                'account.payment.register',
                'default_get',
                [['line_ids', 'partner_id', 'currency_id']], 
                ['context' => $context]
            );
            
            if (empty($defaults)) {
                // Should not happen if invoice is posted and has residual amount
                 throw new \Exception('Gagal inisialisasi pembayaran. Pastikan invoice sudah diposting dan belum lunas.');
            }

            // Gabungkan defaults dengan input user
            $wizardVals = array_merge($defaults, [
                'journal_id' => $journalId,
                'amount' => $amount, 
                'payment_date' => date('Y-m-d'),
                'communication' => $memo ?: 'Payment Invoice ' . $invoiceId
            ]);

            // 3. Create Wizard Record
            $wizardId = $this->odooService->call(
                'account.payment.register', 
                'create', 
                [$wizardVals], 
                ['context' => $context]
            );

            if (is_array($wizardId)) {
                $wizardId = $wizardId[0];
            }

            // 4. Confirm Payment (Action Create Payments)
            // Ini akan membuat payment dan otomatis reconcile dengan invoice
            $this->odooService->call(
                'account.payment.register', 
                'action_create_payments', 
                [[$wizardId]], 
                ['context' => $context]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Pembayaran berhasil diproses dan invoice lunas',
                'payment_id' => $wizardId
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get partner ID from invoice
     */
    private function getInvoicePartnerId($invoiceId)
    {
        $invoice = $this->odooService->searchRead(
            'account.move',
            [['id', '=', $invoiceId]],
            ['partner_id'],
            1
        );

        if (empty($invoice)) {
            throw new \Exception('Invoice tidak ditemukan');
        }

        return is_array($invoice[0]['partner_id']) ? $invoice[0]['partner_id'][0] : $invoice[0]['partner_id'];
    }
}
