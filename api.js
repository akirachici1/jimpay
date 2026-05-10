/**
 * API Service untuk Web Jimpitan Warga
 * 
 * Set USE_MOCK ke `false` jika backend sudah siap,
 * dan sesuaikan BASE_URL ke endpoint backend yang sebenarnya.
 */

const USE_MOCK = false;
const BASE_URL = '/backend/api';

// --- INITIALIZE MOCK DB ---
function initMockDB() {
  if (!localStorage.getItem('jimpitan_warga')) {
    localStorage.setItem('jimpitan_warga', JSON.stringify([
      { id: 1, nama_kk: 'Sugiyanto Wibowo', no_rumah: 'No. 12', inisial: 'SW', username: 'warga', password: '123' },
      { id: 2, nama_kk: 'Hartono Budiman', no_rumah: 'No. 23', inisial: 'HB', username: 'hartono', password: '123' },
      { id: 3, nama_kk: 'Dwi Rahayu', no_rumah: 'No. 7', inisial: 'DR', username: 'dwi', password: '123' },
      { id: 4, nama_kk: 'Parimin S.', no_rumah: 'No. 33', inisial: 'PS', username: 'parimin', password: '123' }
    ]));
  }
  if (!localStorage.getItem('jimpitan_pembayaran')) {
    // bulan: 1-12 (1 = Jan, 12 = Des)
    // status: lunas, belum, pending, ditolak
    const mockPembayaran = [];
    const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    
    // Setup for Warga 1 (Sugiyanto) - Lunas Jan-Sep, Pending Okt, Belum Nov
    for(let i=1; i<=9; i++) mockPembayaran.push({ id: `p1_${i}`, warga_id: 1, bulan: i, nama_bulan: bulanNames[i-1], tahun: 2025, nominal: 6000, status: 'lunas', tgl_bayar: `05 ${bulanNames[i-1]} 2025` });
    mockPembayaran.push({ id: 'p1_10', warga_id: 1, bulan: 10, nama_bulan: 'Okt', tahun: 2025, nominal: 6000, status: 'pending', tgl_bayar: '18 Okt 2025', catatan: 'Bayar lewat transfer BRI', file_name: 'bukti_tf.jpg' });

    // Setup for Warga 2 (Hartono) - Lunas Jan-Ags, Belum Sep-Nov
    for(let i=1; i<=8; i++) mockPembayaran.push({ id: `p2_${i}`, warga_id: 2, bulan: i, nama_bulan: bulanNames[i-1], tahun: 2025, nominal: 6000, status: 'lunas', tgl_bayar: `10 ${bulanNames[i-1]} 2025` });

    // Setup for Warga 3 (Dwi) - Lunas Jan-Okt, Belum Nov
    for(let i=1; i<=10; i++) mockPembayaran.push({ id: `p3_${i}`, warga_id: 3, bulan: i, nama_bulan: bulanNames[i-1], tahun: 2025, nominal: 6000, status: 'lunas', tgl_bayar: `01 ${bulanNames[i-1]} 2025` });

    // Setup for Warga 4 (Parimin) - Lunas Jan-Sep, Belum Okt-Nov
    for(let i=1; i<=9; i++) mockPembayaran.push({ id: `p4_${i}`, warga_id: 4, bulan: i, nama_bulan: bulanNames[i-1], tahun: 2025, nominal: 6000, status: 'lunas', tgl_bayar: `15 ${bulanNames[i-1]} 2025` });

    localStorage.setItem('jimpitan_pembayaran', JSON.stringify(mockPembayaran));
  }
  if (!localStorage.getItem('jimpitan_pengeluaran')) {
    localStorage.setItem('jimpitan_pengeluaran', JSON.stringify([
      { id: 1, keterangan: 'Beli Sapu Lidi & Pengki', nominal: 35000, tanggal: '10 Nov 2025' },
      { id: 2, keterangan: 'Perbaikan Lampu Gapura', nominal: 120000, tanggal: '02 Nov 2025' }
    ]));
  }
}
if (USE_MOCK) initMockDB();

