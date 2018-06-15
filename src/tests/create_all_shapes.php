<html>
<head>
<title>Testing</title>
</head>
<body>
<h3>Creating Shapefiles for Tests</h3>
<p>
<?

require_once('../shapefile.php');
try {
	echo "Creating Point Shapefile <br/>";
//	$ShapeFile = new ShapeFile('point.shp');
//	$ShapeFile->addPoint( array( "x"=>3, "y"=>4));
//	$ShapeFile->addPoint( array( "x"=>15, "y"=>15));
//	$ShapeFile->addPoint( array( "x"=>-25, "y"=>15));
//	$ShapeFile->write();
//
//	echo "Creating Multipoint Shapefile <br/>";
//	$ShapeFile = new ShapeFile('multipoint.shp');
//	$ShapeFile->addMultipoint(
//		array(
//			array( "x" =>   3, "y" =>  4),
//			array( "x" =>  15, "y" => 15),
//			array( "x" => -25, "y" => 13)
//		)
//	);
//	$ShapeFile->addMultipoint(
//		array(
//			array( "x" =>  1, "y" =>  5),
//			array( "x" =>  2, "y" => -9),
//			array( "x" => -4, "y" =>  4)
//		)
//	);
//	$ShapeFile->write();

//	echo "Creating Polyline Shapefile <br/>";
//	$ShapeFile = new ShapeFile('polyline.shp');
//	$ShapeFile->addPolyline(
//		array(
//			array(
//				array( "x" =>   3, "y" =>  4),
//				array( "x" =>  15, "y" => 15),
//				array( "x" => -25, "y" => 13)
//			),
//			array(
//				array( "x" =>  1, "y" =>  5),
//				array( "x" =>  2, "y" => -9),
//				array( "x" => -4, "y" =>  4)
//			)
//		)
//	);
//	$ShapeFile->addPolyline(
//		array(
//			array(
//				array( "x" =>   1, "y" =>  1),
//				array( "x" =>   9, "y" => 81),
//				array( "x" =>  10, "y" =>100)
//			),
//			array(
//				array( "x" => -1, "y" =>  1),
//				array( "x" => -3, "y" =>  9),
//				array( "x" => -6, "y" => 36)
//			)
//		)
//	);
//	$ShapeFile->write();

	echo "Creating Polygon Shapefile <br/>";	
	$ShapeFile = new ShapeFile('polygon.shp');
	$ShapeFile->addPolygon(
		array(
			array(
				array( "x" =>   1, "y" =>  1),
				array( "x" =>   1, "y" => 10),
				array( "x" =>  10, "y" => 10),
				array( "x" =>  10, "y" =>  1),
				array( "x" =>   1, "y" =>  1)
			),
			array(
				array( "x" =>  2, "y" =>  2),
				array( "x" =>  9, "y" =>  2),
				array( "x" =>  5, "y" =>  9),
				array( "x" =>  2, "y" =>  2)
			)
		)
	);
	echo "<pre>";
	var_dump(gettype('21s21'));
	echo "<pre>";die;
    $ShapeFile->addDbRecord([
            'id' => 'sas',
//            'deleted' => 2,
    ]);
	$ShapeFile->write();
	
} catch (ShapeFileException $e) {
	exit('Error '.$e->getCode().': '.$e->getMessage());
}

?>
</p>
<p>Done. Please go back in your web browser.</p>
</body>
</html>