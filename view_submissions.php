<?php
/**
 * Imperial Hospital - Submissions Viewer Dashboard
 * View all inquiries and appointment bookings saved locally
 */

$json_file = __DIR__ . '/submissions.json';
$submissions = [];
if (file_exists($json_file)) {
    $submissions = json_decode(file_get_contents($json_file), true) ?: [];
}

if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'yes') {
    file_put_contents($json_file, '[]');
    $submissions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Submissions - Imperial Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f3f8; margin: 0; padding: 25px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #0A1C43; color: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header h1 { margin: 0; font-size: 22px; }
        .header .info { font-size: 13px; color: #00d084; font-weight: 500; }
        .nav-links a { color: #fff; text-decoration: none; margin-left: 15px; font-size: 13px; background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 4px; }
        .nav-links a:hover { background: rgba(255,255,255,0.3); }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eef2f6; font-size: 14px; }
        th { background: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        tr:hover { background-color: #fcfdfe; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: #e0f2fe; color: #0369a1; }
        .badge-date { background: #fef3c7; color: #b45309; }
        .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
        .empty-state svg { width: 60px; height: 60px; fill: #cbd5e1; margin-bottom: 15px; }
        .btn-clear { background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-clear:hover { background: #dc2626; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Imperial Hospital - Form Submissions</h1>
            <div class="info">Target Recipient: rb887087@gmail.com</div>
        </div>
        <div class="nav-links">
            <a href="./">Website Home</a>
            <a href="contact/">Contact Page</a>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 style="margin:0; font-size:18px;">Recent Inquiries & Appointments (<?php echo count($submissions); ?>)</h2>
            <?php if (!empty($submissions)): ?>
            <form method="POST" onsubmit="return confirm('Are you sure you want to clear all submission logs?');" style="margin:0;">
                <input type="hidden" name="clear_all" value="yes">
                <button type="submit" class="btn-clear">Clear All</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($submissions)): ?>
            <div class="empty-state">
                <p>No submissions recorded yet. Try filling out the contact form or appointment form on the website!</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Date / Time</th>
                            <th>Message / Inquiry</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td style="white-space:nowrap; font-size:12px; color:#64748b;"><?php echo htmlspecialchars($sub['timestamp'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($sub['parsed']['name'] ?? 'N/A'); ?></strong></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($sub['parsed']['email'] ?? ''); ?>"><?php echo htmlspecialchars($sub['parsed']['email'] ?? 'N/A'); ?></a></td>
                            <td><?php echo htmlspecialchars($sub['parsed']['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($sub['parsed']['date']) || !empty($sub['parsed']['time'])): ?>
                                    <span class="badge badge-date"><?php echo htmlspecialchars(trim(($sub['parsed']['date'] ?? '') . ' ' . ($sub['parsed']['time'] ?? ''))); ?></span>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:300px; word-break:break-word;"><?php echo nl2br(htmlspecialchars($sub['parsed']['message'] ?? 'N/A')); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($sub['parsed']['subject'] ?? 'Form'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
