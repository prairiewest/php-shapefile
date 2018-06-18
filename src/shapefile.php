<?php
/***************************************************************************************************
 * ShapeFile - PHP Class to read an ESRI Shapefile and its associated DBF
 * Author            : Gaspare Sganga
 * Version            : 2.0
 * License            : MIT
 * Documentation    : http://gasparesganga.com/labs/php-shapefile
 *
 * This fork modified by Todd Trann
 * July 29, 2015
 ****************************************************************************************************/

// =================================================================================================
// Subsitute for PHP dBase functions
if (!function_exists('dbase_open')) {
    require_once(dirname(__FILE__) . '/dbase_functions.php');
}
// =================================================================================================
require_once 'dbfModel.php';

class ShapeFile
{

    // getShapeType return type
    const FORMAT_INT = 0;
    const FORMAT_STR = 1;
    // Geometry format
    const GEOMETRY_ARRAY = 0;
    const GEOMETRY_WKT = 1;
    // Shape types
    const SHAPE_UNDEFINED = -1;
    const SHAPE_NULL = 0;
    const SHAPE_POINT = 1;
    const SHAPE_POLYLINE = 3;
    const SHAPE_POLYGON = 5;
    const SHAPE_MULTIPOINT = 8;


    private static $error_messages = [
        'FILE_SHP' => [11, "Impossible to open SHP file: check if the file exists and is readable"],
        'FILE_DBF' => [12, "Impossible to open DBF file: check if the file exists and is readable"],
        'FILE_SHP_READ' => [13, "Unable to read SHP file"],
        'FILE_DBF_READ' => [14, "Unable to read DBF file"],
        'FILE_SHP_WRITE' => [15, "Unable to write SHP file"],
        'FILE_SHX_WRITE' => [16, "Unable to write SHX file"],
        'FILE_DBF_WRITE' => [17, "Unable to write DBF file"],
        'FILE_READ_CONFLICT' => [18, "Cannot read while in write mode"],
        'FILE_WRITE_CONFLICT' => [19, "Cannot write while in read mode"],
        'SHAPE_TYPE_NOT_SUPPORTED' => [21, "Shape type not supported"],
        'WRONG_RECORD_TYPE' => [22, "Record has wrong shape type"],
        'POLYGON_AREA_TOO_SMALL' => [31, "Polygon area too small: can't determine vertex orientation"],
    ];
    private static $binary_data_lengths = [
        'd' => 8,    // double (8 bytes or 4 words)
        'V' => 4,    // unsigned long (32 bit, little endian) (4 bytes or 2 words)
        'N' => 4    // unsigned long (32 bit, big endian) (4 bytes or 2 words)
    ];
    private static $shape_types = [
        self::SHAPE_NULL => 'Null',
        self::SHAPE_POINT => 'Point',
        self::SHAPE_MULTIPOINT => 'MultiPoint',
        self::SHAPE_POLYLINE => 'PolyLine',
        self::SHAPE_POLYGON => 'Polygon',
    ];

    private $shp;        // .shp file handle
    private $shp_file;    // .shp file name
    private $dbf;        // .dbf file handle
    private $dbf_file;    // .dbf file name
    private $shx;        // .shx file handle
    private $shx_file;    // .shx file name
    private $file_size;    // In bytes
    private $shape_type;
    private $bounding_box;    // Array of coordinates
    private $mode;        // "r" when reading a shapefile, "w" when writing, empty when not set
    private $record_number;
    private $records;    // Array of <shape_type> records
    /**
     * @var DbfModel
     */
    private $dbfModel;

