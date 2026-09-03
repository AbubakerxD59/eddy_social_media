# Eddy

A social network for founders and operators. Laravel 13, Inertia, Vue 3, and Tailwind.

## What is working

- Register and login (username, email, password)
- Home feed
- Signals as quote, photo carousel, video, or link preview
- Public profiles at `/@username`
- Mentor listing (Google Calendar / Meet and Stripe come next)
- Placeholder pages for messages and notifications (Laravel Echo later)

## Local setup

```bash
cd ~/Desktop/Projects/eddy_social_media
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm run dev
```

In another terminal:

```bash
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000). Seeded login: `ada@eddy.test` / `password`.

## Namecheap / cPanel shared hosting

Build assets on your machine (`npm ci && npm run build`), commit `public/build`, and pull on the server. Do not run Vite or npm over SSH.

Prefer pointing the domain’s document root at `public`:

- cPanel → **Domains** → `bizsphere.forflippers.com` → **Manage**
- Document root: `bizsphere.forflippers.com/public`
- Production `APP_URL`: `https://bizsphere.forflippers.com` (no `/public` suffix)

If the document root has to stay on the repo folder, the root `.htaccess` maps `/build/assets` and routes into `public/`. After a pull:

```bash
cd ~/bizsphere.forflippers.com
git pull origin main
php artisan config:clear
php artisan view:clear
```

- Point production `FILESYSTEM_DISK` at S3 or R2 before you store real video
- Use cron: `* * * * * php artisan schedule:run`
- Live chat later: Laravel Echo + Pusher/Ably on shared hosting, then Reverb on a VPS
