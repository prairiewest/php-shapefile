<html>
<head>
<title>Multipoint</title>
</head>
<body>
<h3>Reading Multipoint Shapefile</h3>
<?

require_once('../shapefile.php');
try {
	$ShapeFile = new ShapeFile('multipoint.shp');
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