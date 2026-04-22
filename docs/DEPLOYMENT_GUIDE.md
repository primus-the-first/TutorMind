# TutorMind Deployment Guide (Namecheap SSH)

This guide contains the connection parameters and workflow for updating the production server via Git & SSH.

## SSH Connection Details
- **User:** `tutodtoo`
- **Host:** `198.54.120.159` (or `tutormind.app`)
- **Port:** `21098`
- **Identity File:** `~/.ssh/id_rsa` (on local machine)

### Connection Command
Run this in your terminal to log in:
```bash
ssh -p 21098 tutodtoo@198.54.120.159
```

---

## Daily Update Workflow

### 1. From your Local Computer (XAMPP)
After making changes locally, send them to GitHub:
```bash
git add .
git commit -m "Describe your changes"
git push origin main
```

### 2. From the Namecheap SSH Terminal
Log in via SSH, navigate to the web folder, and pull the latest code:
```bash
cd public_html
git pull origin main
```

---

## Troubleshooting

### "Already up to date" but site is old
1. Check if you pushed from your computer.
2. Check if the server is on the right branch: `git branch`
3. Force a hard reset if things are stuck:
   ```bash
   git fetch origin
   git reset --hard origin/main
   ```

### Permission Denied (publickey)
- **Local machine:** Ensure your private key is in `C:\Users\Primus Aeternus\.ssh\id_rsa`.
- **Server to GitHub:** Ensure the server's public key (`cat ~/.ssh/id_ed25519.pub`) is added to the **Deploy Keys** section of the `TutorMind` repository on GitHub.

### Security
The `.htaccess` file is configured to block public access to the `.git` directory:
```apache
RewriteRule ^(.*/)?\.git/ - [F,L]
```
