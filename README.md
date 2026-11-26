# Bitsy: An Interactive 3D Web-Based Learning Game on Computer Hardware Basics

CC316 Group 3. A web app with three connected games: Identification, Classification,
and Build-a-PC in 3D with troubleshooting. Built with HTML, CSS, JavaScript, PHP, MySQL and Three.js.

---

## 1. Run it on XAMPP

1. Install XAMPP (PHP 7.4 or newer; PHP 8 recommended).
2. Copy this whole `bitsy` folder into `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\bitsy\index.php`.
3. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
4. Go to <http://localhost/phpmyadmin>, click **Import**, choose `database/bitsy.sql`, and click **Go**.
   This creates the `bitsy` database with all starter parts, devices, cables, scenarios and questions.
5. Open <http://localhost/bitsy> in Chrome, Edge or Firefox.

**Content manager (admin) login:** username `admin`, password `admin123`.
Change it right away under **Account**.

If your MySQL has a password, or a different port, edit `includes/config.php`.

### The 3D game and the internet
Game 3 uses Three.js. By default it loads from the internet (jsDelivr CDN).
For a computer lab without internet, download
`https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.min.js` once and save it as
`assets/vendor/three.module.min.js`. Bitsy then uses the local copy automatically.

If a computer cannot show 3D graphics (no WebGL), Game 3 shows a message and the learner can still play
Games 1 and 2.

---

## 2. How the proposal maps to the system

| Proposal objective | Where it is |
|---|---|
| Three connected games | `play.php` + `assets/js/game-identification.js`, `game-classification.js`, `game-build3d.js` |
| Interactive 3D virtual computer (place parts, fix faults) | `assets/js/game-build3d.js` (Three.js) |
| Easy / Medium / Hard, starting knowledge check, progress report after each level | `knowledge-check.php`, `api/submit.php`, report pop-up in `assets/js/common.js` |
| Admin module for parts, devices, cables and scenarios | `admin/` |
| Pre-test / post-test and SUS evaluation | `knowledge-check.php?kind=post`, `survey.php`, results in `admin/index.php` |

### Game 1: Identification
* Easy: peripherals and common devices. Medium: system unit parts. Hard: cables and ports.
* Two kinds of questions: *picture → name* and *name → picture*. After every answer the learner reads what the part does.
* Hard ends with a round that matches cables to their ports (tap, or drag and drop).

### Game 2: Classification
* Drag (or tap) each device into **Input**, **Output** or **Storage**.
* A device counts as correct only if it lands in the right box on the first try.

### Game 3: Build-a-PC in 3D
* **Desk view**: monitor, keyboard, mouse, speakers and the system unit. Click the system unit to look closer.
* **Back view**: the ports. **Inside view**: the open case.
* Easy: plug the peripheral devices into the right ports. Medium: install CPU, cooler, RAM, SSD and graphics card.
  Hard: build the full system unit with the power supply, hard drive, 24-pin power cable, SATA data cable and power cable.
* **Two ways to place a part** (both always work):
  - **Drag and drop:** the **Drag & drop parts** tab under the 3D view holds a card for every part. Drag a card onto a
    glowing box in the 3D view. Dropping close to a box is enough (it snaps to the nearest one). While holding a part,
    drag over the Desk, Inside or Back button to change the view. Placed parts shrink to small icons; click one to take it out.
  - **Click:** pick the part in the list on the right, then click a glowing box or choose the place from the list.
  The **How to play** tab next to it explains the controls.
* **Power On test** checks for missing parts, parts in the wrong slot, and loose cables (a SATA cable with only one end connected).
* **Troubleshooting**: up to two random scenarios of that level are loaded from the `scenarios` table. The same
  virtual computer is broken (unplugged cable, loose part, missing part, or power supply switched off) and the monitor
  shows a matching screen ("No signal", beeping black screen, "No bootable device", temperature warning…).
  The learner clicks the part that causes the problem.
* All 3D models are made from simple shapes in code, so no outside model files are needed.

---

## 3. Scoring and progress