// --- HELPER UNTUK MOCK ---
const getMockData = (key) => JSON.parse(localStorage.getItem(key)) || [];
const setMockData = (key, data) => localStorage.setItem(key, JSON.stringify(data));
const delay = (ms) => new Promise(res => setTimeout(res, ms));

// --- API FUNCTIONS ---

async function apiLogin(username, password, role) {
  if (USE_MOCK) {
    await delay(500); // simulasi network
    if (role === 'admin' && username === 'admin' && password === 'admin') {
      const token = 'mock_token_admin_123';
      const user = { id: 99, role: 'admin', nama: 'Ketua RT 06' };
      localStorage.setItem('auth_token', token);
      localStorage.setItem('auth_user', JSON.stringify(user));
      return { success: true, token, user };
    }
    
    if (role === 'warga') {
      const wargas = getMockData('jimpitan_warga');
      // allow '1234' for 'warga' based on original instructions, or the db pass '123'
      const warga = wargas.find(w => w.username === username && (w.password === password || (username === 'warga' && password === '1234')));
      if (warga) {
        const token = 'mock_token_warga_' + warga.id;
        const user = { id: warga.id, role: 'warga', nama: warga.nama_kk, rumah: warga.no_rumah, inisial: warga.inisial };
        localStorage.setItem('auth_token', token);
        localStorage.setItem('auth_user', JSON.stringify(user));
        return { success: true, token, user };
      }
    }
    throw new Error('Username atau password salah');
  } else {
    const res = await fetch(`${BASE_URL}/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password, role })
    });
    if (!res.ok) throw new Error('Login failed');
    const data = await res.json();
    if (data.success) {
      localStorage.setItem('auth_token', data.token);
      localStorage.setItem('auth_user', JSON.stringify(data.user));
    }
    return data;
  }
}

function apiLogout() {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('auth_user');
  window.location.href = 'login.html';
}

function getAuthUser() {
  const user = localStorage.getItem('auth_user');
  return user ? JSON.parse(user) : null;
}

function getAuthHeaders() {
  const token = localStorage.getItem('auth_token');
  return token ? { Authorization: 'Bearer ' + token } : {};
}

async function fetchJson(url, options = {}) {
  options.headers = {
    ...(options.headers || {}),
    ...getAuthHeaders()
  };

  const res = await fetch(url, options);

  const contentType = res.headers.get('Content-Type') || '';
  if (contentType.includes('application/json')) {
    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.message || data.error || 'Request failed');
    }
    return data;
  }

  if (!res.ok) {
    throw new Error('Request failed with status ' + res.status);
  }
  return await res.text();
}

// --- API WARGA ---

async function apiGetWargaDashboard(wargaId, tahun = new Date().getFullYear()) {
  if (USE_MOCK) {
    await delay(300);
    const pembayaran = getMockData('jimpitan_pembayaran').filter(p => p.warga_id === wargaId && p.tahun === tahun);
    const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    
    // Asumsi bulan berjalan adalah November (11)
    const currentMonth = 11;
    
    let lunas = 0, belum = 0, pending = 0, totalNominal = 0;
    const grid = [];
    
    for (let i = 1; i <= 12; i++) {
      const bayar = pembayaran.find(p => p.bulan === i);
      let status = 'upcoming'; // default for future months
      
      if (bayar) {
        status = bayar.status; // lunas, pending, ditolak
        if (status === 'lunas') { lunas++; totalNominal += bayar.nominal; }
        if (status === 'pending') pending++;
        if (status === 'ditolak') belum++;
      } else if (i <= currentMonth) {
        status = 'belum';
        belum++;
      }
      
      grid.push({
        bulan: i,
        nama_bulan: bulanNames[i-1],
        status: status,
        nominal: 6000
      });
    }

    const riwayat = pembayaran
      .filter(p => p.status !== 'belum')
      .sort((a,b) => b.bulan - a.bulan)
      .slice(0, 5); // top 5

    // Hitung total RT untuk Transparansi
    const allPembayaran = getMockData('jimpitan_pembayaran');
    const totalMasukRT = allPembayaran.filter(p => p.status === 'lunas').length * 6000;
    const pengeluaran = getMockData('jimpitan_pengeluaran');
    const totalPengeluaran = pengeluaran.reduce((sum, item) => sum + item.nominal, 0);
    const saldoBersih = totalMasukRT - totalPengeluaran;
      
    return {
      stats: { lunas, belum, pending, totalNominal, totalMasukRT, totalPengeluaran, saldoBersih },
      grid,
      riwayat
    };
  } else {
    return await fetchJson(`${BASE_URL}/warga_dashboard.php?warga_id=${wargaId}&tahun=${tahun}`);
  }
}

async function apiKirimSetoran(wargaId, bulanArr, fileObj, catatan, tglBayar) {
  if (USE_MOCK) {
    await delay(800);
    const pembayaran = getMockData('jimpitan_pembayaran');
    const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    
    bulanArr.forEach(b => {
      // hapus jika ada yg ditolak sebelumnya
      const existingIdx = pembayaran.findIndex(p => p.warga_id === wargaId && p.bulan === b);
      if (existingIdx > -1) pembayaran.splice(existingIdx, 1);
      
      pembayaran.push({
        id: `p_${Date.now()}_${b}`,
        warga_id: wargaId,
        bulan: b,
        nama_bulan: bulanNames[b-1],
        tahun: 2026,
        nominal: 6000,
        status: 'pending',
        tgl_bayar: tglBayar,
        catatan: catatan,
        file_name: fileObj ? fileObj.name : 'bukti.jpg'
      });
    });
    setMockData('jimpitan_pembayaran', pembayaran);
    return { success: true };
  } else {
    const formData = new FormData();
    formData.append('warga_id', wargaId);
    formData.append('bulan', JSON.stringify(bulanArr));
    formData.append('catatan', catatan);
    formData.append('tgl_bayar', tglBayar);
    if (fileObj) formData.append('bukti', fileObj);

    return await fetchJson(`${BASE_URL}/kirim_setoran.php`, {
      method: 'POST',
      body: formData
    });
  }
}

// --- API ADMIN ---

async function apiSetBayarTunai(wargaId, bulanArr, tglBayar) {
  if (USE_MOCK) {
    await delay(400);
    return { success: true };
  } else {
    return await fetchJson(`${BASE_URL}/bayar_tunai.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ warga_id: wargaId, bulan: bulanArr, tgl_bayar: tglBayar })
    });
  }
}