    public function __construct($shp_file, $dbf_file = '', $shx_file = '')
    {
        if ($dbf_file == '') {
            $dbf_file = substr($shp_file, 0, -3) . 'dbf';
        }
        if ($shx_file == '') {
            $shx_file = substr($shp_file, 0, -3) . 'shx';
        }
        $this->shp_file = $shp_file;
        $this->dbf_file = $dbf_file;
        $this->shx_file = $shx_file;
        $this->operation = "";
        $this->shape_type = self::SHAPE_UNDEFINED;
        $this->record_number = 1;
        $this->bounding_box = [];
        $this->records = [];
        $this->file_size = 0;

        if(!$this->dbfModel){
            $this->dbfModel = new DbfModel();
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /******************** USER ACCESSIBLE METHODS ********************/

    public function getShapeType($format = self::FORMAT_INT)
    {
        if (!isset(self::$shape_types[$this->shape_type])) {
            return false;
        }
        if ($format == self::FORMAT_STR) {
            return self::$shape_types[$this->shape_type];
        } else {
            return $this->shape_type;
        }
    }

    /*
     * Can either be a class constant (integer)
     * or a string like "Point"
     */
    public function setShapeType($st)
    {
        if (is_numeric($st)) {
            if (!isset(self::$shape_types[$st])) {
                $this->Error('SHAPE_TYPE_NOT_SUPPORTED', $st);
            }
            $this->shape_type = $st;
        } else {
            $lookup = array_search($st, self::$shape_types);
            if ($lookup != false) {
                $this->shape_type = $lookup;
            } else {
                $this->Error('SHAPE_TYPE_NOT_SUPPORTED', $st);
            }
        }
    }

    /*
     * Returns an array [xmin,ymin,xmax,ymax]
     */
    public function getBoundingBox()
    {
        if (!isset($this->bounding_box) || count($this->bounding_box) == 0) {
            $this->bounding_box = $this->FindBoundsAllRecords();
        }
        return $this->bounding_box;
    }

    /*
     * Gets the next record from the shapefile when in read mode
     * This function can be called repeatedly, it will continue
     * until it runs out of records
     */
    public function getRecord($geometry_format = self::GEOMETRY_ARRAY)
    {
        if ($this->mode == "") {
            $this->open_read();
        }
        if ($this->mode != "r") {
            $this->Error('FILE_READ_CONFLICT');
        }
        if (ftell($this->shp) >= $this->file_size) {
            return false;
        }
        $record_number = $this->ReadData('N');
        $content_length = $this->ReadData('N');
        $shape_type = $this->ReadData('V');
        if ($shape_type != 0 && $shape_type != $this->shape_type) {
            $this->Error('WRONG_RECORD_TYPE', $shape_type);
        }
        switch ($shape_type) {
            case self::SHAPE_NULL:
                $shp = null;
                break;
            case self::SHAPE_POINT:
                $shp = $this->ReadPoint();
                break;
            case self::SHAPE_MULTIPOINT:
                $shp = $this->ReadMultiPoint();
                break;
            case self::SHAPE_POLYLINE:
                $shp = $this->ReadPolyLine();
                break;
            case self::SHAPE_POLYGON:
                $shp = $this->ReadPolygon();
                break;
        }
        if ($geometry_format == self::GEOMETRY_WKT) {
            $shp = $this->WKT($shp);
        }
        return [
            'shp' => $shp,
            'dbf' => dbase_get_record_with_names($this->dbf, $record_number),
        ];
    }

    /*
     * Format of input to addPoint:
     *     Array (
     *        [x] => float
     *        [y] => float
     *     )
     */
    public function addPoint($point)
    {
        if (!isset($this->shape_type) || $this->shape_type == self::SHAPE_UNDEFINED) {
            $this->setShapeType(self::SHAPE_POINT);
        }
        $this->addRecord([
                'x' => $point['x'],
                'y' => $point['y'],
            ]
        );
        $this->file_size = $this->file_size + 8 + (4 + 2 * 8); // Rec header + ( record )
    }

    /*
     * Format of input to addMultipoint:
     *     Array (
     *        Array (                // Point 1
     *            [x] => float
     *            [y] => float
     *        ),
     *        Array (                // Point 2
     *            [x] => float
     *            [y] => float
     *        )
     *     )
     */
    public function addMultipoint($points)
    {
        if (!isset($this->shape_type) || $this->shape_type == self::SHAPE_UNDEFINED) {
            $this->setShapeType(self::SHAPE_MULTIPOINT);
        }
        $xmin = PHP_INT_MAX;
        $ymin = PHP_INT_MAX;
        $xmax = 0 - PHP_INT_MAX;
        $ymax = 0 - PHP_INT_MAX;
        $numpoints = 0;
        foreach ($points as $point) {
            $xmin = min($xmin, $point['x']);
            $ymin = min($ymin, $point['y']);
            $xmax = max($xmax, $point['x']);
            $ymax = max($ymax, $point['y']);
            $numpoints++;
        }
        $this->addRecord([
                'xmin' => $xmin,
                'ymin' => $ymin,
                'xmax' => $xmax,
                'ymax' => $ymax,
                'numpoints' => $numpoints,
                'points' => $points,
            ]
        );
        $this->file_size = $this->file_size + 48 + $numpoints * 16;
    }

    /*
     * Format of input to addPolyline:
     *     Array (
     *         Array (                  // Line 1
     *            Array (                 // Line 1 point 1
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Line 1 point 2
     *                [x] => float
     *                [y] => float
     *            )
     *         ),
     *         Array (                  // Line 2
     *            Array (                 // Line 2 point 1
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Line 2 point 2
     *                [x] => float
     *                [y] => float
     *            )
     *         )
     *     )
     */
    public function addPolyline($parts)
    {
        if (!isset($this->shape_type) || $this->shape_type == self::SHAPE_UNDEFINED) {
            $this->setShapeType(self::SHAPE_POLYLINE);
        }
        $xmin = PHP_INT_MAX;
        $ymin = PHP_INT_MAX;
        $xmax = 0 - PHP_INT_MAX;
        $ymax = 0 - PHP_INT_MAX;
        $numparts = 0;
        $totalpoints = 0;
        foreach ($parts as $points) {
            $numpoints = 0;
            foreach ($points as $point) {
                $xmin = min($xmin, $point['x']);
                $ymin = min($ymin, $point['y']);
                $xmax = max($xmax, $point['x']);
                $ymax = max($ymax, $point['y']);
                $numpoints++;
            }
            $part['numpoints'] = $numpoints;
            $totalpoints = $totalpoints + $numpoints;
            $numparts++;
        }
        $this->addRecord([
                'xmin' => $xmin,
                'ymin' => $ymin,
                'xmax' => $xmax,
                'ymax' => $ymax,
                'numparts' => $numparts,
                'numpoints' => $totalpoints,
                'parts' => $parts,
            ]
        );
        $this->file_size = $this->file_size + 44 + $numparts * 4 + $totalpoints * 16;
    }

    /*
     * All Polygon arrays must have length of 4 or greater, and the last point
     * must be exactly the same as the first point to close the polygon
     *
     * Format of input to addPolygon:
     *     Array (
     *         Array (                  // Polygon 1 (must be clockwise)
     *            Array (                 // Ring 1 point 1
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 1 point 2
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 1 point 3
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 1 point 4
     *                [x] => float
     *                [y] => float
     *            )
     *         ),
     *         Array (                  // Polygon 2 (clockwise for new poly, counterclockwise for holes)
     *            Array (                 // Ring 2 point 1
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 2 point 2
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 2 point 3
     *                [x] => float
     *                [y] => float
     *            ),
     *            Array (                 // Ring 2 point 4
     *                [x] => float
     *                [y] => float
     *            )
     *         )
     *     )
     */
    public function addPolygon($parts)
    {
        if (!isset($this->shape_type) || $this->shape_type == self::SHAPE_UNDEFINED) {
            $this->setShapeType(self::SHAPE_POLYGON);
        }
        $xmin = PHP_INT_MAX;
        $ymin = PHP_INT_MAX;
        $xmax = 0 - PHP_INT_MAX;
        $ymax = 0 - PHP_INT_MAX;
        $numparts = 0;
        $totalpoints = 0;
        foreach ($parts as $points) {
            $numpoints = 0;
            foreach ($points as $point) {
                $xmin = min($xmin, $point['x']);
                $ymin = min($ymin, $point['y']);
                $xmax = max($xmax, $point['x']);
                $ymax = max($ymax, $point['y']);
                $numpoints++;
            }
            $part['numpoints'] = $numpoints;
            $totalpoints = $totalpoints + $numpoints;
            $numparts++;
        }
        $this->addRecord([
                'xmin' => $xmin,
                'ymin' => $ymin,
                'xmax' => $xmax,
                'ymax' => $ymax,
                'numparts' => $numparts,
                'numpoints' => $totalpoints,
                'parts' => $parts,
            ]
        );

        $this->file_size = $this->file_size + 44 + $numparts * 4 + $totalpoints * 16;
    }

    /**
     * After adding all the feature records, call this method
     * This method write data to files and after, is cleaning all properties
     * @return array|bool array of errors or true is success
     * @throws ErrorException
     * @throws ShapeFileException
     */
    public function write()
    {
        if(!$this->checkDbfData()){
            return false;
        }
        foreach ($this->records as $record) {
            $this->WriteRecord($record);
        }
        $this->close();

        return true;
    }

    /******************** PRIVATE ********************/

    private function open_read()
    {
        $this->mode = "r";
        if (!(is_readable($this->shp_file) && is_file($this->shp_file))) {
            $this->Error('FILE_SHP');
        }
        if (!(is_readable($this->dbf_file) && is_file($this->dbf_file))) {
            $this->Error('FILE_DBF');
        }

        $this->shp = fopen($this->shp_file, 'rb');
        if (!$this->shp) {
            $this->Error('FILE_SHP_READ');
        }
        $this->dbf = dbase_open($this->dbf_file, 0);
        if ($this->dbf === false) {
            $this->Error('FILE_DBF_READ');
        }

        $this->file_size = filesize($this->shp_file);
        $this->LoadHeader();
    }

    private function open_write()
    {
        $this->mode = "w";
        $this->shp = fopen($this->shp_file, 'wb');
        if (!$this->shp) {
            $this->Error('FILE_SHP_WRITE');
        }
        $this->shx = fopen($this->shx_file, 'wb');
        if (!$this->shx) {
            $this->Error('FILE_SHX_WRITE');
        }
        if (file_exists($this->dbf_file)) {
            unlink($this->dbf_file);
        }
        $scheme = $this->dbfModel->getSchema();
        $this->dbf = dbase_create($this->dbf_file, $scheme);
        if ($this->dbf === false) {
            $this->Error('FILE_DBF_WRITE');
        }
        $this->record_number = 1;
    }

    /*
     * No need to call this directly.
     * When writing, call write(). When reading, it will be called automatically.
     */
    private function close()
    {
        if ($this->shp && get_resource_type($this->shp) === 'file') {
            fclose($this->shp);
        }
        if ($this->dbf) {
            @dbase_close($this->dbf);
        }
        if ($this->shx && get_resource_type($this->shp) === 'file') {
            fclose($this->shx);
        }
        $this->mode = "";
        $this->shape_type = self::SHAPE_UNDEFINED;
        $this->record_number = 1;
        $this->bounding_box = [];
        $this->records = [];
        $this->file_size = 0;
    }

    private function addRecord($record)
    {
        // Each add function is responsible for incrementing the file size itself
        $this->records[] = $record;
    }

    private function ReadData($type)
    {
        if ($this->mode != "r") {
            $this->Error('FILE_READ_CONFLICT');
        }
        $data = fread($this->shp, self::$binary_data_lengths[$type]);
        if (!$data) {
            return null;
        }
        return current(unpack($type, $data));
    }

    private function WriteData($type, $data, $fh)
    {
        if (!isset($fh) || $fh === false) {
            $this->Error('FILE_SHP_WRITE');
        }
        if ($this->mode != "w") {
            $this->Error('FILE_WRITE_CONFLICT');
        }
        $packed = pack($type, $data);
        $bytes = fwrite($fh, $packed, self::$binary_data_lengths[$type]);
        if ($bytes == 0 || $bytes === false) {
            $this->Error('FILE_SHP_WRITE');
        }
    }

    private function ReadBoundingBox()
    {
        return [
            'xmin' => $this->ReadData('d'),
            'ymin' => $this->ReadData('d'),
            'xmax' => $this->ReadData('d'),
            'ymax' => $this->ReadData('d'),
        ];
    }

    private function WriteBoundingBox($fh, $bb)
    {
        if (!isset($bb) || count($bb) == 0) {
            $bb = $this->FindBoundsAllRecords();
        }
        $this->WriteData('d', $bb['xmin'], $fh);
        $this->WriteData('d', $bb['ymin'], $fh);
        $this->WriteData('d', $bb['xmax'], $fh);
        $this->WriteData('d', $bb['ymax'], $fh);
    }

    private function FindBoundsAllRecords()
    {
        $xmin = PHP_INT_MAX;
        $ymin = PHP_INT_MAX;
        $xmax = 0 - PHP_INT_MAX;
        $ymax = 0 - PHP_INT_MAX;
        switch ($this->shape_type) {
            case self::SHAPE_NULL:
                $xmin = 0;
                $ymin = 0;
                $xmax = 0;
                $ymax = 0;
                break;
            case self::SHAPE_POINT:
                foreach ($this->records as $point) {
                    $xmin = min($xmin, $point['x']);
                    $ymin = min($ymin, $point['y']);
                    $xmax = max($xmax, $point['x']);
                    $ymax = max($ymax, $point['y']);
                }
                break;
            case self::SHAPE_MULTIPOINT:
            case self::SHAPE_POLYLINE:
            case self::SHAPE_POLYGON:
                foreach ($this->records as $multipoint) {
                    $xmin = min($xmin, $multipoint['xmin']);
                    $ymin = min($ymin, $multipoint['ymin']);
                    $xmax = max($xmax, $multipoint['xmax']);
                    $ymax = max($ymax, $multipoint['ymax']);
                }
                break;
        }
        $this->bounding_box = ["xmin" => $xmin, "ymin" => $ymin, "xmax" => $xmax, "ymax" => $ymax];
        return $this->bounding_box;
    }

    private function WriteHeader($fh, $wordSize)
    {
        // File code magic number
        fseek($fh, 0, SEEK_SET);
        $this->WriteData('N', 9994, $fh);
        // Unused 5 bytes
        for ($i = 0; $i < 5; $i++) {
            $this->WriteData('N', 0, $fh);
        }
        // File size in words
        $this->WriteData('N', $wordSize, $fh);
        // File version
        $this->WriteData('V', 1000, $fh);
        // Shape type
        if (!isset(self::$shape_types[$this->shape_type])) {
            $this->Error('SHAPE_TYPE_NOT_SUPPORTED', $this->shape_type);
        }
        $this->WriteData('V', $this->shape_type, $fh);
        // Bounding box
        $this->WriteBoundingBox($fh, $this->bounding_box);
        // Range of Z
        $this->WriteData('d', 0, $fh);
        $this->WriteData('d', 0, $fh);
        // Range of M
        $this->WriteData('d', 0, $fh);
        $this->WriteData('d', 0, $fh);
        fseek($fh, 100, SEEK_SET);
    }

    private function LoadHeader()
    {
        fseek($this->shp, 32, SEEK_SET);
        $this->setShapeType($this->ReadData('V'));
        $this->bounding_box = $this->ReadBoundingBox();
        fseek($this->shp, 100, SEEK_SET);
    }

    /**
     * @param $record
     * @throws ErrorException
     * @throws ShapeFileException
     */
    private function WriteRecord($record)
    {
        if ($this->mode == "") {
            $this->open_write();
            // .shp size: file size + header length, convert bytes to 16 bit words
            $this->WriteHeader($this->shp, ($this->file_size + 100) / 2);
            // .shx size: num records * 8 + header length, convert bytes to 16 bit words
            $this->WriteHeader($this->shx, (count($this->records) * 8 + 100) / 2);
            $this->current_offset = 50; // Words
        }
        if ($this->mode != "w") {
            $this->Error('FILE_WRITE_CONFLICT');
        }
        $record_length = 0;
        switch ($this->shape_type) {
            case self::SHAPE_NULL:
                $record_length = $this->WriteNull();
                break;
            case self::SHAPE_POINT:
                $record_length = $this->WritePoint($record);
                break;
            case self::SHAPE_MULTIPOINT:
                $record_length = $this->WriteMultiPoint($record);
                break;
            case self::SHAPE_POLYLINE:
                $record_length = $this->WritePolyLine($record);
                break;
            case self::SHAPE_POLYGON:
                $record_length = $this->WritePolygon($record);
                break;
        }
        $dbfRecord = isset($record['dbfRecord']) ? $record['dbfRecord'] : null;
        // Update the .dbf file
        $this->updateDbfFile($dbfRecord);
        // Update the .shx file
        $this->WriteData('N', $this->current_offset, $this->shx);
        $this->WriteData('N', $record_length, $this->shx);
        $this->current_offset = $this->current_offset + $record_length + 4; // Account for next record header
        // Get ready for next record
        $this->record_number++;
    }

    private function ReadPoint()
    {
        return [
            'x' => $this->ReadData('d'),
            'y' => $this->ReadData('d'),
        ];
    }

    private function WriteNull()
    {
        $length = 2;
        // Header
        $this->WriteData('N', $this->record_number, $this->shp);
        $this->WriteData('N', $length, $this->shp);
        // Record
        $this->WriteData('V', self::SHAPE_NULL, $this->shp);
        return $length;
    }

    private function WritePoint($point)
    {
        $length = 10;
        // Header
        $this->WriteData('N', $this->record_number, $this->shp);
        // Storage: 2 words for shape type int + 4 words * 2 coordinates
        $this->WriteData('N', $length, $this->shp);
        // Record
        $this->WriteData('V', self::SHAPE_POINT, $this->shp);
        $this->WriteData('d', $point['x'], $this->shp);
        $this->WriteData('d', $point['y'], $this->shp);
        return $length;
    }

    private function ReadMultiPoint()
    {
        // Header
        $ret = [
            'bounding_box' => $this->ReadBoundingBox(),
            'numpoints' => $this->ReadData('V'),
            'points' => [],
        ];
        // Points
        for ($i = 0; $i < $ret['numpoints']; $i++) {
            $ret['points'][] = $this->ReadPoint();
        }
        return $ret;
    }

    private function WriteMultiPoint($multipoint)
    {
        $length = 24 + $multipoint['numpoints'] * 8;
        // Header
        $this->WriteData('N', $this->record_number, $this->shp);
        // Storage: 2 words for rec header + 22 words + 8 words * n points
        $this->WriteData('N', $length, $this->shp);
        // Record
        $this->WriteData('V', self::SHAPE_MULTIPOINT, $this->shp);
        $this->WriteBoundingBox($this->shp, [
            'xmin' => $multipoint['xmin'],
            'ymin' => $multipoint['ymin'],
            'xmax' => $multipoint['xmax'],
            'ymax' => $multipoint['ymax'],
        ]);
        $this->WriteData('V', $multipoint['numpoints'], $this->shp);
        // Points
        foreach ($multipoint['points'] as $point) {
            $this->WriteData('d', $point['x'], $this->shp);
            $this->WriteData('d', $point['y'], $this->shp);
        }
        return $length;
    }

    private function ReadPolyLine()
    {
        // Header
        $ret = [
            'bounding_box' => $this->ReadBoundingBox(),
            'numparts' => $this->ReadData('V'),
            'parts' => [],
        ];
        $tot_points = $this->ReadData('V');
        // Parts
        $parts_first_index = [];
        for ($i = 0; $i < $ret['numparts']; $i++) {
            $parts_first_index[$i] = $this->ReadData('V');
            $ret['parts'][$i] = [
                'numpoints' => 0,
                'points' => [],
            ];
        }
        // Points
        $part = 0;
        for ($i = 0; $i < $tot_points; $i++) {
            if (isset($parts_first_index[$part + 1]) && $parts_first_index[$part + 1] == $i) {
                $part++;
            }
            $ret['parts'][$part]['points'][] = $this->ReadPoint();
        }
        for ($i = 0; $i < $ret['numparts']; $i++) {
            $ret['parts'][$i]['numpoints'] = count($ret['parts'][$i]['points']);
        }
        return $ret;
    }

    private function WritePolyLine($polyline)
    {
        $length = 22 + $polyline['numparts'] * 2 + $polyline['numpoints'] * 8;
        // Header
        $this->WriteData('N', $this->record_number, $this->shp);
        // Storage:
        $this->WriteData('N', $length, $this->shp);
        // Record
        $this->WriteData('V', self::SHAPE_POLYLINE, $this->shp);
        $this->WriteBoundingBox($this->shp, [
            'xmin' => $polyline['xmin'],
            'ymin' => $polyline['ymin'],
            'xmax' => $polyline['xmax'],
            'ymax' => $polyline['ymax'],
        ]);
        $this->WriteData('V', $polyline['numparts'], $this->shp);
        $this->WriteData('V', $polyline['numpoints'], $this->shp);
        // Parts
        $index = 0;
        foreach ($polyline['parts'] as $points) {
            $this->WriteData('V', $index, $this->shp);
            $index += count($points);
        }
        // Points
        foreach ($polyline['parts'] as $points) {
            foreach ($points as $point) {
                $this->WriteData('d', $point['x'], $this->shp);
                $this->WriteData('d', $point['y'], $this->shp);
            }
        }
        return $length;
    }

    private function ReadPolygon()
    {
        // Read as Polyline
        $data = $this->ReadPolyLine();
        // Rings
        $i = -1;
        $parts = [];
        foreach ($data['parts'] as $rawpart) {
            if ($this->IsClockwise($rawpart['points'])) {
                $i++;
                $parts[$i] = [
                    'numrings' => 0,
                    'rings' => [],
                ];
            }
            $parts[$i]['rings'][] = $rawpart;
        }
        for ($i = 0; $i < count($parts); $i++) {
            $parts[$i]['numrings'] = count($parts[$i]['rings']);
        }
        return [
            'bounding_box' => $data['bounding_box'],
            'numparts' => count($parts),
            'parts' => $parts,
        ];
    }

    private function WritePolygon($polygon)
    {
        $length = 22 + $polygon['numparts'] * 2 + $polygon['numpoints'] * 8;
        // Header
        $this->WriteData('N', $this->record_number, $this->shp);
        // Storage:
        $this->WriteData('N', $length, $this->shp);
        // Record
        $this->WriteData('V', self::SHAPE_POLYGON, $this->shp);
        $this->WriteBoundingBox($this->shp, [
            'xmin' => $polygon['xmin'],
            'ymin' => $polygon['ymin'],
            'xmax' => $polygon['xmax'],
            'ymax' => $polygon['ymax'],
        ]);
        $this->WriteData('V', $polygon['numparts'], $this->shp);
        $this->WriteData('V', $polygon['numpoints'], $this->shp);
        // Parts
        $index = 0;
        foreach ($polygon['parts'] as $points) {
            $this->WriteData('V', $index, $this->shp);
            $index += count($points);
        }
        // Points
        foreach ($polygon['parts'] as $points) {
            foreach ($points as $point) {
                $this->WriteData('d', $point['x'], $this->shp);
                $this->WriteData('d', $point['y'], $this->shp);
            }
        }
        return $length;
    }

    private function IsClockwise($points, $exp = 1)
    {
        $num_points = count($points);
        if ($num_points < 2) {
            return true;
        }

        $num_points--;
        $tot = 0;
        for ($i = 0; $i < $num_points; $i++) {
            $tot += ($exp * $points[$i]['x'] * $points[$i + 1]['y']) - ($exp * $points[$i]['y'] * $points[$i + 1]['x']);
        }
        $tot += ($exp * $points[$num_points]['x'] * $points[0]['y']) - ($exp * $points[$num_points]['y'] * $points[0]['x']);

        if ($tot == 0) {
            if ($exp >= 1000000000) {
                $this->Error('POLYGON_AREA_TOO_SMALL');
            }
            return $this->IsClockwise($points, $exp * 1000);
        }

        return $tot < 0;
    }


    private function WKT($data)
    {
        if (!$data) {
            return null;
        }
        switch ($this->shape_type) {
            case 1:
                return 'POINT(' . $data['x'] . ' ' . $data['y'] . ')';

            case 8:
                return 'MULTIPOINT' . $this->ImplodePoints($data['points']);

            case 3:
                if ($data['numparts'] > 1) {
                    return 'MULTILINESTRING(' . $this->ImplodeParts($data['parts']) . ')';
                } else {
                    return 'LINESTRING' . $this->ImplodeParts($data['parts']);
                }

            case 5:
                $wkt = [];
                foreach ($data['parts'] as $part) {
                    $wkt[] = '(' . $this->ImplodeParts($part['rings']) . ')';
                }
                if ($data['numparts'] > 1) {
                    return 'MULTIPOLYGON(' . implode(', ', $wkt) . ')';
                } else {
                    return 'POLYGON' . implode(', ', $wkt);
                }
        }
    }

    private function ImplodeParts($parts)
    {
        $wkt = [];
        foreach ($parts as $part) {
            $wkt[] = $this->ImplodePoints($part['points']);
        }
        return implode(', ', $wkt);
    }

    private function ImplodePoints($points)
    {
        $wkt = [];
        foreach ($points as $point) {
            $wkt[] = $point['x'] . ' ' . $point['y'];
        }
        return '(' . implode(', ', $wkt) . ')';
    }


    private function Error($error, $details = '')
    {
        $code = self::$error_messages[$error][0];
        $message = self::$error_messages[$error][1];
        if ($details != '') {
            $message .= ': "' . $details . '"';
        }
        throw new ShapeFileException($message, $code);
    }

    public function addDbRecord($array)
    {
        if (!empty($this->records)) {
            end($this->records);
            $this->records[key($this->records)]['dbfRecord'] = $array;
            reset($this->records);
            return true;
        }
        return false;
    }

    /**
     * @param null $dbfData
     * @throws ErrorException
     */
    private function updateDbfFile($dbfData = null)
    {
        $this->dbfModel->checkDataWithSchema($dbfData);
        $errors = $this->dbfModel->getErrors();
        if(empty($errors)){
            if ($dbfData) {
                dbase_add_record($this->dbf, $dbfData);
            }
        }
    }

    public function getErrors()
    {
        return $this->dbfModel->getErrors();
    }

    /**
     * @throws ErrorException
     */
    private function checkDbfData()
    {
        foreach ($this->records as $recordId => $record) {
            if(isset($record['dbfRecord'])){
                $this->dbfModel->checkDataWithSchema($record['dbfRecord']);
            } else {
                $this->addDbRecord($this->setDefaultDbfRecord($recordId));
                $this->dbfModel->checkDataWithSchema($this->records[$recordId]['dbfRecord']);
            }
        }

        if(empty($this->dbfModel->getErrors())){
            return true;
        }
        return false;
    }

    /**
     * @param $schema
     * @throws ErrorException
     */
    public function setDbfSchema($schema)
    {
        $this->dbfModel->setSchema($schema);
    }

    private function setDefaultDbfRecord($recordId)
    {
        return $this->records[$recordId]['dbfRecord'] = ["id" => $recordId];
    }
}

class ShapeFileException extends Exception
{
}

?>