# LAMMS Multi-Device Development Setup

## Goal
Allow multiple laptops to access your dev server with a shared backend database.

---

## Current Setup

### Your Main PC (Development Server)
- **IP Address**: `192.168.163.170`
- **Frontend**: `http://192.168.163.170:5173`
- **Backend API**: `http://192.168.163.170:8000`
- **Database**: PostgreSQL on `localhost:5432`

### Other Laptops (Clients)
- Can access frontend and backend from network
- **Cannot** use camera (browser security - requires HTTPS or localhost)

---

## Configuration Files

### 1. Frontend `.env` (Your Main PC)
```env
VITE_API_BASE_URL=http://localhost:8000
```

### 2. Backend `.env` (Your Main PC)
```env
APP_URL=http://192.168.163.170:8000
DB_HOST=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173,192.168.163.170:5173,10.0.20.163:5173,172.31.64.1:5173
```

### 3. CORS Configuration (Your Main PC)
```php
'allowed_origins' => [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://192.168.163.170:5173',
    'http://10.0.20.163:5173',
    'http://172.31.64.1:5173'
]
```

---

## Running the Servers

### On Your Main PC

**Terminal 1 - Backend API:**
```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms-backend"
php artisan serve --host=0.0.0.0 --port=8000
```
- Accessible from: `http://192.168.163.170:8000`

**Terminal 2 - Frontend (for other devices):**
```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms"
npm run dev:host
```
- Accessible from: `http://192.168.163.170:5173`

---

## Accessing from Other Laptops

### From Another Laptop on the Network

1. **Open browser** on the other laptop
2. **Go to**: `http://192.168.163.170:5173`
3. **Login** with any credentials
4. **Use all features** except camera (QR Scanner)

### Features Available on Network Devices:
- ✅ Login/Authentication
- ✅ Dashboard
- ✅ Attendance tracking (manual entry)
- ✅ Reports
- ✅ Admin functions
- ❌ QR Scanner (camera access blocked by browser)

### Features Available on Your Main PC (localhost):
- ✅ Everything above
- ✅ QR Scanner (camera works on localhost)

---

## Database Access

### PostgreSQL is Local Only
- **Host**: `localhost:5432`
- **Only accessible** from your main PC
- **Other laptops** access data through the API

### All Data Flows Through API:
```
Other Laptop → Frontend (192.168.163.170:5173)
            → Backend API (192.168.163.170:8000)
            → Database (localhost:5432 on your PC)
```

---

## npm Commands

### For Your Main PC (localhost access with camera):
```bash
npm run dev
```
- Runs on `http://localhost:5173`
- Camera works ✅
- Only you can access

### For Network Access (other devices):
```bash
npm run dev:host
```
- Runs on `http://192.168.163.170:5173`
- Camera doesn't work ❌
- Other laptops can access

---

## Troubleshooting

### "Cannot connect to 192.168.163.170:8000"
- Check backend is running: `php artisan serve --host=0.0.0.0 --port=8000`
- Check firewall allows port 8000
- Verify IP address is correct

### "CORS error"
- Ensure IP is added to `lamms-backend/config/cors.php`
- Restart backend server after changes

### "Camera not working"
- This is expected on network IPs (browser security)
- Use `localhost:5173` on your main PC for camera access

### "Database connection error"
- Database is local only on your main PC
- Other laptops access through API, not directly

---

## Summary

| Feature | Your PC (localhost) | Network (192.168.163.170) |
|---------|-------------------|--------------------------|
| Frontend | ✅ | ✅ |
| Backend API | ✅ | ✅ |
| Database | ✅ (direct) | ✅ (via API) |
| Camera/QR | ✅ | ❌ |
| Login | ✅ | ✅ |
| Reports | ✅ | ✅ |

---

## Next Steps

1. **Keep backend running**: `php artisan serve --host=0.0.0.0 --port=8000`
2. **Keep frontend running**: `npm run dev:host`
3. **Other laptops access**: `http://192.168.163.170:5173`
4. **Your PC uses camera**: Access `http://localhost:5173` when you need QR scanner

All data is synced through the shared database on your main PC! 🎉
