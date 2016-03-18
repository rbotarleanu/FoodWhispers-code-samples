<?php

/**
 * Backend for database interaction by the website.
 *
 */

class LocalUser 
{
	/**
	* Database object;
	* @var object
	*/
	private $_db;
	
	/**
	 *  Constructor for the PDO.
	 *  Checks if the database object already exists, if not
	 *  instantiates it.
	 *	
	 *  @param object $db
	 *  @return void
	*/
	public function __construct($db=NULL)
	{
		if(is_object($db)) {
			$this->_db = $db;
		} else {
			$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME;
            $this->_db = new PDO($dsn, DB_USER, DB_PASS);
		}
	}
	
	/* Searches for a location by name [and gps coordinates]. */
	public function searchForLocation($name, $address) 
	{
		$gps_search = null;
		$search_range = 20000; // 20km
		$cutoff = 5; // maximum number of edits between search name and database name 
		$total_result_size = 25; // maximum number of locations returned
		if($address != null) {
			$gps_search = $this->getCoordinatesByAddress($address);
		}
		$locations = array();
		// find locations by address
		$sql = $sql = "SELECT Address, Description, Category, Specific_local, Name, " .
		"Image, Paid_status, Webpage, Delivery_hours, Reservation_hours, Schedule," . 
		"Coordinates, UserID, Phone_no, Rating FROM users";
		if($stmt = $this->_db->prepare($sql)) {
			$stmt->execute();
			while($row = $stmt->fetch()) {
				if($gps_search != null) {
					// get search latitude and longitude
					$gps_search_fields = explode(":", $gps_search);
					$gps_search_lat = $gps_search_fields[0];
					$gps_search_long = $gps_search_fields[1];
				
					// get location latitude and longitude
					$gps_location_fields = explode(":", $row['Coordinates']);
					$gps_location_lat = $gps_location_fields[0];
					$gps_location_long = $gps_location_fields[1];
					
					// compute distance
					$dist = $this->gps2m(
						$gps_search_lat, $gps_search_long, 
						$gps_location_lat, $gps_location_long
					);
					if($dist > $search_range) continue;
				}
				// cutoff results that differ too much
				$lev_dist = levenshtein(strtolower ($name), strtolower ($row['Name']));
				if($lev_dist <= $cutoff) {
					array_push($locations, array(
						"UserId" => $row['UserID'],
						"Address" => $row['Address'],
						"Description" => $row['Description'],
						"Category" => $row['Category'],
						"Specific_local" => $row['Specific_local'],
						"Name" => $row['Name'],
						"Image" => $row['Image'],
						"Paid_status" => $row['Paid_status'],
						"Webpage" => $row['Webpage'],
						"Delivery_hours" => $row['Delivery_hours'],
						"Reservation_hours" => $row['Reservation_hours'],
						"Schedule" => $row['Schedule'],
						"Phone_no" => $row['Phone_no'],
						"Rating" => $row['Rating'],
						"LevDistance" => $lev_dist,
					));	
				}
			}
		}
		
		// order results by name
		// closure for usort call; compares levenshtein distances
		$cmp = function($a, $b) {
			return $a['LevDistance'] - $b['LevDistance'];
		};
		// sort and return the top results
		usort($locations, $cmp);
		// maximum return size is the minimum between the total_result_size and the locations size
		$max_entries = min(sizeof($locations), $total_result_size);
		$results = array();
		for($i = 0; $i < $max_entries; $i = $i + 1) {
			array_push($results, $locations[$i]);
		}
		return $results;		
	}
	
	/*
	*		Extracts menu information for the session user.
	*		@return mixed	an array of information, FALSE on error;
	*/
	public function getMenuInformation($category) 
	{
		$sql = "SELECT * FROM menu_items WHERE
				Category=:cat AND
				UserID=:uid";
		if($stmt = $this->_db->prepare($sql)) {
			$stmt->bindParam(":cat", $category, PDO::PARAM_STR);
			$stmt->bindParam(":uid", $_SESSION['UserID'], PDO::PARAM_STR);
			$stmt->execute();
			$cnt = 0;
			$results = FALSE;
			while($row = $stmt->fetch()) {
				$results[$cnt] = 
					array($row['Name'], 
						  $row['Ingredients'], 
						  $row['Image'],
						  $row['Quantity'],
						  $row['Unit'],
						  $row['Price'],
						  $row['Currency']);
				$cnt = $cnt + 1;
			}
			return $results;
		} else {
			return FALSE;
		}
	}
	