async function apiGetAdminDashboard() {
  if (USE_MOCK) {
    await delay(300);
    const wargas = getMockData('jimpitan_warga');
    const pembayaran = getMockData('jimpitan_pembayaran');
    const currentMonth = 11; // Nov
    
    // Rekap Kas
    const rekap = [];
    const bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    
    let totalMasuk = 0;
    for(let i=1; i<=currentMonth; i++) {
      const bayarBulanIni = pembayaran.filter(p => p.bulan === i && p.status === 'lunas');
      const lunasCount = bayarBulanIni.length;
      const terkumpul = lunasCount * 6000;
      totalMasuk += terkumpul;
      
      rekap.push({
        bulan: bulanNames[i-1],
        terkumpul: `Rp ${terkumpul.toLocaleString('id-ID')}`,
        lunas: lunasCount,
        total_kk: wargas.length,
        selesai: lunasCount === wargas.length
      });
    }

    // Tunggakan
    const tunggakan = [];
    wargas.forEach(w => {
      const bulanNunggak = [];
      for(let i=1; i<=currentMonth; i++) {
        const bayar = pembayaran.find(p => p.warga_id === w.id && p.bulan === i);
        if (!bayar || bayar.status === 'ditolak') {
          bulanNunggak.push(bulanNames[i-1].substring(0,3)); // Jan, Feb
        }
      }
      if (bulanNunggak.length > 0) {
        tunggakan.push({
          nama: w.nama_kk,
          rumah: w.no_rumah,
          inisial: w.inisial,
          bulan: bulanNunggak
        });
      }
    });

    const pendingCount = pembayaran.filter(p => p.status === 'pending').length;
    const lunasBulanIni = rekap[currentMonth-1].lunas;

    const pengeluaran = getMockData('jimpitan_pengeluaran');
    const totalPengeluaran = pengeluaran.reduce((sum, item) => sum + item.nominal, 0);
    const saldoBersih = totalMasuk - totalPengeluaran;

    return {
      stats: {
        total_kk: wargas.length,
        lunas_bulan_ini: lunasBulanIni,
        persen_lunas: Math.round((lunasBulanIni / wargas.length) * 100),
        belum_bayar: tunggakan.length,
        pending_count: pendingCount,
        total_masuk: totalMasuk,
        target_masuk: wargas.length * 6000 * 12,
        total_pengeluaran: totalPengeluaran,
        saldo_bersih: saldoBersih
      },
      rekap,
      tunggakan
    };
  } else {
    return await fetchJson(`${BASE_URL}/admin_dashboard.php`);
  }
}

