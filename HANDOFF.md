# Yo Salon Project Handoff

## Project Overview
Yo Salon is a multi-tenant salon booking system with:
- **Backend:** Laravel 13.20 on PHP 8.3 FPM
- **Frontend:** Next.js on Vercel
- **Database:** Supabase PostgreSQL with PgBouncer pooler
- **Infrastructure:** AWS EC2 Ubuntu 24.04 + Nginx
- **Real-time:** Laravel Reverb for WebSockets
- **Queue/Cache:** Redis
- **Process Management:** Supervisor

## Current Infrastructure Status

### Backend (AWS EC2: 16.171.141.237)
- ✅ **Database:** Supabase PostgreSQL connected via pooler (aws-0-eu-central-1.pooler.supabase.com:6543)
- ✅ **Redis:** Installed and configured (127.0.0.1:6379)
- ✅ **Laravel Reverb:** Installed and configured (127.0.0.1:8080)
- ✅ **Supervisor:** Running with workers, scheduler, and Reverb
- ✅ **Multi-tenant isolation:** Fully implemented via `BelongsToSalon` trait
- ✅ **Deployment script:** `deploy.sh` automates updates

### Frontend (Vercel)
- ✅ **Next.js:** Deployed and running
- ✅ **API Proxy:** Backend routes configured via next.config.js
- ✅ **Invite Links:** Fixed to use `window.location.origin`
- ✅ **Authentication:** Working for owner/staff/portal

## Recent Critical Fixes

### 1. Supabase PostgreSQL Fix
**Issue:** Prepared statement errors with PgBouncer pooler
**Solution:** Added to `config/database.php`:
```php
'sslmode' => 'require',
'options' => [PDO::ATTR_EMULATE_PREPARES => true]
```

### 2. Redis Configuration
**Issue:** Laravel still using database drivers
**Solution:** Updated production `.env`:
```
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
```

### 3. Laravel Reverb Installation
**Issue:** Reverb command not found
**Solution:** Added to `composer.json`:
```json
"laravel/reverb": "^1.0"
```

### 4. Supervisor Configuration
**Issue:** Workers failing due to permissions
**Solution:** Updated `deploy.sh` to run artisan commands as `www-data` user

### 5. Invite Link Fix
**Issue:** Links showing localhost instead of production URL
**Solution:** Changed from `process.env.NEXT_PUBLIC_ROOT_DOMAIN` to `window.location.origin`

## Repository Structure

### Backend (`/var/www/yo.salon/backend`)
```
config/
  database.php          # Supabase pooler fix
  reverb.php            # Reverb server config
supervisor.conf         # Worker/scheduler/Reverb config
deploy.sh              # Deployment automation
.env                   # Production environment (NOT in git)
.env.example          # Template with Redis/Reverb
composer.json         # Includes laravel/reverb
```

### Frontend
```
src/
  app/
    staff/page.tsx     # Staff invite links fixed
    customers/page.tsx # Customer invite links fixed
  components/
    dashboard/
      OwnerDashboard.tsx # Public booking URL fixed
```

## Important Rules for Next Agent

### 1. **NEVER touch production .env file**
- The `.env` file on AWS contains secrets
- Only update `.env.example` for template changes
- Never commit `.env` to git

### 2. **Always test locally before production**
- Run changes locally first
- Verify no breaking changes
- Then commit and push

### 3. **Use proper deployment workflow**
```
Local changes → git commit → git push → AWS git pull → ./deploy.sh
```

### 4. **Don't redesign, fix specific issues**
- Focus on the reported problem
- Minimal changes
- Test the fix, don't over-engineer

### 5. **Respect multi-tenant isolation**
- All models use `BelongsToSalon` trait
- Global scopes automatically filter by `salon_id`
- Never bypass tenant scoping without reason

### 6. **Production is live and in use**
- The app is currently online and usable
- Be extremely careful with database changes
- Test deployment scripts thoroughly

## Current Commit Status

### Backend
- `75a2697` - Add Laravel Reverb package to composer.json
- `3867b4b` - Fix deploy.sh to run artisan commands as www-data user
- `6f9dd6b` - Update deployment script and .env.example for production setup
- `d57e07f` - Fix deployment script to copy Supervisor config
- `c7a3ff7` - Complete production setup: Redis, Reverb, Supervisor

### Frontend
- `3ae3bf7` - Fix invite links to use window.location.origin instead of localhost

## Deployment Commands

### On AWS Server
```bash
cd /var/www/yo.salon/backend
git pull
chmod +x deploy.sh
./deploy.sh
```

### Verify Services
```bash
sudo supervisorctl status
redis-cli ping
```

### Expected Supervisor Status
```
laravel-worker_00     RUNNING
laravel-worker_01     RUNNING
laravel-scheduler     RUNNING
laravel-reverb        RUNNING
```

## Known Issues / Next Steps

### 1. **Reverb Environment Variables**
- Production Reverb keys need to be set in `.env`
- Currently using placeholder values
- Generate secure keys for production

### 2. **AWS S3 Configuration**
- File storage still local
- Need to configure AWS S3 for production file uploads
- Update `.env` with AWS credentials

### 3. **Email Configuration**
- Currently using log driver
- Configure Mailtrap or production SMTP
- Update mail settings in `.env`

### 4. **Monitoring**
- No production monitoring setup
- Consider adding error tracking (Sentry)
- Add uptime monitoring

## Database Schema Notes

### Multi-Tenant Architecture
- All tenant tables have `salon_id` foreign key
- Customer-salon relationships allow multi-salon customers
- User-salon relationships for staff/owner access

### Key Tables
- `salons` - Salon profiles with slug for public URLs
- `users` - Backend users (owner/staff)
- `customers` - Customer records
- `customer_salon` - Customer-salon relationships
- `staff` - Staff profiles
- `bookings` - Appointments
- `services` - Salon services
- `invitations` - Staff/customer invitations

## API Routes

### Backend API
- Base: `https://16.171.141.237/api/v1`
- Authentication: Bearer token via Authorization header
- Multi-tenant: Automatic via middleware

### Frontend Routes
- Dashboard: `/dashboard`
- Staff: `/staff`
- Customers: `/customers`
- Portal: `/portal/*`
- Invite: `/invite/[token]` (staff)
- Portal Invite: `/portal/invite/[token]` (customers)

## Contact Information

### Server Access
- **AWS EC2:** ubuntu@16.171.141.237
- **Backend Path:** /var/www/yo.salon/backend
- **Frontend:** Deployed via Vercel (auto-deploys from git)

### Database
- **Supabase Pooler:** aws-0-eu-central-1.pooler.supabase.com:6543
- **SSL Mode:** require
- **Prepared Statements:** emulated

## Summary

The system is production-ready with:
- ✅ Working multi-tenant architecture
- ✅ Redis for sessions, cache, and queues
- ✅ Laravel Reverb for real-time features
- ✅ Supervisor for process management
- ✅ Automated deployment
- ✅ Fixed invite links for production URLs

The next agent should focus on:
1. Completing Reverb production setup (secure keys)
2. Configuring AWS S3 for file storage
3. Setting up production email service
4. Adding monitoring and error tracking

**BE CAREFUL:** Production is live. Test locally first, use minimal changes, and follow the deployment workflow.
