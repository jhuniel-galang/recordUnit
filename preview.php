<?php
session_start();
require 'config.php';

require 'libs/PHPWord/src/PhpWord/Autoloader.php';
\PhpOffice\PhpWord\Autoloader::register();

require 'libs/PhpSpreadsheet/src/Bootstrap.php';


if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT * FROM documents 
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("File not found");
}

$doc = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Preview</title>
</head>
<body>

<h3><?= htmlspecialchars($doc['file_name']) ?></h3>

<?php if ($doc['file_type'] === 'pdf'): ?>
    <iframe 
        src="<?= htmlspecialchars($doc['file_path']) ?>" 
        width="100%" 
        height="600px">
    </iframe>
<?php endif; ?>

</body>
</html>
