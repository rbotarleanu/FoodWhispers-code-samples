<?php
    /* Does NOT include the HTML & Javascript front-end code.
     * This is only the code that handles the form submission.
	 */
	include_once "./common/base.php";
	$pageTitle = "Free Version";
	// check that all compulsory fields are completed
	if(!empty($_POST['username']) and
	   !empty($_POST['password1']) and
	   !empty($_POST['password2']) and
	   !empty($_POST['email']) and
   	   !empty($_POST['name']) and
	   !empty($_POST['country']) and
	   !empty($_POST['county']) and 
	   !empty($_POST['street']) and
	   !empty($_POST['street_no']) and
	   !empty($_POST['description']) and
	   ((!empty($_POST['category_list'])) or !empty($_POST['category_other'])) and
	   ((!empty($_POST['specific_list'])) or !empty($_POST['specific_other']))	
	   ):
	    // get a merged address
	    $_POST['address'] = $_POST['country'] . ']' . $_POST['county'] . ']' . $_POST['city'] . ']' . $_POST['street'] . ']'. $_POST['street_no'];
		$err = false;
		// check if the two passwords are identical
		if($_POST['password1'] != $_POST['password2']) {
			$message = "Passwords do not match";
			echo "<script type='text/javascript'>alert('$message');</script>";
			$err = true;
		}
		// check the password length
		if((strlen($_POST['password1']) < 8)) {
			$message1 = "Password must be at least 8 characters long.";
			echo "<script type='text/javascript'>alert('$message1');</script>";
			$err = true;
		}
		// check if the email seems valid
		if(strpos($_POST['email'], '@') === false) {
			$message2 = "Invalid email address.";
			echo "<script type='text/javascript'>alert('$message2');</script>";
			$err = true;
		}
		// check that a category was selected (or written in)
		if((empty($_POST['category_other']) and count($_POST['category_list']) == 0)) {
			$message3 = "Please specify at least one category.";
			echo "<script type='text/javascript'>alert('$message3');</script>";
			$err = true;
		}
		// check that a specific was selected (or written in)
		if((empty($_POST['specific_other']) and count($_POST['specific_list']) == 0)) {
			$message4 = "Please specify at least one specific.";
			echo "<script type='text/javascript'>alert('$message4');</script>";
			$err = true;
		}
		// if there were no errors, attempt to create an entry
		if($err == false) {
			// get a database handler
			include_once "./inc/class.users.inc.php";
			$users = new LocalUser($db);
			// create a merged category and specific
			$category = $_POST['category_other']."/";
			if(empty($_POST['category_other'])) $category = "";
			$specific = $_POST['specific_other']."/";
			if(empty($_POST['specific_other'])) $specific = "";
			if(!empty($_POST['category_list'])) {
				foreach($_POST['category_list'] as $cat) 
					$category = $category . $cat . "/";
			}
			if(!empty($_POST['specific_list'])) {
				foreach($_POST['specific_list'] as $spec) 
					$specific = $specific . $spec . "/";
			}
			// try to create an account
			$res = $users->createFreeAccount($category, $specific);
			if($res == null) {
				// Redirect browser on success
				echo '<script type="text/javascript"> window.location = "
				link_to_main_app_webpage(does not exist anymore)" </script>'; 
				die();
			} else {
				// got a non-null result, print the corresponding message
				echo $res;
				goto reload_page;
			}
			exit();
		} else goto reload_page;
	else:{
		/* not all fields were filled in correctly */
		$message5 = "Please fill all fields";
		echo "<script type='text/javascript'>alert('$message5');</script>";
		reload_page:	/* reload the page */
	}
	
?>