	/*
	*		Extracts event information for the session user.
	*		@return mixed	an array of information, FALSE on error;
	*/
	public function getEventInformation() 
	{
		$sql = "SELECT * FROM events WHERE
				UserID=:uid";
		if($stmt = $this->_db->prepare($sql)) {
			$stmt->bindParam(":uid", $_SESSION['UserID'], PDO::PARAM_STR);
			$stmt->execute();
			$cnt = 0;
			$results = FALSE;
			while($row = $stmt->fetch()) {
				$results[$cnt] = 
					array($row['Name'], 
						  $row['Date'], 
						  $row['Day'],
						  $row['Start_time'],
						  $row['Finish_time'],
						  $row['Description'],
						  $row['Image'],
						  $row['Price'],
						  $row['Currency']);
				$cnt = $cnt + 1;
			}
			return $results;
		} else {
			return FALSE;
		}
	}
	
		/*
	*	Updates all event information for a user.
	*	@return string 		A success/error message.
	*/
	public function updateEventInformation($data) 
	{
		foreach($data as $row) {
			$sql = "INSERT INTO events
				(Name, Date, Day, Start_time, Finish_time, Description, Image, Price, Currency, UserID)
				VALUES (:name,:date,:day, :start_time,:finish_time,:description,:image,:price,:currency,
				:uid)";
			if($stmt = $this->_db->prepare($sql)) {
				$stmt->bindParam(":uid", $_SESSION['UserID'], PDO::PARAM_STR);
				$stmt->bindParam(":name", $row[0], PDO::PARAM_STR);
				$stmt->bindParam(":date", $row[1], PDO::PARAM_STR);
				$stmt->bindParam(":day", $row[2], PDO::PARAM_STR);
				$stmt->bindParam(":start_time", $row[3], PDO::PARAM_STR);
				$stmt->bindParam(":finish_time", $row[4], PDO::PARAM_STR);
				$stmt->bindParam(":description", $row[5], PDO::PARAM_STR);
				$stmt->bindParam(":image", $row[6], PDO::PARAM_STR);
				$stmt->bindParam(":price", $row[7], PDO::PARAM_STR);
				$stmt->bindParam(":currency", $row[8], PDO::PARAM_STR);
				$stmt->execute();
				$stmt->closeCursor();
			} else {
					$message30 = "Error!Could not update event information. Please try again.";
				return "<script type='text/javascript'>alert('$message30');</script>";
			}
		}
			$message31 = "Success!Your events have been updated.";
				return "<script type='text/javascript'>alert('$message31');</script>";
	}
	
		/**
	*		Creates a new daily for the specified user.
	*/
	public function createDaily($uid, $daily) 
	{
		$sql = "INSERT INTO dailies(Day, Date_, Start_time, Finish_time, Price, Currency,".
			"Name, Ingredients, Image, Quantity, Unit, UserID)".
			"VALUES (:day,:date,:start_time,:finish_time,:price,:currency,:name,:ingredients,
					 :image,:quantity,:unit, :uid)";
		if($stmt = $this->_db->prepare($sql)) {
			$stmt->bindParam(':day', $daily[0], PDO::PARAM_STR);
			$stmt->bindParam(':date', $daily[1], PDO::PARAM_STR);
			$stmt->bindParam(':start_time', $daily[2], PDO::PARAM_STR);
			$stmt->bindParam(':finish_time', $daily[3], PDO::PARAM_STR);
			$stmt->bindParam(':price', $daily[4], PDO::PARAM_STR);
			$stmt->bindParam(':currency', $daily[5], PDO::PARAM_STR);
			$stmt->bindParam(':name', $daily[6], PDO::PARAM_STR);
			$stmt->bindParam(':ingredients', $daily[7], PDO::PARAM_STR);
			$stmt->bindParam(':image', $daily[8], PDO::PARAM_STR);
			$stmt->bindParam(':quantity', $daily[9], PDO::PARAM_STR);
			$stmt->bindParam(':unit', $daily[10], PDO::PARAM_STR);
			$stmt->bindParam(':uid', $uid, PDO::PARAM_STR);
			$stmt->execute();
			$stmt->closeCursor();
			$id = $this->_db->lastInsertId();
			if($id == 0) return NULL;
			else return $id;
		} else return NULL;
	}
	
	
?>