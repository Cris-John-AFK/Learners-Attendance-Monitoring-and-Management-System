# 🚀 Complete Deployment Guide - LAMMS System

## Current Status
✅ **Frontend**: Deployed on Vercel at https://lamms.vercel.app  
⏳ **Backend**: Not yet deployed  
⏳ **Database**: Need to migrate from local pgAdmin to cloud  

---

## 📋 Quick Deployment Steps

### **Option 1: Railway (Recommended - Easiest)**

#### Step 1: Create GitHub Repository for Backend

1. Go to https://github.com and create a new repository named `lamms-backend`
2. In your terminal:

```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms-backend"
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/lamms-backend.git
git push -u origin main
```

#### Step 2: Deploy to Railway

1. Go to https://railway.app and sign up/login
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Choose `lamms-backend` repository
5. Railway will automatically detect Laravel and deploy

#### Step 3: Add PostgreSQL Database

1. In your Railway project, click "+ New"
2. Select "Database" → "PostgreSQL"
3. Railway creates a PostgreSQL database automatically
4. Click on PostgreSQL service → "Variables" tab
5. Copy these values (you'll need them):
   - `PGHOST`
   - `PGPORT`
   - `PGDATABASE`
   - `PGUSER`
   - `PGPASSWORD`

#### Step 4: Set Environment Variables

1. Click on your Laravel service (not PostgreSQL)
2. Go to "Variables" tab
3. Click "Raw Editor" and paste:

```env
APP_NAME=LAMMS
APP_ENV=production
APP_DEBUG=false
APP_URL=${{RAILWAY_PUBLIC_DOMAIN}}

DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=lamms.vercel.app,localhost:5173
SESSION_DOMAIN=.railway.app
```

4. Generate APP_KEY:
   - In Railway, go to your Laravel service
   - Click "Settings" → "Deploy"
   - Run command: `php artisan key:generate --show`
   - Copy the output (e.g., `base64:xxxxx`)
   - Add to Variables: `APP_KEY=base64:xxxxx`

#### Step 5: Import Your Database to Railway

**Method 1: Using pgAdmin 4 (Easiest)**

1. Open pgAdmin 4
2. Right-click "Servers" → "Register" → "Server"
3. Fill in:
   - **Name**: Railway LAMMS
   - **Host**: [PGHOST from Railway]
   - **Port**: [PGPORT from Railway]
   - **Database**: [PGDATABASE from Railway]
   - **Username**: [PGUSER from Railway]
   - **Password**: [PGPASSWORD from Railway]
   - **SSL Mode**: Require
4. Connect to the server
5. Right-click your local `lamms_db` → "Backup"
6. Save backup file
7. Right-click Railway database → "Restore"
8. Select your backup file

**Method 2: Using Command Line**

```bash
# Export your local database
pg_dump -U postgres -h localhost -p 5432 lamms_db > lamms_backup.sql

# Import to Railway (replace with your Railway credentials)
psql -h [RAILWAY_PGHOST] -p [RAILWAY_PGPORT] -U [RAILWAY_PGUSER] -d [RAILWAY_PGDATABASE] < lamms_backup.sql
```

#### Step 6: Run Migrations (if needed)

1. In Railway → Your Laravel service → "Settings"
2. Add deployment command:
```bash
php artisan migrate --force
```

#### Step 7: Get Your Backend URL

1. In Railway → Your Laravel service → "Settings"
2. Click "Generate Domain"
3. Copy the URL (e.g., `https://lamms-backend-production.up.railway.app`)

#### Step 8: Update Frontend to Use Backend

1. Edit `.env.production` in your frontend:
```env
VITE_API_URL=https://your-railway-backend-url.railway.app
```

2. Redeploy frontend to Vercel:
```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms"
vercel --prod
```

---

### **Option 2: Heroku (Alternative)**

#### Step 1: Install Heroku CLI

```bash
npm install -g heroku
```

#### Step 2: Login and Create App

```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms-backend"
heroku login
heroku create lamms-backend
```

#### Step 3: Add PostgreSQL

```bash
heroku addons:create heroku-postgresql:essential-0
```

#### Step 4: Set Environment Variables

```bash
heroku config:set APP_NAME=LAMMS
heroku config:set APP_ENV=production
heroku config:set APP_DEBUG=false
heroku config:set APP_KEY=$(php artisan key:generate --show)
```

#### Step 5: Deploy

```bash
git push heroku main
heroku run php artisan migrate --force
```

---

## 🔒 Making Your Site Private

### **Method 1: Vercel Password Protection (Easiest)**

1. Go to https://vercel.com/dashboard
2. Select your `lamms` project
3. Go to "Settings" → "Deployment Protection"
4. Enable "Password Protection"
5. Set a password
6. Save

Now anyone visiting `lamms.vercel.app` will need the password!

### **Method 2: Vercel Authentication (Email-based)**

1. In Vercel → Settings → Deployment Protection
2. Enable "Vercel Authentication"
3. Add email addresses of allowed users
4. They'll need to log in with their Vercel account

### **Method 3: Use Your Existing Login System**

Your app already has authentication! Once deployed with the backend, only users with accounts can access the system.

---

## 📊 Database Migration Checklist

- [ ] Export local PostgreSQL database from pgAdmin
- [ ] Create Railway PostgreSQL database
- [ ] Import data to Railway PostgreSQL
- [ ] Verify all tables and data migrated
- [ ] Test database connection from Railway backend
- [ ] Update frontend API URL
- [ ] Test login and data loading

---

## 🧪 Testing After Deployment

1. **Test Backend API**:
   - Visit `https://your-backend.railway.app/api/health` (if you have a health endpoint)
   - Try logging in from frontend

2. **Test Frontend**:
   - Visit `https://lamms.vercel.app`
   - Try logging in with a test account
   - Check if data loads correctly

3. **Test Database**:
   - Verify attendance records load
   - Check student/teacher data
   - Test creating new records

---

## 🆘 Troubleshooting

### Frontend can't connect to backend
- Check CORS settings in Laravel `config/cors.php`
- Verify `SANCTUM_STATEFUL_DOMAINS` includes `lamms.vercel.app`
- Check `.env.production` has correct backend URL

### Database connection failed
- Verify Railway PostgreSQL is running
- Check environment variables are set correctly
- Ensure SSL mode is enabled for PostgreSQL connection

### 500 Internal Server Error
- Check Railway logs: Service → "Deployments" → Click latest deployment → "View Logs"
- Verify APP_KEY is set
- Check database migrations ran successfully

---

## 📞 Support

If you encounter issues:
1. Check Railway deployment logs
2. Check Vercel deployment logs
3. Verify environment variables are set correctly
4. Test database connection separately

---

## 🎉 Success Checklist

- [ ] Backend deployed to Railway
- [ ] PostgreSQL database created and data imported
- [ ] Environment variables configured
- [ ] Frontend updated with backend URL
- [ ] Frontend redeployed to Vercel
- [ ] Password protection enabled on Vercel
- [ ] Login works end-to-end
- [ ] Data loads correctly

---

**Your Live URLs:**
- Frontend: https://lamms.vercel.app
- Backend: https://[your-app].railway.app
- Database: Managed by Railway PostgreSQL
