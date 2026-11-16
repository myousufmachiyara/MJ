# Building the offline installer (MJ-Jewelry-System-Setup.exe)

This turns the web app into a Windows installer that a client runs once, with
no internet needed afterward. You do this on your own Windows PC — it can't
be done from this chat, because I can't run Windows programs here.

It takes about 30–45 minutes the first time. Do this on a Windows 10/11 PC
with internet access (you only need internet for this build step, not for
the client who receives the installer).

## What you'll end up with

A folder laid out like this, which Inno Setup turns into one `.exe`:

```
offline-installer\
  app\        <- the Laravel project, ready to run
  php\        <- portable PHP 8.2
  mysql\      <- portable MariaDB
  scripts\    <- start.bat, stop.bat, backup.bat, first-run.bat, .env.offline
  mj-offline.iss
```

The `scripts\` files and `mj-offline.iss` are already written — this
session put them there. You're assembling `app\`, `php\` and `mysql\`.

## Step 1 — Get the app folder ready

1. Copy this whole project into `offline-installer\app\`.
2. Open a command prompt in `offline-installer\app\` and run:
   ```
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   ```
   (Skip the `npm` line if the project has no `package.json` / no Vite
   build step — check for a `vite.config.js` first.)
3. Do **not** put a `.env` file in `app\` — `first-run.bat` creates it from
   `scripts\.env.offline` automatically on the client's PC.
4. Delete `app\storage\logs\*.log` and empty `app\storage\framework\cache`,
   `app\storage\framework\sessions`, `app\storage\framework\views` if they
   have anything in them, so the installer doesn't ship your own test data.

## Step 2 — Get portable PHP

1. Go to <https://windows.php.net/download/> and download the
   **"Non Thread Safe" x64 Zip** build for **PHP 8.2** (for example
   `php-8.2.XX-nts-Win32-vs16-x64.zip`). Use the latest 8.2.x available.
2. Extract it into `offline-installer\php\` (so `offline-installer\php\php.exe`
   exists).
3. In that folder, copy `php.ini-production` to `php.ini` and open it in a
   text editor. Find and change these lines (remove the leading `;` if the
   line is commented out):
   ```
   extension_dir = "ext"
   extension=curl
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=mysqli
   extension=openssl
   extension=pdo_mysql
   extension=zip
   extension=intl
   memory_limit = 512M
   upload_max_filesize = 32M
   post_max_size = 32M
   max_execution_time = 120
   ```
4. Test it: open a command prompt in `offline-installer\php\` and run
   `php.exe -v`. It should print the PHP version with no errors about
   missing extensions.

## Step 3 — Get portable MariaDB

1. Go to <https://mariadb.org/download/> and download the **Windows
   64-bit ZIP** (not the MSI installer) for the latest stable **MariaDB
   11.x**.
2. Extract it into `offline-installer\mysql\` (so
   `offline-installer\mysql\bin\mariadbd.exe` exists).
3. That's it — no install step. `first-run.bat` initializes its data
   directory itself the first time the client runs it.

## Step 4 — Test it manually before packaging

Before wrapping this in an installer, prove it works as a plain folder:

1. Copy `scripts\first-run.bat`, `start.bat`, `stop.bat` into
   `offline-installer\` (one level up from `scripts\`, so they sit next to
   `app\`, `php\`, `mysql\`) — **or** just run them from inside `scripts\`
   as they are; either works, since they use `%~dp0\..` to find the other
   folders.
2. Double-click `scripts\first-run.bat`. Watch for errors. It should finish
   by saying "Setup complete."
3. Double-click `scripts\start.bat`. Your browser should open to
   `http://127.0.0.1:8000` and show the login page.
4. Check `offline-installer\app\CREDENTIALS.txt` for the generated admin
   password and log in.
5. Click around: open a sale invoice, print a PDF, try the barcode scan
   screen. Then turn off your Wi-Fi/network and reload the page — it
   should still work and look the same (this is what the earlier CDN fix
   was for).
6. Double-click `scripts\stop.bat` when you're done testing.

If anything fails here, fix it before building the installer — the
installer will have the exact same problem.

## Step 5 — Install Inno Setup and build

1. Download and install **Inno Setup** from
   <https://jrsoftware.org/isinfo.php> (free).
2. Open `mj-offline.iss` in Inno Setup.
3. Click **Build → Compile** (or press F9 on recent versions is "Run",
   use the **Build** menu for Compile).
4. The finished installer appears at
   `offline-installer\output\MJ-Jewelry-System-Setup.exe`.

## Step 6 — Test the installer itself

Run `MJ-Jewelry-System-Setup.exe` on a **different** Windows PC (or a
clean VM) than the one you built it on — this catches "works on my
machine" problems like a missing PHP extension your dev PC already had
installed separately.

- It installs to `C:\MJJewelrySystem` by default.
- At the end of setup it runs the database setup and offers to launch the
  app — let it.
- Check the desktop icon works, and that the app still works with the
  network disconnected.

## Step 7 — Set up the daily backup (recommended, do this on the client's PC)

The client's database and uploaded images live only on that one PC, so
back them up:

1. Open **Task Scheduler** on the client's PC.
2. Create a new task: trigger **Daily**, at a quiet time (e.g. 11 PM).
3. Action: start a program —
   `C:\MJJewelrySystem\scripts\backup.bat`
4. Tell the client to occasionally copy the `C:\MJJewelrySystem\backups`
   folder to a USB drive or cloud folder — a backup that lives on the same
   PC doesn't help if that PC's disk fails.

## Updating a client's installation later

When you have a code fix (like the ones from this chat) to send an
existing client:

1. Rebuild `app\` with the fix (steps 1 above).
2. Zip just the `app\` folder.
3. On the client's PC: run `scripts\stop.bat`, replace the old `app\`
   folder's files with the new ones (**keep their `app\.env` and
   `app\storage\app\public` — don't overwrite those**), then run:
   ```
   cd C:\MJJewelrySystem\app
   ..\php\php.exe artisan migrate --force
   ..\php\php.exe artisan config:cache
   ..\php\php.exe artisan route:cache
   ```
4. Run `scripts\start.bat` again.

I can prepare a ready-made "update package" + a `update.bat` that
automates steps 3–4, if you'd like — tell me and I'll build it next.

## Notes on what this is and isn't

- This does **not** encrypt or hide the PHP source code — see the earlier
  discussion on ionCube/SourceGuardian if you need that.
- The client's database and files live only on their PC. If several of
  their PCs need to use it, point the other PCs' browsers at the server
  PC's local IP address (e.g. `http://192.168.1.10:8000`) instead of
  `127.0.0.1`, and open port 8000 in that PC's firewall.
- MariaDB runs on port **3307** here (not the usual 3306), so it won't
  clash if the client's PC already has some other MySQL installed.
