<html>
<head>
<title>Polygon</title>
</head>
<body>
<h3>Reading Polygon Shapefile</h3>
<?

require_once('../shapefile.php');
try {
	$ShapeFile = new ShapeFile('polygon.shp');
	echo "<pre>";
	while ($record = $ShapeFile->getRecord(SHAPEFILE::GEOMETRY_ARRAY)) {
		if ($record['dbf']['deleted']) {
			echo "Deleted\n";
			continue;
		}
		// Geometry
		print_r($record['shp']);
		// DBF Data
		print_r($record['dbf']);
	}
} catch (ShapeFileException $e) {
	exit('Error '.$e->getCode().': '.$e->getMessage());
}

?>
</body>
</html>