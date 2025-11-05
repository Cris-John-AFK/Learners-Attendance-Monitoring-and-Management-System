# 🚀 Laravel Backend Deployment Guide (Railway)

## Prerequisites
- GitHub account
- Railway account (sign up at https://railway.app)
- Your PostgreSQL database backup

## Step-by-Step Deployment

### 1. Push Your Code to GitHub

```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms-backend"
git init
git add .
git commit -m "Initial commit - Laravel backend"
git branch -M main
git remote add origin YOUR_GITHUB_REPO_URL
git push -u origin main
```

### 2. Deploy to Railway

1. Go to https://railway.app
2. Click "Start a New Project"
3. Select "Deploy from GitHub repo"
4. Choose your backend repository
5. Railway will auto-detect it's a Laravel app

### 3. Add PostgreSQL Database

1. In your Railway project, click "+ New"
2. Select "Database" → "PostgreSQL"
3. Railway will create a PostgreSQL database
4. Copy the connection details

### 4. Set Environment Variables

In Railway project settings → Variables, add:

```
APP_NAME=LAMMS
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-railway-app.railway.app

DB_CONNECTION=pgsql
DB_HOST=${PGHOST}
DB_PORT=${PGPORT}
DB_DATABASE=${PGDATABASE}
DB_USERNAME=${PGUSER}
DB_PASSWORD=${PGPASSWORD}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=lamms.vercel.app
SESSION_DOMAIN=.railway.app
```

### 5. Import Your Database

Option A: Use Railway's PostgreSQL client
1. Click on your PostgreSQL service
2. Go to "Data" tab
3. Use the Query editor to run your SQL dump

Option B: Use pgAdmin 4
1. Get connection details from Railway PostgreSQL service
2. In pgAdmin, create new server connection:
   - Host: [from Railway]
   - Port: [from Railway]
   - Database: [from Railway]
   - Username: [from Railway]
   - Password: [from Railway]
3. Right-click database → Restore
4. Select your backup file

### 6. Run Migrations

In Railway project → Settings → Deploy:
```bash
php artisan migrate --force
```

### 7. Generate APP_KEY

```bash
php artisan key:generate --show
```
Copy the output and add to Railway environment variables

### 8. Update Frontend API URL

Update your Vercel frontend to use the Railway backend URL.

## Your URLs After Deployment

- **Frontend**: https://lamms.vercel.app
- **Backend**: https://your-app-name.railway.app
- **Database**: Managed by Railway

## Troubleshooting

### CORS Issues
Add to `config/cors.php`:
```php
'allowed_origins' => ['https://lamms.vercel.app'],
```

### Database Connection Issues
- Check Railway PostgreSQL is running
- Verify environment variables are set correctly
- Check database credentials

### Session Issues
- Ensure SESSION_DOMAIN is set correctly
- Check SANCTUM_STATEFUL_DOMAINS includes your frontend domain
