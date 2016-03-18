<?php
/**
  *	Creates an object for the connection with the database.
  *	@return $db the database object (NULL on failure)
  */
function createLocalConnection() {
	// Create a database object
	try {
		$dsn = "mysql:host=".DB_HOST.";dbname="."cont_localuri_db";
        $db = new PDO($dsn, DB_USER, DB_PASS);
		return $db;
	} catch (PDOException $e) {
		return NULL;
	}
}

/*
 *	Verifying required params posted or not.
 *	@param Array $required_fields a list of the required parameters
 */
function verifyRequiredParams($required_fields) 
{
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
  *	Removes padding from a text that was encrypted using pkcs5.
  *	@param String $text the padded text
  *	@return String $text the unpadded text
  */
function pkcs5_unpad($text) 
{ 
    $pad = ord($text{strlen($text)-1}); 
    if ($pad > strlen($text)) return false; 
    if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false; 
    return substr($text, 0, -1 * $pad); 
} 

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) 
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json');
 
    echo json_encode($response);
}

/**
 * User Registration
 * url - /register
 * method - POST
 * params - username, email, password
 */
$app->post('/register', function() use ($app) 
{
            // check for required params
            verifyRequiredParams(array('username', 'email', 'password'));
			
            $response = array();
			
            // reading post params
            $name = $app->request->post('username');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
 
            $db = createDbConnection();
            $users = new Consumer($db);
            $res = $users->createUser($name, $password, $email);
 
            if($res == null) {
				$response['error'] = true;
				$response['message'] = "An error has occured during your registration." . 
								"Please try again.";
			} else if($res == 1) {
				$response['error'] = true;
				$response['message'] = "That username is already being used by someone else.";
			} else if($res == 2) {
				$response['error'] = true;
				$response['message'] = "A user with that email address already exists.";
			} else {
				$response['error'] = false;
				$response['Id'] = $res['id'];
				$response['api_key'] = encrypt($res['api_key'], $password);
			}
			echoRespnse(200, $response);
});
		
/**
 * User Login
 * url - /login
 * method - POST
 * params - username, password
 */
$app->post('/login', function() use ($app) 
{
            // check for required params
            verifyRequiredParams(array('username', 'password'));
 
            // reading post params
            $username = $app->request()->post('username');
            $password = $app->request()->post('password');
            $response = array();
			$db = createDbConnection();
            $users = new Consumer($db);
			$key = $users->getPassword($username);
			if($key == null) {
				 // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials!';
			} else {
				$password = trim(rtrim(decrypt($password, $key), "\0"));
				// check for correct username and password
				if ($users->checkLogin($username, $password)) {
					// get the user by username
					$user = $users->getDataByUsername($username);
				
					if ($user != NULL) {
						$response = array(
							"id" => $user["Id"],
							"error" => false,
							"email" => $user["Email"],
							"api_key" => encrypt($user["Api_key"], $password)
						);
					} else {
						// unknown error occurred
						$response['error'] = true;
						$response['message'] = "An error occurred. Please try again!";
					}
				} else {
					// user credentials are wrong
					$response['error'] = true;
					$response['message'] = 'Login failed. Incorrect credentials!';
				}
			}
			echoRespnse(200, $response);
});
		
/**
 * Listing all orders of particual user
 * method GET
 * url /orders          
 */
$app->get('/orders', 'authenticate', function() 
{
            global $uid;
			global $pass;
            $response = array();
			require_once '../inc/class.users.inc.php';
            $db = createLocalConnection();
			$users = new LocalUser($db);
            // fetching all user orders
            $result = $users->getOrdersConsumer($uid);
 
			$response["error"] = false;
            $response["orders"] = array();
            // looping through result and preparing orders array
            foreach ($result as $row) {
                $tmp = array();
				$tmp['OrderID'] = $row['OrderID'];
                $tmp['ConsumerID'] = $row['ConsumerID'];
                $tmp['Dishes'] = $row['Dishes'];
				$tmp['Name'] = encrypt($row['Name'], $pass);
				$tmp['Address'] = encrypt($row['Address'], $pass);
				$tmp['Phone_no'] = encrypt($row['Phone_no'], $pass);
				$tmp['Total_price'] = $row['Total_price'];
				$tmp['Currency'] = $row['Currency'];
				$tmp['Other'] = $row['Other'];
				$tmp['Confirmed'] = trim($row['Confirmed']);
				$tmp["Local"] = $users->getLocaleNameById($row['LocalID']);
				if($tmp["Local"] != null) {
					array_push($response["orders"], $tmp);	
				}
		    }
            echoRespnse(200, $response);
});
		
		/**
 * Listing all locales of particual user
 * method POST
 */
$app->post('/locales/', function() use($app) 
{
			// check for required params
            verifyRequiredParams(array('country', 'county', 'city', 'gps', 'range'));
        	
			// reading post params
            $country = $app->request()->post('country');
            $county = $app->request()->post('county');
            $city = $app->request()->post('city');
            $gps = $app->request()->post('gps');
			$range = $app->request()->post('range');
					
            require_once '../inc/class.users.inc.php';
			$db = createLocalConnection();
			$users = new LocalUser($db);

			$response = array();
			
			// get locales in range of consumer
            $result = $users->getLocalsInRange($country, $county, $city, $gps, $range);
			if(!((is_array($result)) or ($result instanceof Traversable))) {
				$response = array (
					"error" => true,
					"message" => "An unexpected error has occured. Please try again."
				);
			} else {
				$response = array (
					"error" => false,
					"locales" => $result
				);
			}
            echoRespnse(200, $response);
});

?>