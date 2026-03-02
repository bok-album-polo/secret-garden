# Security Report for Secret Garden Project

## 1. Local File Inclusion (LFI) in `render()`

**Vulnerability:** The `render()` method in `Controller.php` is vulnerable to Local File Inclusion.

**File:** `public-site-source/controllers/Controller.php`

**Description:** The `$view` parameter is concatenated with a directory path without any sanitization. An attacker who can control the `$view` parameter could use directory traversal characters (`../`) to include and potentially execute arbitrary PHP files from the server's filesystem.

**Example:**
In `GenericPageController.php`, the `show()` method calls `render()` with the `$route` parameter, which is derived from the user-controlled `$_GET['page']` or `$_SERVER['REQUEST_URI']`.
`$this->render($viewPath, ...);`
An attacker could request `/?page=../../../../etc/passwd` (on Linux) or similar to access sensitive files.

**Recommendation:** Sanitize the `$view` parameter to ensure it does not contain any directory traversal characters. A simple way to do this is to use `basename()` on the `$view` parameter before using it to construct the file path.

## 2. Unrestricted File Upload

**Vulnerability:** The `handleFileUploadToDb()` method in `Controller.php` allows unrestricted file uploads.

**File:** `public-site-source/controllers/Controller.php`

**Description:** The method checks the file size but does not validate the file type (MIME type) or extension. This allows an attacker to upload any type of file, including malicious scripts (e.g., PHP, HTML with JavaScript). While the files are stored in the database, they could be served back to users, leading to Cross-Site Scripting (XSS) or other attacks.

**Recommendation:** Implement strict file type validation. Use a whitelist of allowed MIME types and file extensions. For example, if only images are allowed, check for `image/jpeg`, `image/png`, etc.

## 3. User-Agent Injection

**Vulnerability:** The `getUserAgentId()` method in `Session.php` is vulnerable to injection attacks.

**File:** `public-site-source/controllers/Session.php`

**Description:** The method takes the `User-Agent` string from the request headers and inserts it directly into the database. Although it uses prepared statements, which prevents SQL injection, it does not sanitize the string. This can lead to second-order vulnerabilities like stored XSS if the user agent is ever displayed on a web page without proper HTML encoding, or log injection if it's written to logs.

**Recommendation:** Sanitize the `User-Agent` string before storing it. At a minimum, encode it for HTML to prevent XSS. For example, use `htmlspecialchars()`.
