# 🏠 Web Jimpitan Warga — Panduan Proyek

## Struktur File

```
jimpitan-web/
├── style.css               ← Design system (warna, tombol, card, tabel, dll)
├── login.html              ← Halaman login (warga & admin)
├── warga-dashboard.html    ← Dashboard warga + form setoran (modal)
├── admin-dashboard.html    ← Panel admin + rekap kas + data tunggakan
└── admin-verifikasi.html   ← Antrian verifikasi dokumen bukti bayar
```

## Cara Buka (Tanpa Backend)

Cukup buka `login.html` di browser. Gunakan:
- Warga  → username: `warga` / password: `1234`
- Admin  → username: `admin` / password: `admin`

Semua halaman sudah terhubung satu sama lain.

---

## Cara Sambungkan ke Backend (nanti)

Setiap fungsi yang perlu API sudah diberi komentar `// TODO` di dalam script.
Cari komentar itu lalu ganti dengan `fetch()` ke endpoint yang dibuat tim backend.

### Contoh endpoint yang dibutuhkan:

| Aksi | Method | Endpoint |
|---|---|---|
| Login | POST | `/api/login` |
| Status bayar warga | GET | `/api/pembayaran?warga_id=...` |
| Kirim setoran + bukti | POST | `/api/setoran` (FormData) |
| Rekap kas admin | GET | `/api/admin/rekap` |
| List warga tunggak | GET | `/api/admin/warga-tunggak` |
| List dokumen pending | GET | `/api/admin/dokumen` |
| Approve dokumen | POST | `/api/admin/verifikasi/:id` |
| Tolak dokumen | POST | `/api/admin/tolak/:id` |

### Contoh penggunaan fetch (template):

```js
// Contoh kirim setoran dengan file
const form = new FormData();
form.append('bulan', 'November');
form.append('tahun', '2025');
form.append('nominal', 6000);
form.append('catatan', 'Transfer BRI');
form.append('file', fileInput.files[0]);

const res = await fetch('/api/setoran', {
  method: 'POST',
  headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
  body: form
});
const data = await res.json();
```

---

## Tabel Database (untuk PowerDesigner)

```
warga         : id, nama_kk, no_rumah, username, password_hash, rt_id
pembayaran    : id, warga_id, bulan, tahun, nominal, status, tgl_bayar
dokumen_bukti : id, pembayaran_id, file_path, catatan, tgl_upload
verifikasi    : id, dokumen_id, admin_id, status, tgl_verifikasi, keterangan_tolak
admin         : id, nama, username, password_hash, rt_id
```

Status pembayaran: `belum` | `pending` | `lunas` | `ditolak`

---

## Teknologi Frontend

- HTML5 + CSS3 + Vanilla JavaScript
- Font: Plus Jakarta Sans (Google Fonts)
- Tidak ada dependency/framework (tidak perlu npm/node)
- Responsive untuk mobile dan desktop

---

## Pengembangan Selanjutnya

- [ ] Sambungkan login ke API (JWT/session)
- [ ] Fetch data dinamis dari database
- [ ] Kirim notifikasi (WhatsApp/email) ke warga belum bayar
- [ ] Ekspor laporan kas ke Excel (dari backend)
- [ ] Halaman tambah warga baru (admin)
