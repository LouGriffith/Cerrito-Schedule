# How to Push to GitHub

Follow these steps to push the Cerrito Schedule System to your GitHub repository.

## Step 1: Create the Repository on GitHub

1. Go to https://github.com/LouGriffith
2. Click the **"+"** icon in the top right
3. Select **"New repository"**
4. Repository name: `Cerrito-Schedule`
5. Description: "WordPress plugin system for managing trivia and bingo event schedules"
6. Choose **Public** or **Private**
7. **DO NOT** initialize with README (we already have one)
8. Click **"Create repository"**

## Step 2: Initialize Local Repository

Open terminal in the `cerrito-schedule-repo` directory and run:

```bash
cd /path/to/cerrito-schedule-repo
git init
git add .
git commit -m "Initial commit - Cerrito Schedule v4.5"
```

## Step 3: Connect to GitHub

Replace `LouGriffith` with your username if different:

```bash
git branch -M main
git remote add origin https://github.com/LouGriffith/Cerrito-Schedule.git
```

## Step 4: Push to GitHub

```bash
git push -u origin main
```

You may be prompted to enter your GitHub credentials.

## Alternative: Using GitHub CLI

If you have GitHub CLI installed:

```bash
cd /path/to/cerrito-schedule-repo
git init
git add .
git commit -m "Initial commit - Cerrito Schedule v4.5"
gh repo create LouGriffith/Cerrito-Schedule --private --source=. --push
```

## Alternative: Using GitHub Desktop

1. Open GitHub Desktop
2. File → Add Local Repository
3. Choose the `cerrito-schedule-repo` folder
4. Click "Publish repository"
5. Name: `Cerrito-Schedule`
6. Choose Public or Private
7. Click "Publish Repository"

## Verify

After pushing, visit:
https://github.com/LouGriffith/Cerrito-Schedule

You should see all your files!

## Future Updates

When you make changes:

```bash
git add .
git commit -m "Description of changes"
git push
```

## Need Help?

If you get errors:
- Make sure you're logged into GitHub
- Check that the repository name matches exactly
- Ensure you have write permissions

## Repository Structure

Your repo will contain:
```
Cerrito-Schedule/
├── README.md                    # Main documentation
├── CHANGELOG.md                 # Version history
├── INSTALLATION.md              # Setup instructions
├── LICENSE                      # License file
├── USER-MANUAL.pdf              # User manual
├── cerrito-schedule.php         # Main plugin
├── cerrito-events-admin.php     # Admin enhancements
└── .gitignore                   # Git ignore file
```