async function apiTutupBuku(data) {
  return await fetchJson(`${BASE_URL}/tutup_buku.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
}

async function apiGetAdminVerifikasi() {
  if (USE_MOCK) {
    await delay(300);
    const wargas = getMockData('jimpitan_warga');
    const pembayaran = getMockData('jimpitan_pembayaran');
    
    // Ambil yang statusnya pending atau baru saja diverifikasi (mock purpose)
    const dokumen = pembayaran
      .filter(p => ['pending', 'ditolak', 'lunas'].includes(p.status) && p.file_name) // yg ada file nya
      .map(p => {
        const w = wargas.find(x => x.id === p.warga_id);
        return {
          id: p.id,
          warga: w ? w.nama_kk : 'Unknown',
          rumah: w ? w.no_rumah : '-',
          inisial: w ? w.inisial : '?',
          bulan: `${p.nama_bulan} ${p.tahun}`,
          nominal: `Rp ${p.nominal.toLocaleString('id-ID')}`,
          tanggal: p.tgl_bayar,
          catatan: p.catatan,
          status: p.status === 'lunas' ? 'terverifikasi' : p.status, // map lunas -> terverifikasi for UI
          alasan: p.alasan_tolak,
          emoji: p.file_name.endsWith('.pdf') ? '📄' : '🧾'
        };
      })
      .sort((a,b) => a.status === 'pending' ? -1 : 1); // pending on top
      
    return dokumen;
  } else {
    return await fetchJson(`${BASE_URL}/admin_verifikasi.php`);
  }
}

async function apiAksiVerifikasi(dokId, aksi, alasan = '') {
  if (USE_MOCK) {
    await delay(500);
    const pembayaran = getMockData('jimpitan_pembayaran');
    const idx = pembayaran.findIndex(p => p.id === dokId);
    if (idx > -1) {
      if (aksi === 'approve') {
        pembayaran[idx].status = 'lunas';
      } else if (aksi === 'reject') {
        pembayaran[idx].status = 'ditolak';
        pembayaran[idx].alasan_tolak = alasan;
      }
      setMockData('jimpitan_pembayaran', pembayaran);
      return { success: true };
    }
    throw new Error('Dokumen tidak ditemukan');
  } else {
    return await fetchJson(`${BASE_URL}/aksi_verifikasi.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ dokId, aksi, alasan })
    });
  }
}

async function apiGetWargaList() {
  if (USE_MOCK) {
    await delay(300);
    return getMockData('jimpitan_warga');
  } else {
    return await fetchJson(`${BASE_URL}/warga_list.php`);
  }
}

