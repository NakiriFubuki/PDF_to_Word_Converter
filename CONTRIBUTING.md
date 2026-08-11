# Contributing to PageShift

Thank you for considering contributing to **PageShift** (PDF ↔ Word Converter)! Your help makes this project better.

## Table of Contents

- [How to Contribute](#how-to-contribute)
- [Contribution Guidelines](#contribution-guidelines)
- [Pull Request Process](#pull-request-process)
- [Reporting Issues](#reporting-issues)
- [License](#license)

---

## How to Contribute

1. **Fork the repository**
2. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR_USERNAME/PDF_to_Word_Converter.git
   cd PDF_to_Word_Converter
   ```
3. **Create a new branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Set up the project locally**
   - Place the folder under XAMPP `htdocs`
   - Start **Apache** and **MySQL**
   - Run `composer install` if `vendor/` is missing
   - Enable PHP `gd` in `php.ini` (needed for images)
   - Open `http://localhost/PDF_to_Word_Converter/`
5. **Make your changes**
6. **Test**
   - PDF → Word (text + images stay on separate lines)
   - Word → PDF (text + images stay on separate lines)
   - Upload validation, history, and download
   - User Manual open / close
7. **Commit your changes**
   ```bash
   git commit -m "Add: a meaningful commit message"
   ```
8. **Push to your branch**
   ```bash
   git push origin feature/your-feature-name
   ```
9. **Open a Pull Request**

---

## Contribution Guidelines

- Follow the existing PHP / JS / CSS style and naming conventions.
- Keep conversion output **stacked**: text blocks and photos must not wrap together.
- Use PDO prepared statements for all SQL.
- Escape output with `htmlspecialchars` in views.
- Write clear, concise commit messages.
- Do not commit secrets (real DB passwords, production credentials, etc.).
- Do not commit files from `uploads/` or `outputs/` except `.gitkeep` / `.htaccess`.

---

## Pull Request Process

- Open PRs against the default branch used by the maintainers.
- Link related issues when applicable.
- Describe what changed and how to test it.
- Wait for review before merging.
- Only maintainers should merge into the default branch.

---

## Reporting Issues

Found a bug or have a feature request? Open an Issue and include:

- Conversion direction (PDF → Word or Word → PDF)
- Steps to reproduce
- Expected vs actual behavior
- PHP / browser / OS versions when relevant
- Error messages (without secrets)

---

## License

By contributing, you agree that your contributions will be licensed under the [MIT Non-Commercial License](LICENSE).

Happy coding!
