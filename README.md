# PHP ShapeFile

PHP Class to read some types of *ESRI Shapefile* and its associated DBF into a PHP Array.  Note that the .shp file can only be read sequentially, the .shx is not used for seeking to specific records.

This fork will also write Shapefiles (and the index and DBF).

Only the following shape types are currently supported:

* Point
* Multipoint
* Polyline
* Polygon

Sample usage can be found in the /tests directory.

---

Documentation and examples at [http://gasparesganga.com/labs/php-shapefile](http://gasparesganga.com/labs/php-shapefile)
