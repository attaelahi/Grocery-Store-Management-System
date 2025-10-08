# 🛒 Grocery Store Management System (Core PHP + MySQL + Bootstrap)

A complete **web-based Grocery Store Management System** built using **Core PHP**, **MySQL**, and **Bootstrap (inline CSS)**.
This system helps grocery store owners efficiently manage **billing (POS)**, **inventory**, **suppliers**, **customers**, **expenses**, and **sales reports** — all from a clean, modern dashboard interface.

---

## 🚀 Features

### 🧾 POS / Billing System

* Fast & easy billing with barcode or product search
* Apply discounts and taxes
* Print receipts
* Hold and retrieve invoices

### 📦 Inventory Management

* Add, edit, or delete products
* Real-time stock updates
* Low stock alerts
* CSV import/export

### 🚚 Suppliers & Purchases

* Add suppliers and record purchases
* Automatically update stock when purchase received
* Track pending and completed purchases

### 👥 Customers

* Add or edit customer records
* Optional credit/ledger tracking

### 💰 Expenses & Reports

* Record daily expenses
* View profit, sales, and expense reports
* Filter reports by date, cashier, or category

### 👨‍💼 User Roles & Authentication

* Secure login/logout system
* Roles: **Admin** (full access) and **Cashier** (limited access)
* Passwords hashed with `password_hash()`

### ⚙️ Settings

* Manage store name, logo, tax rate, and currency
* Print receipt customization

---

## 🎨 Dashboard Design

Modern and responsive **admin dashboard** built with inline Bootstrap CSS.

**Color Palette**

| Element | Color      | Code      |
| ------- | ---------- | --------- |
| Primary | Blue       | `#0f62fe` |
| Accent  | Teal       | `#00b894` |
| Sidebar | Dark Navy  | `#0b1226` |
| Text    | Muted Gray | `#6b7280` |
| Danger  | Red        | `#ff6b6b` |

---

## 🧠 Tech Stack

| Layer          | Technology                           |
| -------------- | ------------------------------------ |
| Backend        | Core PHP (PDO + prepared statements) |
| Database       | MySQL                                |
| Frontend       | Bootstrap 5 (CDN) + Inline CSS       |
| Authentication | PHP Sessions                         |
| Data Security  | Password hashing (`password_hash`)   |

---

## 🛠️ Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourusername/grocery-store-management.git
```

### 2. Move to project directory

```bash
cd grocery-store-management
```

### 3. Import database

* Create a MySQL database, e.g. `grocery_db`
* Import the included SQL file:

```bash
mysql -u root -p grocery_db < database.sql
```

### 4. Configure database connection

Edit the file:
`config/database.php`

```php
$dsn = "mysql:host=localhost;dbname=grocery_db;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
$pdo = new PDO($dsn, $user, $pass, $options);
```

### 5. Run the app

Place the folder inside your web root (e.g. `htdocs` for XAMPP).
Then open:

```
http://localhost/grocery-store-management
```

---

## 🔑 Default Login

| Role    | Username | Password   |
| ------- | -------- | ---------- |
| Admin   | admin    | admin123   |
| Cashier | cashier  | cashier123 |

*(You can change these in the database or through the Users module.)*

---

## 📂 Folder Structure

```
/app
  /controllers
  /models
  /views
/config
/public
  /uploads
/helpers
```

---

## 📸 Screenshots (Optional)

You can add screenshots like this:

```
![Dashboard](screenshots/dashboard.png)
![POS Page](screenshots/pos.png)
```

---

## 🔒 Security Notes

* Passwords are securely hashed.
* All SQL queries use prepared statements.
* CSRF and input sanitization helpers are used.
* Only Admin can manage users and system settings.

---

## 🧾 Example Reports

* Daily & Monthly Sales
* Low Stock Items
* Expenses Summary
* Profit & Loss

---

## 🤝 Contribution

1. Fork the repo
2. Create a new branch (`feature/new-module`)
3. Commit changes
4. Push to your branch
5. Open a Pull Request

---


---

## 📜 License

This project is open-source under the **MIT License** — you are free to use, modify, and distribute it.

---
