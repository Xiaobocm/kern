
### README.en.md

```markdown
# Kern – Lightweight Goal Manager

English | [中文](README.md)

## Introduction
**Kern** is a lightweight personal goal management tool built with PHP and MySQL. It lets you record daily tasks (Flags), track a big goal, switch themes and backgrounds, and supports multiple languages (Chinese, English, Japanese, Korean, etc.).  
This project is created by **Xiaobocm**, a junior high school student, in just half a day.

## Features
- 📝 Daily Flag management (CRUD, toggle completion)
- 📊 Statistics dashboard (day/month/year views, completion pie chart)
- 🎯 Big goal tracking with progress bar
- 🖼️ Custom backgrounds (upload/switch/delete)
- 🌙 Light/dark themes + system preference
- 🌐 Multi-language support (5 built‑in, extensible)
- 💾 Database backup (requires `mysqldump`)
- 📱 Mobile‑friendly

## Installation & Setup
1. **Environment**  
   - PHP 7.4+  
   - MySQL 5.7+  
   - Web server (Apache / Nginx)
2. **Download**  
   Place all files in your web root.
3. **Database config**  
   Edit the credentials at the top of `index.php`:
   ```php
   $db_host = 'localhost';
   $db_user = 'root';
   $db_pass = '';
   $db_name = 'flag_db';
