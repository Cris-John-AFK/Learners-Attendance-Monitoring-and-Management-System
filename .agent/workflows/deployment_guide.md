---
description: Guide to deploying LAMMS (Backend & Database) for Always-On Access
---

# 🚀 LAMMS Turnover Deployment Guide (Always-On)

This guide will help you set up the **Database** and **Backend** on the cloud so your application works 24/7 without your laptop.

## Phase 1: The Cloud Database (Neon.tech)
We need to move your `lamms_db` to the cloud.

1.  **Create Account**: Go to [Neon.tech](https://neon.tech) and sign up (Free).
2.  **Create Project**: Create a new project name `lamms-db`.
3.  **Get Credentials**:
    *   Look for the **Connection String** on the dashboard.
    *   It looks like: `postgres://neondb_owner:AbC123@ep-cool-server.us-east-2.aws.neon.tech/neondb?sslmode=require`
    *   **Keep this safe!**

4.  **Restore Your Data**:
    *   You have the backup file: `C:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms_db_backup.sql`
    *   Open your terminal (PowerShell) and run this command (replace `YOUR_NEON_CONNECTION_STRING` with the one from step 3):
    ```powershell
    & "C:\Program Files\PostgreSQL\17\bin\psql.exe" "YOUR_NEON_CONNECTION_STRING" < "C:\xampp\htdocs\CAPSTONE 2\sakai_lamms\lamms_db_backup.sql"
    ```
    *   *Note: If `psql` asks for a password, it's the one inside the connection string.*

## Phase 2: Deploy Backend to Vercel
Now we deploy the Laravel code.

1.  **Push Code**: Make sure your latest changes are pushed to GitHub.
    ```bash
    git add .
    git commit -m "Prepare for Vercel deployment"
    git push origin main
    ```

2.  **Vercel Dashboard**:
    *   Go to Vercel and **Add New Project**.
    *   Import your `sakai_lamms` repository.
    *   **Root Directory**: Click "Edit" and select `lamms-backend`.

3.  **Environment Variables (Important)**:
    Add the following variables in Vercel (copy from Neon dashboard):
    *   `DB_CONNECTION`: `pgsql`
    *   `DB_HOST`: (The host part, e.g., `ep-cool-server...neon.tech`)
    *   `DB_PORT`: `5432`
    *   `DB_DATABASE`: `neondb` (Default in Neon)
    *   `DB_USERNAME`: (The user part, e.g., `neondb_owner`)
    *   `DB_PASSWORD`: (The password part)
    *   `DB_PGSQL_SSLMODE`: `require` (Crucial for Neon)
    *   `APP_KEY`: (Copy from your local .env)

4.  **Deploy**: Click "Deploy".
    *   Once done, Vercel will give you a domain like `https://lamms-backend.vercel.app`.

## Phase 3: Deploy Frontend to Vercel
Finally, update the frontend to talk to the new backend.

1.  **Vercel Dashboard**:
    *   Add another New Project.
    *   Import the same `sakai_lamms` repository.
    *   **Root Directory**: Leave as `./` (Root).

2.  **Environment Variables**:
    *   `VITE_API_BASE_URL`: Paste your **Backend URL** from Phase 2 (e.g., `https://lamms-backend.vercel.app`).

3.  **Deploy**: Click "Deploy".

## ✅ Success!
Your application is now:
*   **Frontend**: `https://sakai-lamms.vercel.app`
*   **Backend**: `https://lamms-backend.vercel.app`
*   **Database**: Hosted on Neon.tech
*   **Status**: **ALWAYS ONLINE** (Laptop not required)
