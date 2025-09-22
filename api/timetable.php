<?php
// timetable.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// REST API URL
$api_url = "http://localhost/public_html/api/TimeAndAttendance.php";

// Fetch JSON data from API
$response = file_get_contents($api_url);
$attendances = json_decode($response, true);

// Convert full file path to relative URL
function imageUrl($fullPath) {
    if (empty($fullPath)) return '';

    $fullPath = str_replace('\\', '/', $fullPath);

    if (strpos($fullPath, '/public_html/') !== false) {
        $relative = substr($fullPath, strpos($fullPath, '/public_html/') + strlen('/public_html'));
    } else {
        $relative = $fullPath;
    }

    return $relative;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Timetable</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #eee; }
        tr:nth-child(even) { background: #f9f9f9; }
        img { max-width: 80px; max-height: 80px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; }
    </style>
</head>
<body>

<h2>Attendance Timetable</h2>

<?php if (empty($attendances)): ?>
    <p>No attendance records found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Username</th>
                <th>Work Date</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Break In</th>
                <th>Break Out</th>
                <th>Break Violation</th>
                <th>Clock In Photo</th>
                <th>Clock Out Photo</th>
                <th>Hours Worked</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendances as $att): ?>
            <tr>
                <td><?= htmlspecialchars($att['employee_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['employee_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['department'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['position'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['username'] ?? '-') ?></td>
                <td><?= htmlspecialchars(isset($att['clock_in']) ? substr($att['clock_in'], 0, 10) : '-') ?></td>
                <td><?= htmlspecialchars($att['clock_in'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['clock_out'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['break_in'] ?? 'Not Started') ?></td>
                <td><?= htmlspecialchars($att['break_out'] ?? 'Not Ended') ?></td>
                <td><?= htmlspecialchars($att['break_violation'] ?? '-') ?></td>

                <!-- Clock In Photo -->
                <td class="px-4 py-2 whitespace-nowrap">
                    <?php if(!empty($att['clock_in_image'])): ?>
                        <img 
                            src="/public_html/<?= htmlspecialchars(imageUrl($att['clock_in_image'])) ?>" 
                            data-src="/public_html/<?= htmlspecialchars(imageUrl($att['clock_in_image'])) ?>"
                            alt="Clock In Photo"
                        >
                    <?php else: ?>
                        <span style="color:#888;">No Photo</span>
                    <?php endif; ?>
                </td>

                <!-- Clock Out Photo -->
                <td class="px-4 py-2 whitespace-nowrap">
                    <?php if(!empty($att['clock_out_image'])): ?>
                        <img 
                            src="/public_html/<?= htmlspecialchars(imageUrl($att['clock_out_image'])) ?>" 
                            data-src="/public_html/<?= htmlspecialchars(imageUrl($att['clock_out_image'])) ?>"
                            alt="Clock Out Photo"
                        >
                    <?php else: ?>
                        <span style="color:#888;">No Photo</span>
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($att['hours_worked'] ?? '-') ?></td>
                <td><?= htmlspecialchars($att['remarks'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
