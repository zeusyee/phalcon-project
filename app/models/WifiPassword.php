<?php
/**
 * ============================================
 * STEP 4: MODEL PHALCON ORM
 * Model: WifiPassword
 * ============================================
 * 
 * Model ini merepresentasikan tabel wifi_passwords di database.
 * Menggunakan Phalcon ORM untuk mapping otomatis.
 */

use Phalcon\Mvc\Model;

class WifiPassword extends Model
{
    /**
     * ID unik (auto increment)
     * @var int
     */
    public $id;

    /**
     * Nama user yang request password
     * @var string
     */
    public $user_name;

    /**
     * Password WiFi yang digenerate
     * @var string
     */
    public $password;

    /**
     * Waktu pembuatan password
     * @var string
     */
    public $created_at;

    /**
     * Waktu kedaluwarsa password
     * @var string
     */
    public $expired_at;

    /**
     * Status aktif (1=aktif, 0=nonaktif)
     * @var int
     */
    public $is_active;

    /**
     * Initialize model
     * Menentukan nama tabel di database
     */
    public function initialize(): void
    {
        // Nama tabel di database
        $this->setSource('wifi_passwords');
    }

    /**
     * Generate password acak
     * 
     * @param int $length Panjang password (default 8)
     * @return string Password random
     */
    public static function generateRandomPassword(int $length = 8): string
    {
        // Karakter yang digunakan untuk password
        // Menghindari karakter yang mirip (0, O, l, 1, I)
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        
        $password = '';
        $charLength = strlen($characters);
        
        // Loop untuk mengambil karakter random
        for ($i = 0; $i < $length; $i++) {
            // random_int() lebih aman daripada rand()
            $password .= $characters[random_int(0, $charLength - 1)];
        }
        
        return $password;
    }

    /**
     * Hitung tanggal expired
     * 
     * @param int $days Jumlah hari aktif (default 7 hari)
     * @return string Format datetime
     */
    public static function calculateExpiredDate(int $days = 7): string
    {
        // Tambahkan jumlah hari ke waktu sekarang
        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }

    /**
     * Cek apakah password sudah expired
     * 
     * @return bool true jika expired
     */
    public function isExpired(): bool
    {
        return strtotime($this->expired_at) < time();
    }

    /**
     * Format tanggal untuk tampilan
     * 
     * @param string $date Tanggal dalam format datetime
     * @return string Tanggal yang diformat
     */
    public static function formatDate(string $date): string
    {
        return date('d M Y, H:i', strtotime($date));
    }

    /**
     * Validasi sebelum create
     * Dipanggil otomatis oleh Phalcon sebelum save
     */
    public function beforeCreate(): void
    {
        // Set waktu pembuatan
        $this->created_at = date('Y-m-d H:i:s');
        
        // Set status aktif
        $this->is_active = 1;
    }
}
