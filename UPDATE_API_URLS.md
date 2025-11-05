# 🔧 How to Update API URLs in Your Frontend

## Current Situation
Your frontend has hardcoded API URLs like:
- `http://127.0.0.1:8000/api/...`

## Goal
Use environment variables so:
- **Development**: Uses `http://127.0.0.1:8000`
- **Production**: Uses `https://your-backend.railway.app`

---

## Quick Fix (Manual - Do This First)

### Step 1: Find all hardcoded URLs

Search your project for: `http://127.0.0.1:8000`

Files that likely need updating:
- `src/services/AuthService.js`
- `src/services/AttendanceRecordsService.js`
- `src/services/AttendanceSessionService.js`
- Any other service files

### Step 2: Replace hardcoded URLs

**Before:**
```javascript
const response = await axios.get('http://127.0.0.1:8000/api/teachers');
```

**After:**
```javascript
import { API_BASE_URL } from '@/config/api';

const response = await axios.get(`${API_BASE_URL}/api/teachers`);
```

---

## Automated Fix (Recommended)

I can help you update all files automatically. Would you like me to:

1. **Search all service files** for hardcoded API URLs
2. **Replace them** with environment variable usage
3. **Test** that everything still works locally

This will ensure your app works in both development and production!

---

## After Updating

### Test Locally (Development)
```bash
npm run dev
# Should use http://127.0.0.1:8000
```

### Build for Production
```bash
npm run build
# Will use VITE_API_URL from .env.production
```

### Deploy to Vercel
```bash
vercel --prod
```

---

## Environment Files Created

✅ `.env.development` - Uses localhost:8000  
✅ `.env.production` - Uses Railway backend URL  
✅ `src/config/api.js` - Centralized API configuration  

---

## Next Steps

1. Deploy your Laravel backend to Railway (follow COMPLETE_DEPLOYMENT_GUIDE.md)
2. Get your Railway backend URL
3. Update `.env.production` with the Railway URL
4. Let me know, and I'll help update all API calls in your code
5. Redeploy frontend to Vercel

Would you like me to automatically update all your API URLs now?
