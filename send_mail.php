<?php
/**
 * Imperial Hospital - Form & Email Handler with Secure SMTP
 * Receives all form submissions (Contact, Appointments, Inquiries)
 * Sends notification emails via SMTP (loaded from .env) and logs entries locally.
 */

// Enable CORS for local testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/smtp_mailer.php';

// ---------------------------------------------------------
// CONFIGURATION (Loaded from .env)
// ---------------------------------------------------------
$mailer = new SimpleSMTPMailer(__DIR__ . '/.env');

$to_email = $_ENV['MAIL_TO_EMAIL'] ?? getenv('MAIL_TO_EMAIL') ?: 'rb887087@gmail.com';
$site_name = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Imperial Hospital & Trauma Centre';
$from_email = $_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: 'no-reply@imperialhospital.local';

// ---------------------------------------------------------
// PARSE SUBMISSION DATA
// ---------------------------------------------------------
$data = $_POST;
if (empty($data)) {
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $data = json_decode($json_input, true) ?: [];
    }
}

// Extract Common Fields across all form types (Formidable, WPR, Metform, Custom)
$name = '';
$email = '';
$phone = '';
$date = '';
$time = '';
$message = '';
$subject = 'New Form Submission - ' . $site_name;
$page_title = $data['referer_title'] ?? $data['page'] ?? $data['page_title'] ?? '';

// Check Formidable fields
if (isset($data['item_meta']) && is_array($data['item_meta'])) {
    foreach ($data['item_meta'] as $k => $v) {
        if (empty($name) && is_string($v) && !filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) < 100 && !preg_match('/^[0-9\-\+\s\(\)]+$/', $v)) {
            $name = trim($v);
        } elseif (empty($email) && filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $email = trim($v);
        } elseif (empty($phone) && preg_match('/^[0-9\-\+\s\(\)]{7,20}$/', trim($v))) {
            $phone = trim($v);
        } elseif (empty($message) && strlen($v) > 0) {
            $message = trim($v);
        }
    }
}

// Check WPR form fields
if (isset($data['form_fields']) && is_array($data['form_fields'])) {
    foreach ($data['form_fields'] as $key => $val) {
        $val_str = is_array($val) ? implode(', ', $val) : trim($val);
        if (empty($val_str)) continue;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val_str) || stripos($key, 'date') !== false) {
            $date = $val_str;
        } elseif (preg_match('/^\d{1,2}:\d{2}(\s*(AM|PM))?$/i', $val_str) || stripos($key, 'time') !== false) {
            $time = $val_str;
        } elseif (filter_var($val_str, FILTER_VALIDATE_EMAIL) || stripos($key, 'email') !== false) {
            $email = $val_str;
        } elseif (preg_match('/^[0-9\-\+\s\(\)]{7,20}$/', $val_str) && (stripos($key, 'phone') !== false || stripos($key, 'tel') !== false || stripos($key, 'mob') !== false)) {
            $phone = $val_str;
        } elseif (stripos($key, 'message') !== false || strlen($val_str) > 60 || stripos($key, 'comment') !== false) {
            $message = $val_str;
        } elseif (empty($name) && (stripos($key, 'name') !== false || stripos($key, 'author') !== false)) {
            $name = $val_str;
        }
    }
}

// Fallback to top-level keys
if (empty($name)) {
    $name = $data['name'] ?? $data['author'] ?? $data['full_name'] ?? $data['mf-name'] ?? '';
}
if (empty($email)) {
    $email = $data['email'] ?? $data['user_email'] ?? $data['mf-email'] ?? '';
}
if (empty($phone)) {
    $phone = $data['phone'] ?? $data['telephone'] ?? $data['mobile'] ?? $data['mf-telephone'] ?? '';
}
if (empty($message)) {
    $message = $data['message'] ?? $data['comment'] ?? $data['msg'] ?? $data['mf-message'] ?? $data['description'] ?? '';
}
if (empty($date)) {
    $date = $data['date'] ?? $data['appointment_date'] ?? '';
}
if (empty($time)) {
    $time = $data['time'] ?? $data['appointment_time'] ?? '';
}

// Determine Subject
if (!empty($page_title)) {
    $subject = "New Inquiry / Appointment from: " . strip_tags($page_title);
} elseif (!empty($date) || !empty($time)) {
    $subject = "New Appointment Request - " . $site_name;
} else {
    $subject = "Contact Form Message from " . ($name ?: 'Visitor') . " - " . $site_name;
}

// ---------------------------------------------------------
// COMPOSE HTML EMAIL BODY
// ---------------------------------------------------------
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? $page_title ?? 'Direct';

