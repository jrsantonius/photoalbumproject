# The Innovators Studio — Album Foto Cetak Custom

Aplikasi web pemesanan album foto cetak custom. Backend PHP native, frontend HTML/CSS/JS vanilla
dalam satu file (`index.html`). Tidak butuh Composer, npm, atau database.

## Menjalankan

```bash
cd album-app
php -d upload_max_filesize=12M -d post_max_size=130M -S localhost:8000
```

Buka http://localhost:8000

**Penting:** jangan jalankan `php -S` tanpa flag `-d` di atas. PHP bawaan membatasi upload ke
2MB per file (`upload_max_filesize`) dan 8MB per request (`post_max_size`) — jauh di bawah batas
10MB/foto yang diklaim aplikasi. Kalau batas itu terlampaui, PHP menolak request sebelum skrip kita
jalan dan mencampur warning HTML ke response, sehingga `fetch()` di frontend gagal parsing JSON dan
menampilkan "Tidak bisa terhubung ke server."

Untuk shared hosting / cPanel: unggah seluruh isi folder ke `public_html`. Tidak ada langkah build.
File `.user.ini` di root sudah mengatur limit yang sama untuk PHP-FPM/CGI (dipakai hampir semua
shared hosting) — tidak perlu konfigurasi tambahan.

## Konfigurasi

Salin `.env.example` menjadi `.env` lalu isi:

| Key | Keterangan |
|---|---|
| `MAX_PHOTOS` | Batas foto per album (default 40) |
| `MAX_FILE_SIZE_MB` | Batas ukuran per foto (4MB di Vercel) |

Alur album memakai dua paket: Tipis (10 lembar/20 halaman/20 foto) dan Tebal
(20 lembar/40 halaman/40 foto). Pengguna dapat mulai setelah mengunggah minimum
10 foto, lalu melengkapinya sesuai paket sebelum membuat pesanan.

Pembayaran online dinonaktifkan sementara. Pesanan dicatat dan ditindaklanjuti manual melalui WhatsApp.

## Endpoint

| Endpoint | Method | Fungsi |
|---|---|---|
| `api/upload.php` | POST | Upload foto (multipart, header `X-Session-Id`) |
| `api/delete-photo.php` | POST | Hapus satu foto |
| `api/orders.php` | POST | Buat pesanan manual |
| `api/order-status.php` | GET | Status pesanan (`?orderId=TIS-xxx`) |
| `api/webhook.php` | POST | Endpoint legacy, tidak digunakan sementara |
| `api/pricing.php` | GET | Tabel harga |

## Penyimpanan

- `uploads/{sessionId}/` — foto pengguna
- `orders/{orderId}.json` — data pesanan

Kedua folder dibuat otomatis oleh `config.php` dengan permission 755. Keduanya berisi `.htaccess`
untuk memblokir eksekusi skrip (uploads) dan akses langsung dari browser (orders); file ini hanya
berpengaruh di Apache.

## Harga

Ukuran × cover: A5 150rb/200rb · A4 200rb/280rb · A3 300rb/400rb (softcover/hardcover).
Surcharge kertas: matte +0 · satin +15rb · glossy +20rb.
Ongkir: J&T 12rb · SiCepat 13rb · JNE Reg 15rb · GoSend 20rb · JNE YES 25rb.

Harga selalu dihitung ulang di server (`calc_total()` di `config.php`); nilai dari frontend tidak dipercaya.
# photoalbumproject
