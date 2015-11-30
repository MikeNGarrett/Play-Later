<?php
include_once 'config.php';
$database = new Database();

// Create some random text-encoded data for a line chart.
header('content-type: image/png');
$url = 'http://chart.apis.google.com/chart?';
$chd = 't:';
$chxl = '1:|';
$max = 0;

$query = $database->prepare('SELECT
				  COUNT(*) AS `releases`, `release_date`
			      FROM
				  `albums`
			      WHERE
				  `release_date` >= CURDATE() - INTERVAL 7 DAY
			      GROUP BY `release_date`');
$query->execute();

while ($row = $query->fetch()) {
	$chd .= $row['releases'] . ',';
	$chxl .= $row['release_date'] . '|';

	if ($row['releases'] > $max) $max = $row['releases'];
}
$chd = substr($chd, 0, -1);
$chxl = substr($chxl, 0, -1);

// Add data, chart type, chart size, and scale to params.
$chart = array(
	'chxl' => $chxl,
	'chxr' => '0,0,' . $max,
	'chxt' => 'y,x',
	'chbh' => 'a',
	'chs' => '600x225',
	'cht' => 'bvg',
	'chco' => '008000',
	'chds' => '0,' . $max,
	'chtt' => '8 latest dates',
	'chd' => $chd);

// Send the request, and print out the returned bytes.
$context = stream_context_create(
	array('http' => array(
			'method' => 'POST',
			'content' => http_build_query($chart))));
fpassthru(fopen($url, 'r', false, $context));
?>