<?php

/*
 * GpsImage v1.0
 *
 * get coordinates, adresses and google map images from pictures
 *
 * Licensed GPLv3 for open source use
 *
 * http://mincedmind.com
 * Copyright 2015 mincedmind.com
 */

class GpsImage {

    private $imagePath;
    private $imageData;
    private $mappingOptions;
    private $gps;
    private $coordinates;
    private $gpsArray = array('FileName', 'DateTimeOriginal', 'GPSVersion', 'GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude', 'GPSAltitudeRef', 'GPSAltitude', 'GPSMeasureMode', 'GPSDOP');
    private $locationValues;
    private $locations = array();
    private $apiKey;

	/**
     * Constructor
     *
     * @param str $apiKey Exiftool path
     * @param str $path Exiftool path
     * @return object $this
     */
    public function __construct($apiKey, $path=false, $zoom=10, $size='600x600') {
        $this->coordinates = new StdClass();
        $this->locationValues = new StdClass();
        $this->mappingOptions = new StdClass();

	$this->apiKey = $apiKey;
	    
        if($path) {
            $this->imagePath = $path;
        }else {
            $this->imagePath = false;
        }

        if(is_int($zoom) && $zoom > 0)
            $this->mappingOptions->zoom = $zoom;

        if(is_string($size))
            $this->mappingOptions->size = $size;
    }

    /**
     * inits image processing
     *
     * @param void
     * @return void
     */
    public function init($mapping=false) {
        $mapUri = false;

        if($this->imagePath === false)
            return false;

    	if(is_array($this->imagePath)) {
            $this->processImageStack();
        }else {
            $this->processSingleImage($this->imagePath);
        }

        // order objects to timeline
        usort($this->locations, array($this, "sortByDate"));

        if($mapping === true) {
            $mapUri = $this->initMapping();

            return $mapUri;
        }

        return $this->locations;
    }

    /**
     * initialize mapping options
     *
     * @param void
     * @return void
     */
    private  function initMapping() {
        $uri = 'https://maps.googleapis.com/maps/api/staticmap';

        $zoom = $this->buildZoom();
        $size = $this->buildSize();
        $type = $this->buildMaptype();
        $center = $this->buildCenter();
        $marker = $this->buildMarker();
	$key = $this->buildKey();

        return $uri . $key . $center . $zoom . $size . $type . $marker;
    }

    /**
     * returns the map type
     *
     * @param void
     * @return string $result
     */
    private function buildMaptype() {
        $result = 'roadmap';

        return '&maptype=' . $result;
    }

    /**
     * returns the userdefined size
     *
     * @param void
     * @return string $result
     */
    private function buildSize() {
        return '&size=' . $this->mappingOptions->size;
    }

    /**
     * returns the userdefined zoom factor
     *
     * @param void
     * @return string $result
     */
    private function buildZoom() {
        return '&zoom=' . $this->mappingOptions->zoom;
    }

    /**
     * Calculates the center of the view in google maps
     *
     * @param void
     * @return string $result
     */
    private function buildCenter() {
        $latitude = 0;
        $longitude = 0;
        $i = 0;

        if(count($this->locations) === 0)
            return 0;

        foreach($this->locations as $location) {
            $latitude += (float) $location->latitude;
            $longitude += (float) $location->longitude;
            $i++;
        }

        $latitude = $latitude / $i;
        $longitude = $longitude / $i;

        return '&center=' . $latitude . ',' . $longitude;
    }
	
    private function buildKey() {

        return '?key=' . $this->apiKey;
    }

    /**
     * Builds marker parameters for google maps API
     *
     * @param void
     * @return string $result
     */
    private function buildMarker() {
        $result = '';
        $i = 1;

        foreach($this->locations as $location) {
            $result .= '&markers=color:0x' . $this->randomHexColor() . '%7Clabel:' . $i . '%7C' . $location->latitude . ',' . $location->longitude;
            $i++;
        }

        return $result;
    }

    private function randomHexColor() {
        return strtoupper(dechex(mt_rand(0x000000, 0xFFFFFF)));
    }

    /**
     * processes a single image
     * saves the result ina n global result array
     *
     * @param void
     * @return void
     */
    private function processSingleImage($imagePath) {

        if(!file_exists($imagePath))
            return false;

        $this->gps = $this->setGpsData($imagePath);

        if(!array_key_exists('GPSLatitude', $this->gps))
            return false;

        $this->coordinates->latitude = $this->convertCoord($this->gps['GPSLatitude'][0], $this->gps['GPSLatitude'][1], $this->gps['GPSLatitude'][2], $this->gps['GPSLatitudeRef']);
        $this->coordinates->longitude = $this->convertCoord($this->gps['GPSLongitude'][0], $this->gps['GPSLongitude'][1], $this->gps['GPSLongitude'][2], $this->gps['GPSLongitudeRef']);
        $this->coordinates->altitude = $this->convertCoord($this->gps['GPSAltitude'][0], $this->gps['GPSAltitude'][1], $this->gps['GPSAltitude'][2], $this->gps['GPSAltitudeRef']);

        $this->getLocation();

        $this->locationValues->latitude = $this->coordinates->latitude;
        $this->locationValues->longitude = $this->coordinates->longitude;
        $this->locationValues->altitude = $this->coordinates->altitude;
        $this->locationValues->name = $this->gps['FileName'];
        $this->locationValues->tstamp = $this->gps['DateTimeOriginal'];

        $this->locations[] = $this->locationValues;

        //reset class variables
        $this->gps = false;
        $this->coordinates = new StdClass();
        $this->locationValues = new StdClass();
    }

