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
	    $ShapeFile = new ShapeFile('point.shp');
        $ShapeFile->addPoint(["x" => 3, "y" => 4]);
        $ShapeFile->addPoint(["x" => 15, "y" => 15]);
        $ShapeFile->addPoint(["x" => -25, "y" => 15]);
        $ShapeFile->write();

        echo "Creating Multipoint Shapefile <br/>";
        $ShapeFile = new ShapeFile('multipoint.shp');
        $ShapeFile->addMultipoint(
            [
                ["x" => 3, "y" => 4],
                ["x" => 15, "y" => 15],
                ["x" => -25, "y" => 13],
            ]
        );
        $ShapeFile->addMultipoint(
            [
                ["x" => 1, "y" => 5],
                ["x" => 2, "y" => -9],
                ["x" => -4, "y" => 4],
            ]
        );
        $ShapeFile->write();

        echo "Creating Polyline Shapefile <br/>";
        $ShapeFile = new ShapeFile('polyline.shp');
    	$ShapeFile->addPolyline(
		array(
			array(
				array( "x" =>   3, "y" =>  4),
				array( "x" =>  15, "y" => 15),
				array( "x" => -25, "y" => 13)
			),
			array(
				array( "x" =>  1, "y" =>  5),
				array( "x" =>  2, "y" => -9),
				array( "x" => -4, "y" =>  4)
			)
		)
	);
	$ShapeFile->addPolyline(
		array(
			array(
				array( "x" =>   1, "y" =>  1),
				array( "x" =>   9, "y" => 81),
				array( "x" =>  10, "y" =>100)
			),
			array(
				array( "x" => -1, "y" =>  1),
				array( "x" => -3, "y" =>  9),
				array( "x" => -6, "y" => 36)
			)
		)
	);
	    $ShapeFile->write();

        echo "Creating Polygon Shapefile <br/>";
        $ShapeFile = new ShapeFile('polygon.shp');
        $ShapeFile->setDbfSchema([
            ['id', DbfModel::NUMBER_TYPE, DbfModel::MEMORY_ADDRESS, DbfModel::FIELD_LENGTH],
            ['value', DbfModel::STRING_TYPE, DbfModel::MEMORY_ADDRESS, DbfModel::FIELD_LENGTH],
        ]);
        $ShapeFile->addPolygon(
            [
                [
                    ["x" => 1, "y" => 1],
                    ["x" => 1, "y" => 10],
                    ["x" => 10, "y" => 10],
                    ["x" => 10, "y" => 1],
                    ["x" => 1, "y" => 1],
                ],
                [
                    ["x" => 2, "y" => 2],
                    ["x" => 9, "y" => 2],
                    ["x" => 5, "y" => 9],
                    ["x" => 2, "y" => 2],
                ],
            ]
        );
        $ShapeFile->addDbRecord([
            'id' => 12,
            'value' => 'sss',
        ]);
        $ShapeFile->addPolygon(
            [
                [
                    ["x" => 1, "y" => 1],
                    ["x" => 1, "y" => 10],
                    ["x" => 10, "y" => 10],
                    ["x" => 10, "y" => 1],
                    ["x" => 1, "y" => 1],
                ],
                [
                    ["x" => 2, "y" => 2],
                    ["x" => 9, "y" => 2],
                    ["x" => 5, "y" => 9],
                    ["x" => 2, "y" => 2],
                ],
            ]
        );
        $ShapeFile->addDbRecord([
            'id' => 13,
            'value' => 'zzz',
        ]);
        if ($ShapeFile->write()) {
            $message = 'Done. Please go back in your web browser.';
        } else {
            $message = null;
            foreach ($ShapeFile->getErrors() as $error) {
                $message .= $error['message'] . '<br>';
            }
        }

    } catch (ShapeFileException $e) {
        exit('Error ' . $e->getCode() . ': ' . $e->getMessage());
    }

    ?>
</p>
<p><?= $message ?></p>
</body>
</html>