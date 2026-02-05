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

                // AUTO-CREATE INVOICE
                $autoInv = $this->_createInvoiceFromOrder($orderId, 'sale');
                $invMsg = 'Draft Invoice otomatis dibuat!';
                if (!$autoInv['success']) {
                    $invMsg = 'Gagal buat invoice otomatis: ' . $autoInv['message'];
                }

                return $this->response->setJsonContent([
                    'success' => true,
                    'orderId' => $orderId,
                    'message' => 'Sales Order berhasil dibuat. ' . $invMsg
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

                // AUTO-CREATE BILL (INVOICE)
                $autoInv = $this->_createInvoiceFromOrder($orderId, 'purchase');
                $invMsg = 'Draft Bill otomatis dibuat!';
                if (!$autoInv['success']) {
                    $invMsg = 'Gagal buat bill otomatis: ' . $autoInv['message'];
                }

                return $this->response->setJsonContent([
                    'success' => true,
                    'orderId' => $orderId,
                    'message' => 'Purchase Order berhasil dibuat. ' . $invMsg
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
            // Fetch Customer Invoices (Sales Order)
            $customerInvoices = $this->odooService->searchRead(
                'account.move',
                [
                    ['move_type', '=', 'out_invoice'],
                    ['state', '!=', 'cancel']
                ],
                ['name', 'partner_id', 'invoice_date', 'amount_total', 'payment_state', 'state'],
                20
            );

            // Fetch Vendor Bills (Purchase Order)
            $vendorBills = $this->odooService->searchRead(
                'account.move',
                [
                    ['move_type', '=', 'in_invoice'],
                    ['state', '!=', 'cancel']
                ],
                ['name', 'partner_id', 'invoice_date', 'amount_total', 'payment_state', 'state'],
                20
            );

            // Fetch partners (Customers & Vendors) for dropdown
            // Remove rank filter to include ALL partners (including new ones with rank=0)
            $partners = $this->odooService->searchRead(
                'res.partner',
                [], // Empty filter to get everything
                ['id', 'name', 'customer_rank', 'supplier_rank'],
                1000 // Increased limit
            );

            // Fetch products for dropdown
            $products = $this->odooService->searchRead(
                'product.product',
                [['sale_ok', '=', true]],
                ['id', 'name', 'list_price'],
                100
            );

            // Fetch Sales Orders (Active / Non-Cancelled)
            $salesOrders = $this->odooService->searchRead(
                'sale.order',
                [['state', '!=', 'cancel']], 
                ['id', 'name', 'partner_id', 'amount_total', 'state'],
                50
            );

            // Fetch Purchase Orders (Active / Non-Cancelled)
            $purchaseOrders = $this->odooService->searchRead(
                'purchase.order',
                [['state', '!=', 'cancel']],
                ['id', 'name', 'partner_id', 'amount_total', 'state'],
                50
            );

            $this->view->invoices = $customerInvoices; // Backward compatibility (if needed)
            $this->view->customerInvoices = $customerInvoices;
            $this->view->vendorBills = $vendorBills;
            $this->view->customers = $partners; // Send mixed partners as 'customers' for now
            $this->view->products = $products;
            $this->view->salesOrders = $salesOrders;
            $this->view->purchaseOrders = $purchaseOrders;
        } catch (\Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->invoices = [];
            $this->view->customers = [];
            $this->view->products = [];
            $this->view->salesOrders = [];
            $this->view->purchaseOrders = [];
        }
    }

    /**
     * Get order details (lines and partner) for invoice creation
     */
    public function getOrderDetailsAction()
    {
        $this->view->disable();
        if (!$this->request->isGet()) {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        try {
            $type = $this->request->getQuery('type', 'string'); // 'sale' or 'purchase'
            $id = (int)$this->request->getQuery('id', 'int');

            if (!$type || !$id) {
                throw new \Exception('Invalid parameters');
            }

            $lines = [];
            $partnerId = 0;

            if ($type === 'sale') {
                // Get SO details
                $order = $this->odooService->searchRead(
                    'sale.order',
                    [['id', '=', $id]],
                    ['partner_id', 'order_line'],
                    1
                );

                if (empty($order)) throw new \Exception('Sales Order not found');
                
                $partnerId = is_array($order[0]['partner_id']) ? $order[0]['partner_id'][0] : $order[0]['partner_id'];
                
                // Get Lines
                $orderLines = $this->odooService->searchRead(
                    'sale.order.line',
                    [['order_id', '=', $id]],
                    ['product_id', 'product_uom_qty', 'price_unit', 'name']
                );

                foreach ($orderLines as $line) {
                    $productId = is_array($line['product_id']) ? $line['product_id'][0] : $line['product_id'];
                    $lines[] = [
                        'product_id' => $productId,
                        'param_qty' => $line['product_uom_qty'], // naming match for frontend
                        'price_unit' => $line['price_unit'],
                        'name' => $line['name']
                    ];
                }

            } elseif ($type === 'purchase') {
                // Get PO details
                $order = $this->odooService->searchRead(
                    'purchase.order',
                    [['id', '=', $id]],
                    ['partner_id', 'order_line'],
                    1
                );

                if (empty($order)) throw new \Exception('Purchase Order not found');

                $partnerId = is_array($order[0]['partner_id']) ? $order[0]['partner_id'][0] : $order[0]['partner_id'];

                // Get Lines
                $orderLines = $this->odooService->searchRead(
                    'purchase.order.line',
                    [['order_id', '=', $id]],
                    ['product_id', 'product_qty', 'price_unit', 'name']
                );

                foreach ($orderLines as $line) {
                    $productId = is_array($line['product_id']) ? $line['product_id'][0] : $line['product_id'];
                    $lines[] = [
                        'product_id' => $productId,
                        'param_qty' => $line['product_qty'],
                        'price_unit' => $line['price_unit'],
                        'name' => $line['name']
                    ];
                }
            } else {
                throw new \Exception('Invalid type');
            }

            echo json_encode([
                'success' => true,
                'partner_id' => $partnerId,
                'lines' => $lines
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Create invoice baru di Odoo
     */
    public function createInvoiceAction()
    {
        if ($this->request->isPost()) {
            try {
                $sourceType = $this->request->getPost('source_type');
                
                // Determine Invoice Type: 'out_invoice' (Customer) or 'in_invoice' (Vendor)
                // If Purchase Order -> Vendor Bill (in_invoice)
                // If Sales Order or Manual -> Customer Invoice (out_invoice)
                $moveType = ($sourceType === 'purchase') ? 'in_invoice' : 'out_invoice';

                // 1. Get Appropriate Journal (CRITICAL for correct behavior)
                // Odoo needs to know which Journal to use to set defaults correctly
                $journalType = ($moveType === 'in_invoice') ? 'purchase' : 'sale';
                $journals = $this->odooService->searchRead(
                    'account.journal',
                    [['type', '=', $journalType]],
                    ['id', 'default_account_id'],
                    1
                );
                
                if (empty($journals)) {
                    throw new \Exception("No Journal found for type '$journalType'. Please check Odoo Accounting configuration.");
                }

                $journalId = $journals[0]['id'];
                $journalDefaultAccount = null;
                if (isset($journals[0]['default_account_id']) && !empty($journals[0]['default_account_id'])) {
                    $journalDefaultAccount = is_array($journals[0]['default_account_id']) 
                        ? $journals[0]['default_account_id'][0] 
                        : $journals[0]['default_account_id'];
                }

                $data = [
                    'journal_id' => $journalId,
                    'partner_id' => (int)$this->request->getPost('partner_id'),
                    'move_type' => $moveType,
                    'invoice_date' => date('Y-m-d'), // Good practice to set date
                ];

                // Optional payment reference
                if ($this->request->getPost('payment_reference')) {
                    $data['payment_reference'] = $this->request->getPost('payment_reference');
                }

                // Prepare invoice lines
                $productIds = $this->request->getPost('product_ids');
                $quantities = $this->request->getPost('quantities');
                $prices = $this->request->getPost('prices'); // New field

                $invoiceLines = [];
                if (!empty($productIds) && is_array($productIds)) {
                    foreach ($productIds as $index => $productId) {
                        if (!empty($productId)) {
                            $qty = isset($quantities[$index]) ? (float)$quantities[$index] : 1;
                            $price = isset($prices[$index]) ? (float)$prices[$index] : 0;
                            
                            // Fetch product data for account details
                            $fieldsToFetch = ['name', 'categ_id'];
                            // If user didn't provide price, we need standard_price (cost) or list_price (sales)
                            if ($price <= 0) {
                                $fieldsToFetch[] = ($moveType === 'in_invoice') ? 'standard_price' : 'list_price';
                            }
                            
                            // Fetch appropriate account
                            if ($moveType === 'in_invoice') {
                                $fieldsToFetch[] = 'property_account_expense_id';
                                $fieldsToFetch[] = 'categ_id'; // Fallback to category
                            } else {
                                $fieldsToFetch[] = 'property_account_income_id';
                                $fieldsToFetch[] = 'categ_id';
                            }

                            $products = $this->odooService->searchRead(
                                'product.product',
                                [['id', '=', (int)$productId]],
                                $fieldsToFetch,
                                1
                            );

                            if (!empty($products)) {
                                $product = $products[0];
                                
                                // Determine Price if not provided
                                if ($price <= 0) {
                                    if ($moveType === 'in_invoice') {
                                        $price = $product['standard_price'] ?? 0;
                                    } else {
                                        $price = $product['list_price'] ?? 0;
                                    }
                                }
                                
                                // Ensure price is reasonable (max 1 trillion)
                                if ($price > 1000000000000) {
                                    $price = 0; // Reset to let Odoo use product default
                                }

                                // Build invoice line
                                $line = [
                                    'product_id' => (int)$productId,
                                    'quantity' => $qty,
                                    'name' => $product['name'] ?? 'Item',
                                ];
                                
                                // Only set price_unit if we have a valid price
                                if ($price > 0) {
                                    $line['price_unit'] = $price;
                                }

                                // Account Logic - Find account_id BEFORE adding to array
                                $accountId = null;
                                // 1. Check Product level account
                                if ($moveType === 'in_invoice') {
                                    if (isset($product['property_account_expense_id']) && !empty($product['property_account_expense_id'])) {
                                        $accountId = is_array($product['property_account_expense_id']) 
                                            ? $product['property_account_expense_id'][0] 
                                            : $product['property_account_expense_id'];
                                    }
                                } else {
                                    if (isset($product['property_account_income_id']) && !empty($product['property_account_income_id'])) {
                                        $accountId = is_array($product['property_account_income_id'])
                                            ? $product['property_account_income_id'][0]
                                            : $product['property_account_income_id'];
                                    }
                                }

                                // 2. If not found, check Category level account (Standard Odoo behavior)
                                if (!$accountId && isset($product['categ_id']) && !empty($product['categ_id'])) {
                                    $categId = is_array($product['categ_id']) ? $product['categ_id'][0] : $product['categ_id'];
                                    
                                    // Fetch Category
                                    $categField = ($moveType === 'in_invoice') ? 'property_account_expense_categ_id' : 'property_account_income_categ_id';
                                    $category = $this->odooService->searchRead(
                                        'product.category',
                                        [['id', '=', (int)$categId]],
                                        [$categField],
                                        1
                                    );

                                    if (!empty($category) && isset($category[0][$categField]) && !empty($category[0][$categField])) {
                                        $acc = $category[0][$categField];
                                        $accountId = is_array($acc) ? $acc[0] : $acc;
                                    }
                                }
                                
                                // 3. FALLBACK: Search by account_type (works better in Odoo 17+)
                                if (!$accountId) {
                                    // Use account_type instead of code prefix
                                    $accountType = ($moveType === 'in_invoice') ? 'expense' : 'income';
                                    $fallbackAccounts = $this->odooService->searchRead(
                                        'account.account',
                                        [['account_type', '=', $accountType]],
                                        ['id'],
                                        1
                                    );
                                    if (!empty($fallbackAccounts)) {
                                        $accountId = $fallbackAccounts[0]['id'];
                                    }
                                }

                                // 4. Last resort: Use journal's default account
                                if (!$accountId && $journalDefaultAccount) {
                                    $accountId = $journalDefaultAccount;
                                }

                                // Set account_id - REQUIRED for balanced entry
                                if ($accountId) {
                                    $line['account_id'] = (int)$accountId;
                                }

                                // NOW add the complete line to array
                                $invoiceLines[] = [0, 0, $line];
                            }
                        }
                    }
                }
                if (!empty($invoiceLines)) {
                    $data['invoice_line_ids'] = $invoiceLines;
                }

                // DEBUG: Log what we're sending to Odoo
                error_log('=== INVOICE CREATE DEBUG ===');
                error_log('Data being sent: ' . json_encode($data, JSON_PRETTY_PRINT));

                $invoiceId = $this->odooService->create('account.move', $data);

                return $this->response->setJsonContent([
                    'success' => true,
                    'invoiceId' => $invoiceId,
                    'message' => ($moveType === 'in_invoice' ? 'Vendor Bill' : 'Customer Invoice') . ' berhasil dibuat'
                ]);
            } catch (\Exception $e) {
                // Log detailed error for debugging
                error_log('Invoice Creation Error: ' . $e->getMessage());
                error_log('Data attempted: ' . json_encode($data ?? [], JSON_PRETTY_PRINT));
                
                return $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'Odoo create error: ' . $e->getMessage(),
                    'debug' => isset($data) ? $data : null
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

    /**
     * Helper to auto-create invoice from SO/PO
     */
    private function _createInvoiceFromOrder($orderId, $sourceType)
    {
        try {
            // 1. Determine Models
            if ($sourceType === 'sale') {
                $orderModel = 'sale.order';
                $lineModel = 'sale.order.line';
                $moveType = 'out_invoice';
                $qtyField = 'product_uom_qty';
                $journalType = 'sale';
            } else {
                $orderModel = 'purchase.order';
                $lineModel = 'purchase.order.line';
                $moveType = 'in_invoice';
                $qtyField = 'product_qty'; // Note: different field name for PO
                $journalType = 'purchase';
            }

            // 2. Fetch Order Data
            $orders = $this->odooService->searchRead(
                $orderModel,
                [['id', '=', (int)$orderId]],
                ['name', 'partner_id', 'order_line'],
                1
            );

            if (empty($orders)) return ['success' => false, 'message' => 'Order not found'];
            $order = $orders[0];

            // 3. Fetch Order Lines
            $lineIds = $order['order_line'];
            if (empty($lineIds)) return ['success' => false, 'message' => 'Order has no lines'];

            $lines = $this->odooService->searchRead(
                $lineModel,
                [['id', 'in', $lineIds]],
                ['product_id', 'name', 'price_unit', $qtyField]
            );

            // 4. Get Journal
            $journals = $this->odooService->searchRead(
                'account.journal',
                [['type', '=', $journalType]],
                ['id', 'default_account_id'],
                1
            );
            
            if (empty($journals)) throw new \Exception("Journal $journalType not found");
            
            $journalId = $journals[0]['id'];
            $journalDefaultAccount = isset($journals[0]['default_account_id']) && $journals[0]['default_account_id'] 
                ? (is_array($journals[0]['default_account_id']) ? $journals[0]['default_account_id'][0] : $journals[0]['default_account_id'])
                : null;

            // 5. Build Invoice Data
            $invoiceData = [
                'move_type' => $moveType,
                'partner_id' => is_array($order['partner_id']) ? $order['partner_id'][0] : $order['partner_id'],
                'invoice_date' => date('Y-m-d'),
                'journal_id' => $journalId,
                'invoice_origin' => $order['name'], // Link to SO/PO
            ];

            // 6. Build Invoice Lines (Finding Accounts)
            $invoiceLines = [];
            foreach ($lines as $line) {
                // Get Product ID
                $prodId = is_array($line['product_id']) ? $line['product_id'][0] : $line['product_id'];
                
                // Fetch Product for Accounts
                $fieldsToFetch = ['name', 'categ_id'];
                if ($moveType === 'in_invoice') {
                    $fieldsToFetch[] = 'property_account_expense_id';
                } else {
                    $fieldsToFetch[] = 'property_account_income_id';
                }
                
                $products = $this->odooService->searchRead('product.product', [['id', '=', $prodId]], $fieldsToFetch, 1);
                if (empty($products)) continue;
                $product = $products[0];

                // === Logic Pencarian Account (Copy dari createInvoiceAction) ===
                $accountId = null;
                // A. Product
                if ($moveType === 'in_invoice' && !empty($product['property_account_expense_id'])) {
                    $accountId = is_array($product['property_account_expense_id']) ? $product['property_account_expense_id'][0] : $product['property_account_expense_id'];
                } elseif ($moveType !== 'in_invoice' && !empty($product['property_account_income_id'])) {
                    $accountId = is_array($product['property_account_income_id']) ? $product['property_account_income_id'][0] : $product['property_account_income_id'];
                }

                // B. Category
                if (!$accountId && !empty($product['categ_id'])) {
                     $categId = is_array($product['categ_id']) ? $product['categ_id'][0] : $product['categ_id'];
                     $categField = ($moveType === 'in_invoice') ? 'property_account_expense_categ_id' : 'property_account_income_categ_id';
                     $cats = $this->odooService->searchRead('product.category', [['id', '=', $categId]], [$categField], 1);
                     if (!empty($cats) && !empty($cats[0][$categField])) {
                         $ac = $cats[0][$categField];
                         $accountId = is_array($ac) ? $ac[0] : $ac;
                     }
                }

                // C. Account Type
                if (!$accountId) {
                    $accType = ($moveType === 'in_invoice') ? 'expense' : 'income';
                    $accs = $this->odooService->searchRead('account.account', [['account_type', '=', $accType]], ['id'], 1);
                    if (!empty($accs)) $accountId = $accs[0]['id'];
                }

                // D. Journal Default
                if (!$accountId && $journalDefaultAccount) {
                    $accountId = $journalDefaultAccount;
                }

                if (!$accountId) throw new \Exception("No account found for product " . $product['name']);

                // Prepare Line
                $invLine = [
                    'product_id' => $prodId,
                    'quantity' => $line[$qtyField],
                    'price_unit' => $line['price_unit'],
                    'name' => $line['name'], // Description from order
                    'account_id' => $accountId
                ];

                $invoiceLines[] = [0, 0, $invLine];
            }

            if (!empty($invoiceLines)) {
                $invoiceData['invoice_line_ids'] = $invoiceLines;
                
                // Debug log
                error_log("Auto Invoice Data: " . json_encode($invoiceData));
                
                $invoiceId = $this->odooService->create('account.move', $invoiceData);
                return ['success' => true, 'id' => $invoiceId];
            }
            
            return ['success' => false, 'message' => 'No lines generated'];

        } catch (\Exception $e) {
            error_log("Auto Invoice Failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
