Hello, this is me working on my project.

Just kidding, I already had the project. I'm only using GitHub now because I didn't see it in the criteria at first, so I had already built my web app. Now I'm adding it all one by one to the GitHub repository. So here is the summary, na mahaba.

# BranchWatch

**A multi-branch store monitoring system with a POS.** Each branch's vendor (cashier) signs in to a simple POS to sell products and record expenses. The owner (admin) signs in from anywhere with internet and watches stock, daily income and expenses of every branch on one live dashboard.

Course: Elec 3 – Technical Elective III · BSCPE 4B · PRMSU College of Engineering

- **Live URL:** [http://jezreel.infinityfreeapp.com/login.php](http://jezreel.infinityfreeapp.com/login.php)
- **Repository:** [https://github.com/tako0-0o/branchwatch](https://github.com/tako0-0o/branchwatch)

## Descriptions

**Admin** - Live dashboard (income, expenses, net, low stock, which vendors are online) · manage **products**, **branches**, **vendors** and **expenses**

**Vendor** - POS for their own branch only: tap products to sell (stock goes down automatically), see today's sales, record expenses (supplies, utilities, restocking...)

The dashboard refreshes by itself every 10 seconds and warns when a product is out of stock, a branch spent more than it earned today, or no vendor is online.

## Tech stack

| Layer | Technology | Why |
|---|---|---|
| Frontend | HTML, CSS, vanilla JavaScript (`fetch` / AJAX) | No build step, runs on any cheap host |
| Backend | PHP 8 (PDO, sessions) | Supported by every free host |
| Database | MySQL / MariaDB | Relational data with foreign keys |
| Version control | Git + GitHub | Commit history, branches |
| Local server | XAMPP | Apache + PHP + MySQL in one install |
| Hosting | Free PHP + MySQL host (InfinityFree) | Gives a public URL |

### Demo accounts

| Role | Username | Password |
|---|---|---|
| Admin (owner) | `admin` | `admin123` (change it in *Account*) |
| Vendor, Downtown Branch | `pikang` | `kangkang` |
| Vendor, Riverside Branch | `adi` | `adipotpot` |

## How I deployed it

I built and tested the whole system on my own computer with XAMPP first. After that I put it online on **InfinityFree**, a free host that supports PHP and MySQL. This is how I did it, step by step.

### 1. I made a free hosting account
I signed up at InfinityFree and created a hosting account on the **$0 "InfinityFree" plan**. I skipped the paid Premium plans because I didn't need them. For the address I picked the `infinityfreeapp.com` ending and got `jezreel.infinityfreeapp.com`. The page showed a warning that another free ending (`howto.rocks`) was not resolving, but it didn't affect mine. It also said a new domain can take up to 72 hours to be reachable everywhere, so I waited a few minutes before testing.

### 2. I created the MySQL database
In the control panel I opened **MySQL Databases** and created a database called `branchwatch`. InfinityFree adds a prefix to the name, so the real name became `if0_XXXXXXXX_branchwatch`. I wrote down the four values the app needs:

- **Host:** `sqlXXX.infinityfree.com` (this is *not* `localhost`)
- **Database name:** `if0_XXXXXXXX_branchwatch`
- **Username:** `if0_XXXXXXXX`
- **Password:** my hosting account password

### 3. I set up `includes/config.local.php`
The app reads its database settings from `includes/config.php`, which defaults to XAMPP (`localhost`, `root`, no password). For the live site I made a separate file, `includes/config.local.php`, with the InfinityFree values:

```php
<?php
define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_NAME', 'if0_XXXXXXXX_branchwatch');
define('DB_USER', 'if0_XXXXXXXX');
define('DB_PASS', 'my-password');
```

This file is listed in `.gitignore`, so my password is **never pushed to GitHub**. I upload it to the host by hand instead. There is a template called `includes/config.local.example.php` in the repo.

### 4. I imported the database
Using **phpMyAdmin** from the control panel, I selected my database, opened **Import**, and imported `database/schema.sql` first, then `database/sample_data.sql`. Both finished without errors, and all the tables showed up on the left.

### 5. I uploaded the files
In the **File Manager** I opened the `htdocs` folder and deleted the placeholder file. The normal **Upload** button only took single files, not folders, so only my loose PHP files went up. To fix it, I zipped the **contents** of my project folder (not the folder itself) and used **Upload & Unzip** inside `htdocs`. After that, all the folders (`admin`, `api`, `assets`, `database`, `includes`, `vendor`) and the PHP files were in place. I also checked that `config.local.php` was inside `includes`.

### 6. Testing the live site
I open `http://jezreel.infinityfreeapp.com/login.php` and check that:

1. the admin account signs in, and I changed its default password right away in *Account*;
2. a vendor account signs in and goes straight to the POS of its own branch;
3. a sale made in the POS lowers the product's stock;
4. the admin dashboard updates within about 10 seconds.

### Problems I ran into

| Problem | What fixed it |
|---|---|
| Opening `login.php` with VS Code's debugger gave `spawn php ENOENT` | The app runs through Apache, not the VS Code debugger. I opened it in the browser at `http://localhost/branchwatch/` instead. |
| Only the loose files uploaded, not the folders | Zip the project's contents and use **Upload & Unzip**. |
| The new address didn't load right away | New domains can take a while because of DNS caching, so I waited and tried again. |

### Next on my list
- Upload the root `.htaccess`, which blocks direct downloads of `.sql` files and folder listings.
- Turn on HTTPS with the **SSL Certificates** tool in InfinityFree.
- Remove the demo vendor accounts, or change their passwords, before sharing the live site widely.

## How I'm adding it to GitHub

The project was already working on my computer before I made the repository. I'm adding it to GitHub piece by piece, committing after each part works: database, config and auth, login and layout, then branches, products, vendors, POS and expenses, the dashboard, and finally the sample data and this README. The POS and expenses go on their own feature branches, which I merge into `main`.
