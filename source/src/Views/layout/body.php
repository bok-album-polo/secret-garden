<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Secret Garden - <?= $route_data['title'] ?? 'Home' ?></title>
	</head>
	<body>
		<?php include __DIR__ . '/header.php'; ?>
		<main>
			<?php include $page_template; ?>
		</main>
		<?php include __DIR__ . '/footer.php'; ?>
		<?php include __DIR__ . '/debug.php'; ?>
	</body>
</html>
