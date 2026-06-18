# GRID (Game Repository & Indie Devlog)

🌐 **Live Demo:** [grid.freedev.app](https://grid.freedev.app)

**GRID** is a collaborative web platform designed specifically to facilitate the Indie Game Developer ecosystem. This platform integrates project management, asset repositories, development documentation (devlogs), and bug reporting into one cohesive ecosystem.

## Key Features

- **Interactive Kanban Board**: Efficient team task management with full **HTML5 Drag-and-Drop** support. Status changes are automatically saved in the background thanks to AJAX and the Fetch API.
- **Asset Management (Asset Repository)**: A visual gallery for storing game assets (images, sounds, scripts) equipped with a live filtering and search system using Vanilla JS.
- **Devlog Creation**: A modern Rich Text Editor designed for writing development update logs for both the public and the team.
- **Bug Reporting & Tracking**: Members can report bugs and errors, while the development team can track their resolution status in real-time.
- **Role-Based Access Control (RBAC)**: Strict separation of access rights between **Admin** (system/organization managers) and **Member** (development team members).
- **Modern Dark-Mode UI**: A premium interface featuring a consistent Dark Mode across all application modules for visual comfort.

## Technology Stack

- **Frontend**: HTML5, CSS3 (Custom Variables), Vanilla JavaScript (no heavy external libraries).
- **UI Framework**: Bootstrap 5 (for the Grid System, responsiveness, and Modals).
- **Backend**: PHP 8 (Native).
- **Security**: Implementation of anti-CSRF Tokens, PDO (Prepared Statements) to prevent SQL Injection, and Rate Limiting for consecutive actions.
- **Database**: MySQL.

## Local Installation Guide

1. **Clone the Repository**
   Ensure you have Git installed on your computer.
   ```bash
   git clone https://github.com/Sjankaczar/GRID.git
   cd GRID
   ```

2. **Database Setup**
   - Create a new database in MySQL (e.g., `grid_db`).
   - Import the table structures and data from the `grid_db.sql` file provided in the root folder.

3. **Connection Configuration**
   - Open the `koneksi.php` file (or `includes/db_helpers.php` if instructed otherwise) and adjust your database connection credentials (such as using `root` with an empty password if you are using a standard XAMPP setup).

4. **Running the Server**
   Use PHP's built-in server to quickly run the project in the root folder:
   ```bash
   php -S localhost:8000
   ```

5. **Access the Application**
   Open your browser and visit:
   `http://localhost:8000`

## Developers & Collaborators

- **Rafli Djanuar**: Backend & Database Architecture
- **Sultan Hamdi**: UI/UX, DOM Manipulation, and AJAX Integration (including Kanban Drag and Drop features and live filters).

---
*Created as a final project for Web Programming.*
