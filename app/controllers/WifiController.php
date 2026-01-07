<?php
/**
 * ============================================
 * STEP 5: CONTROLLER
 * Controller: WifiController
 * ============================================
 * 
 * Controller ini menangani semua request terkait
 * generate password WiFi.
 */

use Phalcon\Mvc\Controller;

class WifiController extends Controller
{
    /**
     * Action: index
     * URL: /wifi atau /wifi/index
     * 
     * Menampilkan form input nama user
     */
    public function indexAction()
    {
        // Set judul halaman (bisa diakses di view)
        $this->view->setVar('pageTitle', 'Generate Password WiFi');
    }

    /**
     * Action: generate
     * URL: /wifi/generate (POST)
     * 
     * Memproses form, generate password, simpan ke database
     */
    public function generateAction()
    {
        // Pastikan request adalah POST
        if (!$this->request->isPost()) {
            // Redirect ke form jika bukan POST
            return $this->response->redirect('wifi');
        }

        // =====================
        // STEP 7: VALIDASI INPUT
        // =====================
        
        // Ambil input nama dari form
        $userName = $this->request->getPost('user_name', 'striptags');
        $userName = trim($userName); // Hapus spasi di awal/akhir
        
        // Ambil durasi hari (default 7 hari)
        $days = (int) $this->request->getPost('days', 'int');
        if ($days < 1 || $days > 30) {
            $days = 7; // Default 7 hari jika tidak valid
        }

        // Validasi: Nama tidak boleh kosong
        if (empty($userName)) {
            $this->view->setVar('error', 'Nama tidak boleh kosong!');
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index'); // Kembali ke form
            return;
        }

        // Validasi: Nama minimal 3 karakter
        if (strlen($userName) < 3) {
            $this->view->setVar('error', 'Nama minimal 3 karakter!');
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index');
            return;
        }

        // Validasi: Nama maksimal 100 karakter
        if (strlen($userName) > 100) {
            $this->view->setVar('error', 'Nama maksimal 100 karakter!');
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index');
            return;
        }

        // Validasi: Nama hanya boleh huruf, angka, dan spasi
        if (!preg_match('/^[a-zA-Z0-9\s]+$/', $userName)) {
            $this->view->setVar('error', 'Nama hanya boleh huruf, angka, dan spasi!');
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index');
            return;
        }

        // Validasi: Cek apakah username sudah ada yang aktif
        $existingUser = WifiPassword::findFirst([
            'conditions' => 'user_name = :name: AND is_active = 1 AND expired_at > NOW()',
            'bind' => ['name' => $userName]
        ]);

        if ($existingUser) {
            $expiredDate = date('d M Y, H:i', strtotime($existingUser->expired_at));
            $this->view->setVar('error', "Username '{$userName}' sudah memiliki password aktif yang berlaku sampai {$expiredDate}. Silakan gunakan nama lain atau tunggu hingga password lama expired.");
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index');
            return;
        }

        // =====================
        // GENERATE PASSWORD
        // =====================
        
        // Generate password random 8-10 karakter
        $passwordLength = random_int(8, 10);
        $password = WifiPassword::generateRandomPassword($passwordLength);

        // =====================
        // SIMPAN KE DATABASE
        // =====================
        
        // Buat instance model baru
        $wifiPassword = new WifiPassword();
        $wifiPassword->user_name = $userName;
        $wifiPassword->password = $password;
        $wifiPassword->expired_at = WifiPassword::calculateExpiredDate($days);
        
        // Simpan ke database
        if ($wifiPassword->save()) {
            // Berhasil disimpan, tampilkan hasil
            $this->view->setVar('pageTitle', 'Password WiFi Anda');
            $this->view->setVar('wifiPassword', $wifiPassword);
            $this->view->setVar('formattedExpired', WifiPassword::formatDate($wifiPassword->expired_at));
            $this->view->setVar('formattedCreated', WifiPassword::formatDate($wifiPassword->created_at));
            $this->view->pick('wifi/result');
        } else {
            // Gagal simpan, tampilkan error
            $messages = $wifiPassword->getMessages();
            $errorMsg = 'Gagal menyimpan password. ';
            foreach ($messages as $message) {
                $errorMsg .= $message->getMessage() . ' ';
            }
            $this->view->setVar('error', $errorMsg);
            $this->view->setVar('pageTitle', 'Generate Password WiFi');
            $this->view->pick('wifi/index');
        }
    }

    /**
     * Action: history
     * URL: /wifi/history
     * 
     * Menampilkan daftar password yang pernah digenerate (opsional)
     */
    public function historyAction()
    {
        // Ambil semua password, urutkan dari terbaru
        $passwords = WifiPassword::find([
            'order' => 'created_at DESC',
            'limit' => 20
        ]);

        $this->view->setVar('pageTitle', 'Riwayat Password WiFi');
        $this->view->setVar('passwords', $passwords);
    }
}
