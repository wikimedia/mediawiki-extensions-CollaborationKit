<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Use of ContentModels
$cfg['suppress_issue_types'][] = 'PhanUndeclaredMethod';
$cfg['suppress_issue_types'][] = 'PhanUndeclaredVariable';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/EventLogging',
		'../../extensions/PageImages',
		'../../extensions/VisualEditor',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/EventLogging',
		'../../extensions/PageImages',
		'../../extensions/VisualEditor',
	]
);

return $cfg;