    /**
     * calls processSingleImage for every delivered image
     *
     * @param void
     * @return array $result
     */
    private function processImageStack() {

        foreach($this->imagePath as $imagePath) {
            $this->processSingleImage($imagePath);
        }
    }

    /**
     * callback to compare values in objects
     *
     * @param std class object $a
     * @param std class object $b
     * @return int
     */
    private function sortByDate($a, $b) {

        return strcmp($a->tstamp, $b->tstamp);
    }

    /**
     * set GPS data in class
     *
     * @param void
     * @return array $result
     */
    private function setGpsData($imagePath) {
    	$result = array();

 		$storageArray = exif_read_data($imagePath);

 		foreach($this->gpsArray as $value) {
 			if(array_key_exists($value, $storageArray))
 				$result[$value] = $storageArray[$value];
 		}

 		return $result;
    }

    /**
     * sends a request to geoplugin.net and gets location information
     * google API delivers a pretty nice result.
     *
     * @param string $url
     * @return object $result
     */
    private function getLocation() {
        $location = false;
    	$result = new StdClass();
    	// $result->location = 'http://www.geoplugin.net/extras/location.gp?lat=' . $this->coordinates->latitude . '&long=' . $this->coordinates->longitude . '&format=php';
    	$result->nearest = 'http://www.geoplugin.net/extras/location.gp?lat=' . $this->coordinates->latitude . '&long=' . $this->coordinates->longitude . '&format=php';
    	$result->location = 'http://maps.googleapis.com/maps/api/geocode/json?latlng=' . $this->coordinates->latitude . ',' . $this->coordinates->longitude . '&sensor=false';

    	$result = json_decode($this->getUrlContents($result->location));
    	// $result->nearest = unserialize($this->getUrlContents($result->nearest));

        $location = $this->validateLocation($result);

        if(is_array($location))
    	   $location = $this->filterLocation($location);
    }

    /**
     * Validates the google maps result array
     *
     * @param array $gMapsArray
     * @return array $result
     */
    private function validateLocation($gMapsArray) {
        $result;

        if(!is_object($gMapsArray))
            return false;

        if(!is_array($gMapsArray->results))
            return false;

        $result = reset($gMapsArray->results);

        if(!is_array($result->address_components))
            return false;

        $result = $result->address_components;

        if(!is_array($result))
            return false;

        return $result;
    }

    /**
     * Filters the google maps result array
     *
     * @param array $details
     * @return object $result
     */
    private function filterLocation($details) {
        $this->locationValues = new StdClass();

        array_filter($details, array($this, 'getLocationValues'));
    }

    /**
     * Filters the google maps result array
     * Fills an object with location information
     *
     * @param array $details
     * @return void
     */
    private function getLocationValues($item) {
        $type = false;

        if(!is_object($item))
            return false;

        $type = reset($item->types);

        if($type === 'political')
            $type = end($item->types);

        $this->locationValues->$type = $item->long_name;
    }

    /**
     * get url contents
     *
     * @param string $url
     * @return string $result
     */
    private function getUrlContents($url) {
    	$return;
        $curl = curl_init();
        $timeout = 5;

        curl_setopt ($curl, CURLOPT_URL,$url);
        curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($curl, CURLOPT_CONNECTTIMEOUT, $timeout);

        $return = curl_exec($curl);

        curl_close($curl);

        return $return;
	}

	/**
     * get url contents
     *
     * @param string $degree
     * @param string $min
     * @param string $seconds
     * @param string $hemisphere
     * @return string $result
     */
	private function convertCoord($degrees, $minutes, $seconds, $hemisphere) {
        $degrees = $this->coord2float($degrees);
		$minutes = $this->coord2float($minutes);
		$seconds = $this->coord2float($seconds);

	    // $return = (float) $degree + ((((float) $min / 60) + ((float) $seconds / 3600)) / 100);
	    // $return = (float) $degree + ((float) $min / 60) + ((float) $seconds / 36000000);
	    $return = $degrees + $minutes / 60 + $seconds / 3600;

	    return ($hemisphere=='S' || $hemisphere=='W') ? $return *= -1 : $return;
	}

	/**
     * converts gps strings to float
     *
     * @param string $value
     * @return float $result
     */
	private function coord2float($val) {
        if(strlen($val) === 1 && strpos($val, '/') !== false)
            return 0;


        $parts = explode('/', $val);

		if (count($parts) == 0 || count($parts) == 1)
        	return (float) $val;

        return (float) $parts[0] / (float) $parts[1];
	}
}
