# 📄 **PageShift — PDF ↔ Word Converter**

A modern **PDF to Word** and **Word to PDF** converter built with **Native PHP**, **MySQL**, **HTML**, **CSS**, and **JavaScript**.  
Drop a file, pick a direction, and download the result — processed **locally** on your XAMPP server.

✨ Feel free to explore, contribute, and enhance the project! 🚀

---

## ✨ **Features**

- 🔁 **Two-way conversion** — **PDF → Word** and **Word → PDF**
- 🖱️ **Drag & drop upload** — or click **Select PDF / Select Word**
- 🧩 **Stacked layout** — text stays with text, photos stay on their own line (no wrap mix)
- 🖼️ **Keeps images** — screenshots and photos are carried into the output file
- 💻 **Code-friendly** — PHP / JSON blocks keep line breaks
- 📖 **Built-in User Manual** — full English guide from the top navigation (one click)
- 🕘 **Conversion history** — this browser session only · auto-clean after 24 hours
- 🔒 **Local processing** — files are not sent to a third-party cloud
- 📱 **Responsive UI** — modern PageShift design for desktop and mobile

---

## 🏗️ **Tech Stack**

| **Category** | **Technology** |
| --- | --- |
| 🎨 **Frontend** | HTML5, CSS3, JavaScript |
| ⚙️ **Backend** | Native PHP 8+ |
| 🗄️ **Database** | MySQL (`pdf_to_word`) |
| 📚 **Libraries** | PHPWord, smalot/pdfparser, TCPDF |
| 🖥️ **Server** | Apache (XAMPP) |

---

## 🖼️ **Project Screenshots**

<p align="center">
  <img src="docs/screenshots/01-home.png" alt="PageShift Home — PDF to Word" width="90%" />
  <br/>
  <em>🏠 Home / PDF → Word — brand intro, direction toggle, and drop zone</em>
</p>

<table>
  <tr>
    <td width="50%" align="center" valign="top">
      <img src="docs/screenshots/02-word-mode.png" alt="Word to PDF mode" width="100%" /><br/>
      <strong>📝 Word → PDF</strong><br/>
      <sub>Switch direction and drop a .docx file</sub>
    </td>
    <td width="50%" align="center" valign="top">
      <img src="docs/screenshots/03-user-manual.png" alt="User Manual modal" width="100%" /><br/>
      <strong>📖 User Manual</strong><br/>
      <sub>Step-by-step English guide from the top nav</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center" colspan="2">
      <img src="docs/screenshots/04-history.png" alt="How it works and history" width="80%" /><br/>
      <strong>🕘 How It Works &amp; History</strong><br/>
      <sub>Three steps plus session conversion records</sub>
    </td>
  </tr>
</table>

---

## 📦 **Requirements**

- 🧰 XAMPP (Apache + MySQL + PHP 8.0+) **or** a similar local stack
- 🧩 PHP extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `zip`, **`gd`**
- 📦 [Composer](https://getcomposer.org/) (to install PHP libraries)
- 🧭 Modern browser (Chrome / Edge / Firefox)
- ✅ JavaScript enabled

> ⚠️ **GD is required** for images. In `C:\xampp\php\php.ini` set `extension=gd`, then restart Apache.

---

## 🚀 **Installation**

1. 📁 Place the project in your web root, for example:
   ```text
   C:\xampp\htdocs\PDF_to_Word_Converter
   ```
2. 📦 Install PHP dependencies:
   ```bash
   composer install
   ```
3. ▶️ Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. 🗄️ The `pdf_to_word` database and `conversions` table are created automatically on first convert.
5. 🌐 Open the app:
   ```text
   http://localhost/PDF_to_Word_Converter/
   ```
6. 📖 Click **User Manual** in the top bar for the full guide.

---

## 🧭 **How to Use**

1. 🔀 Choose **PDF → Word** or **Word → PDF**
2. 📂 Select or drop a file (max **20 MB** · `.pdf` or `.docx`)
3. ⚙️ Click **Convert**
4. ⬇️ Download the result
5. 🕘 Scroll to **History** to grab earlier files from this session

### 📌 **Best results**

- ✅ Text-based PDFs (you can select/copy text)
- ✅ Modern Word **`.docx`** (legacy `.doc` is not supported)
- ❌ Scanned / image-only PDFs (no OCR in this version)

---

## 📁 **Folder Structure**

```text
PDF_to_Word_Converter/
├── api/
│   ├── convert.php          # Upload + convert
│   ├── download.php         # Secure download
│   └── history.php          # Session history
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── docs/
│   └── screenshots/         # README project screenshots
├── includes/
│   ├── config.php
│   ├── db.php
│   └── functions.php        # PDF ↔ Word conversion
├── sql/
│   └── schema.sql
├── uploads/                 # Incoming files (blocked by .htaccess)
├── outputs/                 # Converted files (blocked by .htaccess)
├── index.php
├── README.md
├── CONTRIBUTING.md
└── LICENSE
```

---

## 🗄️ **Database**

Table `conversions` stores each job: original name, direction (`pdf_to_word` / `word_to_pdf`), status, page count, and timestamps.

Files on disk are cleaned up after **24 hours**.

---

## ⚙️ **Configuration**

Edit `includes/config.php` for database credentials, max file size, and copyright:

```php
define('APP_COPYRIGHT_NOTICE', 'Copyright © 2026 Eng Choon Hao. All Rights Reserved.');
```

Default MySQL user is XAMPP `root` with an empty password.

---

## 🔒 **Security Notes**

- 🧪 Upload checks: MIME type + `%PDF-` / DOCX zip magic bytes
- 🛡️ All SQL uses PDO prepared statements
- 🧼 Outputs escaped with `htmlspecialchars`
- 🚫 Direct access to `uploads/` and `outputs/` is denied
- 🍪 Downloads are limited to the current browser session

---

## 🤝 **Contributing**

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

💡 To contribute, check the guidelines and open a PR with a clear description of what you changed.

---

## 📜 **License**

This project is licensed under the [MIT Non-Commercial License](LICENSE).

---

## ©️ **Copyright**

**Copyright © 2026 Eng Choon Hao. All Rights Reserved.**

Unauthorized copying or redistribution of this project without permission is prohibited.

---

⭐ If you find this project helpful, don't forget to **star** the repository! 🌟

Happy coding! 💻🎉📄