* **Knowledge check** (12 questions, 4 per game). Each game's part gives the **baseline** (0–100%) and the starting level:
  0–25% → Easy, 50–75% → Medium (Easy stays open), 100% → Hard (all levels open).
* **Unlocking**: score **70%** or more on a level to open the next one (`PASS_MARK` in `includes/config.php`).
* **Games 1 and 2**: score = correct ÷ total × 100.
* **Game 3**: building is worth 50 points (minus 10 for each wrong slot or loose cable found by the Power On test);
  troubleshooting is worth 50 points shared by the scenarios (full points on the first try, 60% on the second, 30% on the third, 10% after that; a hint counts as one try).
  If a level has no active scenarios, the build is worth 100 (minus 20 per mistake).
* **Current game score** = average of the best score of each level played.
* **Improvement** (normalized gain, Hake 1998), calculated by the server from stored scores:
  `(current score − baseline) ÷ (100 − baseline)`. If the baseline is already 100%, it shows "Already 100% at the start".
* **Badges**: First Steps, Part Spotter, Sorting Star, PC Builder, Perfect Score, Fixer, Big Improver, Bitsy Graduate.
* **Post-test**: the same 12 questions, available once the learner has played every game. The report compares pre and post.
* **SUS**: the 10-item System Usability Scale. Score = (sum of odd items − 5 + 25 − sum of even items) × 2.5.

The admin statistics are anonymous: right and wrong answers are logged in the `attempts` table without a user id.

---

## 4. Folder guide (matches the team roles)

```
index.php, register.php, login.php, logout.php   Registration and login (PHP sessions)
dashboard.php, report.php                        Learner home and progress / final report
knowledge-check.php, survey.php                  Starting check, post-test, SUS survey
play.php                                         Loads a game level and its content from MySQL
api/submit.php                                   Saves a level result, gives badges, returns the progress report
admin/                                           Content manager: statistics, parts & devices, scenarios, account
includes/config.php                              Database and game settings
includes/db.php, auth.php, functions.php         Database, sessions/CSRF, progress and badge logic
includes/game_data.php                           Games, levels, badges, 3D parts list for scenarios
assets/js/icons.js                               Built-in drawings of parts, devices, cables and ports
assets/js/common.js                              Shared game helpers, drag and drop, level report
assets/js/game-identification.js                 Game 1
assets/js/game-classification.js                 Game 2
assets/js/game-build3d.js                        Game 3 (Three.js scene, build, Power On test, troubleshooting)
assets/css/style.css                             All styles
database/bitsy.sql                               Schema and starter content
uploads/items/                                   Pictures the admin uploads
```

Suggested ownership, following the proposal: Tura (login, connecting pages, reports), Copas (Games 1 and 2, learner
interface), Pakingan (database, `api/`, scoring, `admin/`), Abella (`game-build3d.js`, scenarios, testing).

---

## 5. Adding content (no code changes)

* **Parts / devices / cables**: Admin → Parts & devices → Add new. Set the level for Game 1 and/or Game 2.
  For Game 2 the device type must be Input, Output or Storage. Cables can have a port picture for the matching round.
  You can upload a PNG/JPG/GIF/WEBP picture (2 MB max) or pick a built-in drawing.
* **Scenarios**: Admin → Scenarios → Add a scenario. Pick the level, the faulty part and what is wrong with it.
  Only parts that exist in the 3D computer at that level can be chosen.
* **Archive** hides an item or scenario from the games without deleting its statistics. **Restore** brings it back.

---

## 6. Security notes

* Passwords are hashed with `password_hash`. All queries use prepared statements. All output is escaped.
* Every form and the score API use a CSRF token.
* `includes/` and `database/` are blocked from the browser by `.htaccess`; `uploads/` cannot run scripts.
* Uploaded pictures are checked by extension, size and real image type.

## 7. Credits

* Three.js (MIT license) for the 3D scene.
* All drawings, icons and 3D models are original and made in code.
* Hake, R. R. (1998) for normalized gain; Brooke, J. (1996) for the System Usability Scale.
