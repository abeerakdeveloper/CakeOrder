<?php
/**
 * Head + body shell for the new screens.
 * Set $pageTitle (and optionally $preCss array) before including.
 * Finish the page with includes/app_footer.php
 */
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$shellBranch = getBranchInfo();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($shellBranch['name']); ?></title>
<?php if (!empty($preCss)): foreach ((array)$preCss as $css): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
<?php endforeach; endif; ?>
<link rel="stylesheet" href="assets/app.css">
<script src="assets/pos_common.js"></script>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