$body_html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>' . htmlspecialchars($subject) . '</title>
<style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f6f9; margin: 0; padding: 20px; }
.container { max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.header { background: #0A1C43; color: #ffffff; padding: 25px; text-align: center; }
.header h2 { margin: 0; font-size: 22px; color: #ffffff; }
.header p { margin: 5px 0 0; color: #00d084; font-size: 14px; font-weight: bold; }
.content { padding: 30px; }
.table { width: 100%; border-collapse: collapse; margin-top: 15px; }
.table th, .table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eeeeee; font-size: 14px; }
.table th { width: 30%; background-color: #f9fafb; color: #555555; font-weight: 600; }
.message-box { background: #f8fafc; border-left: 4px solid #00d084; padding: 15px; margin-top: 20px; border-radius: 4px; }
.footer { background: #f9fafb; padding: 15px 30px; font-size: 12px; color: #888888; text-align: center; border-top: 1px solid #eeeeee; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>' . htmlspecialchars($site_name) . '</h2>
        <p>New Website Submission Received</p>
    </div>
    <div class="content">
        <table class="table">
            <tr><th>Name:</th><td><strong>' . htmlspecialchars($name ?: 'Not specified') . '</strong></td></tr>
            <tr><th>Email:</th><td>' . ($email ? '<a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a>' : 'Not specified') . '</td></tr>
            <tr><th>Phone:</th><td>' . ($phone ? '<a href="tel:' . htmlspecialchars($phone) . '">' . htmlspecialchars($phone) . '</a>' : 'Not specified') . '</td></tr>';

if (!empty($date) || !empty($time)) {
    $body_html .= '<tr><th>Date & Time:</th><td>' . htmlspecialchars(trim("$date $time")) . '</td></tr>';
}

if (!empty($page_title)) {
    $body_html .= '<tr><th>Page / Source:</th><td>' . htmlspecialchars($page_title) . '</td></tr>';
}

$body_html .= '<tr><th>Submitted At:</th><td>' . $timestamp . '</td></tr>
        </table>';

if (!empty($message)) {
    $body_html .= '<div class="message-box">
        <strong>Message / Details:</strong><br/>
        ' . nl2br(htmlspecialchars($message)) . '
    </div>';
}

$body_html .= '</div>
    <div class="footer">
        This notification was generated automatically from ' . htmlspecialchars($site_name) . ' website.
    </div>
</div>
</body>
</html>';

// ---------------------------------------------------------
// SEND EMAIL VIA SMTP (with fallback to PHP mail)
// ---------------------------------------------------------
$email_sent = false;
$smtp_error = '';

if (!empty($to_email)) {
    $email_sent = $mailer->send($to_email, $subject, $body_html, $email);
    if (!$email_sent) {
        $smtp_error = $mailer->getLastError();
        // Fallback to PHP native mail()
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $from_email . '>',
        ];
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . ($name ? "$name <$email>" : $email);
        }
        @$email_sent = mail($to_email, $subject, $body_html, implode("\r\n", $headers));
    }
}

// ---------------------------------------------------------
// LOG SUBMISSION LOCALLY (JSON & Text Log)
// ---------------------------------------------------------
$submission_entry = [
    'id' => uniqid('sub_'),
    'timestamp' => $timestamp,
    'ip' => $ip,
    'referer' => $referer,
    'recipient' => $to_email,
    'email_sent' => $email_sent,
    'smtp_error' => $smtp_error,
    'parsed' => [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'date' => $date,
        'time' => $time,
        'message' => $message,
        'subject' => $subject,
    ],
    'raw_data' => $data
];

// 1. JSON Database
$json_file = __DIR__ . '/submissions.json';
$existing_entries = [];
if (file_exists($json_file)) {
    $existing_entries = json_decode(file_get_contents($json_file), true) ?: [];
}
array_unshift($existing_entries, $submission_entry);
file_put_contents($json_file, json_encode($existing_entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// 2. Text Log
$log_file = __DIR__ . '/submissions.log';
$log_text = sprintf(
    "[%s] [%s] SMTP: %s | Recipient: %s | Name: %s | Email: %s | Phone: %s | Date: %s %s | Message: %s\n",
    $timestamp,
    $ip,
    $email_sent ? 'DELIVERED' : 'FAILED(' . $smtp_error . ')',
    $to_email,
    $name ?: 'N/A',
    $email ?: 'N/A',
    $phone ?: 'N/A',
    $date ?: '',
    $time ?: '',
    str_replace(["\r", "\n"], ' ', substr($message, 0, 150))
);
file_put_contents($log_file, $log_text, FILE_APPEND);

// ---------------------------------------------------------
// RETURN RESPONSE (AJAX JSON or HTML Page)
// ---------------------------------------------------------
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$accepts_json = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$has_ajax_param = isset($_POST['ajax']) || isset($_GET['ajax']) || isset($_POST['is_ajax']);

if ($is_ajax || $accepts_json || $has_ajax_param) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! Your submission has been received successfully. Our team will get in touch with you shortly.',
        'recipient' => $to_email,
        'email_sent' => $email_sent,
        'id' => $submission_entry['id']
    ]);
    exit;
}

// Standard Browser Form Submit HTML Response
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Imperial Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: #f4f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #222;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .icon {
            width: 70px;
            height: 70px;
            background: #e8f8f0;
            color: #00d084;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
        }
        h1 {
            font-size: 24px;
            color: #0A1C43;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .btn {
            display: inline-block;
            background: #00d084;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn:hover {
            background: #00b371;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Thank You!</h1>
        <p>Your message has been received successfully. Our team at <strong>Imperial Hospital</strong> will contact you shortly.</p>
        <a href="<?php echo htmlspecialchars($referer ?: 'index.html'); ?>" class="btn">Return to Website</a>
    </div>
</body>
</html>
