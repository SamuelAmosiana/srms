# How to Install FPDF for PDF Generation

If you want to use PDF generation features in your enrollment system, here are several ways to install FPDF:

## Option 1: Manual Installation (Simplest)

1. Download FPDF from http://www.fpdf.org/
2. Extract the files
3. Create a `lib/fpdf/` directory in your project:
   ```
   mkdir lib
   mkdir lib/fpdf
   ```
4. Copy `fpdf.php` and the `font` folder to `lib/fpdf/`

## Option 2: Using Composer (Recommended for larger projects)

1. Initialize composer in your project (if not already done):
   ```
   composer init
   ```

2. Install FPDF via composer:
   ```
   composer require setasign/fpdf
   ```

3. Update your PHP code to use the composer autoloader:
   ```php
   require_once '../vendor/autoload.php';
   ```

## Option 3: Using a CDN or Direct Download

You can also download FPDF directly from:
- Official site: http://www.fpdf.org/
- GitHub: https://github.com/Setasign/FPDF

## After Installation

Once FPDF is installed, you can uncomment the PDF generation code in `admin/enrollment_approvals.php`:

```php
// Uncomment this line when FPDF is installed
// require_once '../lib/fpdf/fpdf.php';
```

And update the generateAcceptanceLetter function to use the PDF class as originally intended.

## Security Note

When generating files, make sure the `letters` directory is not directly accessible via web requests, or implement proper access controls to prevent unauthorized access to acceptance letters.