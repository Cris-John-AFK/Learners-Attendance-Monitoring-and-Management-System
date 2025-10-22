# 🌐 MULTI-DEVICE LOCALHOST SETUP GUIDE FOR LAMMS
## How to Use the Same Database Across Multiple Devices

---

## 📋 **SCENARIO**
You want multiple devices (e.g., your laptop for admin, groupmate's laptop for guardhouse) to access the **same LAMMS database** so that when the guardhouse scans a QR code, the admin can see it in real-time.

---

## 🎯 **RECOMMENDED SOLUTIONS**

### **OPTION 1: SHARED NETWORK DATABASE (RECOMMENDED)** ⭐

This is the **BEST** solution for your capstone project. One computer hosts the database, others connect to it.

#### **Setup Steps:**

#### **1️⃣ On the HOST Computer (Database Server)**

**A. Configure PostgreSQL to Accept Network Connections**

1. **Find PostgreSQL config files:**
   ```
   C:\Program Files\PostgreSQL\15\data\postgresql.conf
   C:\Program Files\PostgreSQL\15\data\pg_hba.conf
   ```

2. **Edit `postgresql.conf`:**
   ```conf
   # Find this line and change it:
   listen_addresses = '*'          # Listen on all network interfaces
   port = 5432                      # Default PostgreSQL port
   ```

3. **Edit `pg_hba.conf`:**
   ```conf
   # Add this line at the end to allow network connections:
   # TYPE  DATABASE        USER            ADDRESS                 METHOD
   host    lamms_db        postgres        192.168.1.0/24          md5
   host    all             all             192.168.1.0/24          md5
   ```
   
   **Note:** Replace `192.168.1.0/24` with your actual network range. To find it:
   - Open CMD and run: `ipconfig`
   - Look for "IPv4 Address" (e.g., 192.168.1.100)
   - Use the first 3 numbers with .0/24 (e.g., 192.168.1.0/24)

4. **Restart PostgreSQL:**
   - Open Services (Win + R → `services.msc`)
   - Find "postgresql-x64-15" (or your version)
   - Right-click → Restart

5. **Allow PostgreSQL through Windows Firewall:**
   ```powershell
   # Run PowerShell as Administrator:
   New-NetFirewallRule -DisplayName "PostgreSQL" -Direction Inbound -LocalPort 5432 -Protocol TCP -Action Allow
   ```

6. **Find your HOST computer's IP address:**
   ```cmd
   ipconfig
   ```
   Look for "IPv4 Address" - example: `192.168.1.100`

**B. Configure Laravel Backend to Accept Network Connections**

1. **Edit `lamms-backend\.env`:**
   ```env
   # Change from localhost to 0.0.0.0 to accept all connections
   APP_URL=http://0.0.0.0:8000
   
   # Database stays the same (localhost for the host machine)
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=lamms_db
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```

2. **Start Laravel with network binding:**
   ```bash
   cd lamms-backend
   php artisan serve --host=0.0.0.0 --port=8000
   ```

3. **Allow Laravel through Windows Firewall:**
   ```powershell
   # Run PowerShell as Administrator:
   New-NetFirewallRule -DisplayName "Laravel Backend" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
   ```

**C. Configure Vue Frontend**

1. **Edit `vite.config.js`:**
   ```javascript
   export default defineConfig({
     server: {
       host: '0.0.0.0',  // Accept connections from network
       port: 5173,
       strictPort: true,
     },
     // ... rest of config
   })
   ```

2. **Start frontend:**
   ```bash
   npm run dev
   ```

3. **Allow Vite through Windows Firewall:**
   ```powershell
   # Run PowerShell as Administrator:
   New-NetFirewallRule -DisplayName "Vite Dev Server" -Direction Inbound -LocalPort 5173 -Protocol TCP -Action Allow
   ```

---

#### **2️⃣ On CLIENT Computers (Guardhouse, Other Devices)**

**A. Install Only the Frontend (No Database Needed)**

1. **Clone the project:**
   ```bash
   git clone <your-repo-url>
   cd sakai_lamms
   npm install
   ```

2. **Create `.env` file pointing to HOST computer:**
   ```env
   # Replace 192.168.1.100 with your HOST computer's IP
   VITE_API_BASE_URL=http://192.168.1.100:8000/api
   ```

3. **Update API configuration in `src/services/`:**
   
   Edit all service files to use the environment variable:
   ```javascript
   // Example: GuardhouseService.js
   const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://192.168.1.100:8000/api';
   ```

4. **Start the frontend:**
   ```bash
   npm run dev
   ```

5. **Access the app:**
   - Guardhouse device: `http://localhost:5173/guardhouse`
   - Admin device: `http://localhost:5173/admin`

**B. Alternative: Access HOST Computer Directly**

Instead of running frontend on each device, just access the HOST:
- Guardhouse: `http://192.168.1.100:5173/guardhouse`
- Admin: `http://192.168.1.100:5173/admin`

---

### **OPTION 2: CLOUD DATABASE (FOR REMOTE ACCESS)** ☁️

If you need to access from different locations (not same WiFi), use a cloud database.

#### **Free Cloud Database Options:**

1. **Supabase (PostgreSQL)** - FREE tier available
   - Website: https://supabase.com
   - 500MB database, unlimited API requests
   - Provides connection string

2. **ElephantSQL** - FREE tier available
   - Website: https://www.elephantsql.com
   - 20MB database (enough for testing)
   - PostgreSQL hosting

3. **Railway** - FREE tier with credit
   - Website: https://railway.app
   - Easy PostgreSQL deployment

#### **Setup with Cloud Database:**

1. **Create account on Supabase/ElephantSQL**

2. **Get connection details:**
   ```
   Host: db.xxxxx.supabase.co
   Port: 5432
   Database: postgres
   User: postgres
   Password: your_password
   ```

3. **Update `lamms-backend\.env`:**
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=db.xxxxx.supabase.co
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres
   DB_PASSWORD=your_cloud_password
   ```

4. **Run migrations:**
   ```bash
   cd lamms-backend
   php artisan migrate
   ```

5. **Import your data:**
   - Use pgAdmin to connect to cloud database
   - Run your SQL file: `LAMMS_ATTENDANCE_DATA_GROUPMATE.sql`

---

### **OPTION 3: NGROK TUNNEL (QUICK TESTING)** 🚇

For quick testing without network configuration.

1. **Download ngrok:** https://ngrok.com/download

2. **Expose Laravel backend:**
   ```bash
   ngrok http 8000
   ```
   
   You'll get a URL like: `https://abc123.ngrok.io`

3. **Update frontend `.env`:**
   ```env
   VITE_API_BASE_URL=https://abc123.ngrok.io/api
   ```

4. **Share the ngrok URL** with your groupmates

**⚠️ Limitations:**
- Free tier has session limits
- URL changes every restart
- Not suitable for production

---

## 🔧 **TESTING THE SETUP**

### **Test Database Connection:**

On CLIENT computer, test if you can reach the HOST database:

```bash
# Test PostgreSQL connection
psql -h 192.168.1.100 -U postgres -d lamms_db

# If successful, you'll see:
# lamms_db=#
```

### **Test Backend API:**

On CLIENT computer, open browser:
```
http://192.168.1.100:8000/api/students
```

You should see JSON response with student data.

### **Test Real-Time Sync:**

1. **On Guardhouse device:** Scan a QR code
2. **On Admin device:** Check if the attendance appears
3. **Verify in database:** Both should see the same data

---

## 📱 **RECOMMENDED SETUP FOR YOUR CAPSTONE**

### **For Presentation/Demo:**

```
┌─────────────────────────────────────────┐
│  HOST COMPUTER (Your Main Laptop)      │
│  - PostgreSQL Database                  │
│  - Laravel Backend (port 8000)          │
│  - Vue Frontend (port 5173)             │
│  - IP: 192.168.1.100                    │
└─────────────────────────────────────────┘
              │
              │ WiFi Network
              │
    ┌─────────┴─────────┐
    │                   │
┌───▼────────┐    ┌────▼─────────┐
│ GUARDHOUSE │    │ ADMIN DEVICE │
│ (Tablet/   │    │ (Laptop)     │
│  Laptop)   │    │              │
│            │    │              │
│ Access:    │    │ Access:      │
│ 192.168.   │    │ 192.168.     │
│ 1.100:5173 │    │ 1.100:5173   │
│ /guardhouse│    │ /admin       │
└────────────┘    └──────────────┘
```

### **Configuration Summary:**

| Component | HOST Computer | CLIENT Devices |
|-----------|---------------|----------------|
| PostgreSQL | ✅ Installed | ❌ Not needed |
| Laravel Backend | ✅ Running on 0.0.0.0:8000 | ❌ Not needed |
| Vue Frontend | ✅ Running on 0.0.0.0:5173 | ✅ Optional (can access HOST directly) |
| Database Connection | localhost | 192.168.1.100:5432 |
| API URL | http://localhost:8000/api | http://192.168.1.100:8000/api |

---

## 🛠️ **TROUBLESHOOTING**

### **Problem: Cannot connect to database from CLIENT**

**Solution:**
1. Check if PostgreSQL is running on HOST
2. Verify `pg_hba.conf` has correct network range
3. Check Windows Firewall allows port 5432
4. Ping the HOST computer: `ping 192.168.1.100`

### **Problem: API returns CORS errors**

**Solution:**
Add to `lamms-backend/config/cors.php`:
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

### **Problem: Guardhouse scan doesn't show in Admin**

**Solution:**
1. Both devices must use the **same backend URL**
2. Check if both are connected to same WiFi
3. Verify database connection on both devices
4. Check browser console for API errors

---

## 📚 **ADDITIONAL RESOURCES**

- **PostgreSQL Network Configuration:** https://www.postgresql.org/docs/current/runtime-config-connection.html
- **Laravel CORS:** https://laravel.com/docs/10.x/routing#cors
- **Vite Network Access:** https://vitejs.dev/config/server-options.html

---

## ✅ **QUICK START CHECKLIST**

### **On HOST Computer:**
- [ ] Configure PostgreSQL for network access
- [ ] Restart PostgreSQL service
- [ ] Allow ports 5432, 8000, 5173 in firewall
- [ ] Start Laravel: `php artisan serve --host=0.0.0.0`
- [ ] Start Vue: `npm run dev`
- [ ] Note your IP address

### **On CLIENT Computers:**
- [ ] Connect to same WiFi network
- [ ] Update `.env` with HOST IP
- [ ] Test database connection
- [ ] Test API endpoint
- [ ] Access frontend via HOST IP

---

## 🎓 **FOR YOUR CAPSTONE PRESENTATION**

**Recommended Demo Setup:**

1. **Main Laptop (HOST):** Run everything, show admin dashboard
2. **Tablet/Second Laptop (CLIENT):** Access guardhouse page
3. **Demo Flow:**
   - Scan QR code on guardhouse device
   - Show real-time update on admin dashboard
   - Demonstrate data synchronization

**Talking Points:**
- "Our system uses a centralized PostgreSQL database"
- "Multiple devices can access simultaneously"
- "Real-time synchronization across guardhouse and admin"
- "Scalable architecture for production deployment"

---

## 📞 **NEED HELP?**

If you encounter issues:
1. Check the error logs: `lamms-backend/storage/logs/laravel.log`
2. Check browser console for frontend errors
3. Verify network connectivity: `ping <HOST_IP>`
4. Test database connection: `psql -h <HOST_IP> -U postgres -d lamms_db`

---

**Good luck with your capstone project! 🚀**