async function apiAddWarga(data) {
  if (USE_MOCK) {
    await delay(600);
    const wargas = getMockData('jimpitan_warga');
    const parts = data.nama_kk.split(' ');
    const inisial = parts.length > 1 ? parts[0][0]+parts[1][0] : parts[0].substring(0,2).toUpperCase();
    
    const newWarga = {
      id: Date.now(),
      nama_kk: data.nama_kk,
      no_rumah: data.no_rumah,
      inisial: inisial,
      username: data.username,
      password: data.password || '123'
    };
    wargas.push(newWarga);
    setMockData('jimpitan_warga', wargas);
    return { success: true, id: newWarga.id };
  } else {
    return await fetchJson(`${BASE_URL}/add_warga.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
  }
}

async function apiImportWarga(wargaDataArray) {
  if (USE_MOCK) {
    await delay(600);
    let wargas = getMockData('jimpitan_warga');
    for (let data of wargaDataArray) {
      wargas.push({
        id: Date.now() + Math.random(),
        nama_kk: data.nama,
        no_rumah: data.gang,
        inisial: data.nama.substring(0,2).toUpperCase(),
        username: data.username,
        password: data.password
      });
    }
    setMockData('jimpitan_warga', wargas);
    return { success: true, message: `${wargaDataArray.length} data imported in mock.` };
  } else {
    return await fetchJson(`${BASE_URL}/import_warga.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(wargaDataArray)
    });
  }
}
async function apiDeleteWarga(id) {
  if (USE_MOCK) {
    await delay(400);
    let wargas = getMockData('jimpitan_warga');
    wargas = wargas.filter(w => w.id !== id);
    setMockData('jimpitan_warga', wargas);
    return { success: true };
  } else {
    return await fetchJson(`${BASE_URL}/delete_warga.php?id=${id}`);
  }
}

// --- API PENGELUARAN ---

async function apiGetPengeluaranList(tahun = new Date().getFullYear()) {
  if (USE_MOCK) {
    await delay(300);
    return getMockData('jimpitan_pengeluaran').sort((a,b) => b.id - a.id);
  } else {
    return await fetchJson(`${BASE_URL}/pengeluaran_list.php?tahun=${tahun}`);
  }
}

async function apiAddPengeluaran(data) {
  if (USE_MOCK) {
    await delay(500);
    const pengeluaran = getMockData('jimpitan_pengeluaran');
    const newPengeluaran = {
      id: Date.now(),
      keterangan: data.keterangan,
      nominal: parseInt(data.nominal, 10),
      tanggal: data.tanggal
    };
    pengeluaran.push(newPengeluaran);
    setMockData('jimpitan_pengeluaran', pengeluaran);
    return { success: true, id: newPengeluaran.id };
  } else {
    return await fetchJson(`${BASE_URL}/add_pengeluaran.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
  }
}

async function apiDeletePengeluaran(id) {
  if (USE_MOCK) {
    await delay(400);
    let pengeluaran = getMockData('jimpitan_pengeluaran');
    pengeluaran = pengeluaran.filter(p => p.id !== id);
    setMockData('jimpitan_pengeluaran', pengeluaran);
    return { success: true };
  } else {
    return await fetchJson(`${BASE_URL}/delete_pengeluaran.php?id=${id}`, {
      method: 'POST'
    });
  }
}

// --- NEW ENDPOINTS (PHASE 4) ---

async function apiEditWarga(data) {
  if (USE_MOCK) {
    await delay(400);
    return { success: true };
  } else {
    return await fetchJson(`${BASE_URL}/edit_warga.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
  }
}

async function apiUpdateProfil(data) {
  const user = getAuthUser();
  if (!user) throw new Error('Not logged in');
  
  data.role = user.role;
  data.id = user.id;

  if (USE_MOCK) {
    await delay(400);
    return { success: true };
  } else {
    const result = await fetchJson(`${BASE_URL}/update_profil.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    if (!result.success) throw new Error(result.message || 'Gagal menyimpan profil');
    return result;
  }
}

async function apiKirimPengingat() {
  if (USE_MOCK) {
    await delay(500);
    return { success: true, count: 2 };
  } else {
    return await fetchJson(`${BASE_URL}/kirim_pengingat.php`);
  }
}
