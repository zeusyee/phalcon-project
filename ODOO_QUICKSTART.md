# Quick Start - Odoo Integration

## 1. Start Docker Containers

```powershell
# Start all services including Odoo
docker-compose up -d

# Check if all containers are running
docker-compose ps
```

## 2. Setup Odoo (First Time Only)

1. Open browser: http://localhost:8069
2. Fill the database creation form:
   - **Master Password**: admin
   - **Database Name**: odoo
   - **Email**: admin@example.com
   - **Password**: admin (change this later!)
   - **Language**: Indonesian
   - **Country**: Indonesia
3. Click "Create Database"
4. Wait for installation (may take 2-3 minutes)

## 3. Install Composer Dependencies

```powershell
# Install Guzzle for HTTP client
docker-compose exec app composer require guzzlehttp/guzzle
```

## 4. Update Router

Add Odoo routes to your router:

```php
// app/config/router.php
$router->add('/odoo', [
    'controller' => 'odoo',
    'action' => 'index'
]);

$router->add('/odoo/test', [
    'controller' => 'odoo',
    'action' => 'testConnection'
]);

$router->add('/odoo/products', [
    'controller' => 'odoo',
    'action' => 'products'
]);

$router->add('/odoo/create-customer', [
    'controller' => 'odoo',
    'action' => 'createCustomer'
]);
```

## 5. Test Connection

```powershell
# Test Odoo connection from browser
# Visit: http://localhost:8181/odoo/test
```

Expected response:
```json
{
  "connected": true,
  "user_id": 2,
  "message": "Successfully connected to Odoo!"
}
```

## 6. Environment Variables (Optional)

Create `.env` file or add to existing:

```env
# Odoo Configuration
ODOO_PORT=8069
ODOO_DB_PASSWORD=odoo123
ODOO_MASTER_PASSWORD=admin

# Odoo API Credentials (after setup)
ODOO_URL=http://odoo:8069
ODOO_DB=odoo
ODOO_USERNAME=admin@example.com
ODOO_PASSWORD=admin
```

## Common Endpoints

- **Odoo Web**: http://localhost:8069
- **Phalcon App**: http://localhost:8181
- **Test Connection**: http://localhost:8181/odoo/test
- **List Customers**: http://localhost:8181/odoo
- **List Products**: http://localhost:8181/odoo/products

## Troubleshooting

### Odoo not accessible
```powershell
docker-compose logs odoo
docker-compose restart odoo
```

### Phalcon can't connect to Odoo
Make sure using service name `odoo` not `localhost`:
```php
'url' => 'http://odoo:8069'  // Correct
```

### Permission errors
```powershell
docker-compose exec app chown -R www-data:www-data /var/www/html
```

## Next Steps

1. Explore Odoo modules: http://localhost:8069/web#menu_id=5&action=10
2. Create test customers in Odoo
3. Test API from Phalcon: http://localhost:8181/odoo
4. Read full documentation: [ODOO_INTEGRATION.md](ODOO_INTEGRATION.md)
