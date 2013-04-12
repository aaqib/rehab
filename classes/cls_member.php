<?php
/************************************************************************
* @Author: Tinu Coman                                                   *
************************************************************************/
//error_reporting(E_ALL);
define('SANDBOX', false);
define('MAX_IMG_RESOLUTION', 1000000);

class member
{
	var $dbu;

	function member()
	{
		$this->dbu = new mysql_db();
		
		if(SANDBOX)
		{
			define('API_USERNAME', 'oleg_g_1291193039_biz_api1.mail.ru');
			define('API_PASSWORD', '1291193052');
			define('API_SIGN', 'A83lKGFYhcUgZ0ktSkwH3B0.KTEHAYo4yVcP8eQ.f2NBxKvLgVXndbQa');
		}
		else
		{
			$this->dbu->query("
						SELECT 
							*
						FROM 
							settings
						WHERE constant_name='PAYPAL_USERNAME' OR constant_name='PAYPAL_PASSWORD' OR constant_name='PAYPAL_SIGN'
						
						");
			while($this->dbu->move_next())
			{
				if($this->dbu->f('constant_name') == 'PAYPAL_USERNAME')
				{
					define('API_USERNAME', $this->dbu->f('value'));
				}
				elseif($this->dbu->f('constant_name') == 'PAYPAL_PASSWORD')
				{
					define('API_PASSWORD', $this->dbu->f('value'));
				}
				elseif($this->dbu->f('constant_name') == 'PAYPAL_SIGN')
				{
					define('API_SIGN', $this->dbu->f('value'));
				}
			}
		}
	}
		
	/****************************************************************
	* function join_now(&$ld)                                       *
	****************************************************************/
	function join_now(&$ld)
	{
		if(!$this->validate_join($ld))
		{
			return false;
		}
			
		global $user_level;
	    $this->dbu->query("
							SELECT 
								trainer_id
							FROM 
								trainer 
							WHERE 
								username = '".$ld['join_email']."'
						");
	    /* CHECK IF EMAIL EXIST IN DB, IF NOT, GENERATE A RANDOM PASSWORD, SAVE IT IN DB AND SEND MAIL TO THAT ADDRESS */
		if($this->dbu->move_next())
        {
            $ld['error'] = get_template_tag('affiliate', $ld['lang'], 'T.USER_EXIST');
            return false;
        }
		else 
        { 
        /*	require_once ('misc/PapApi.class.php');
            // login (as merchant)
            
            $session = new Gpf_Api_Session(AFFILIATES_API_M_URL);
            if(!$session->login(AFFILIATES_API_M_USERNAME, AFFILIATES_API_M_PASSWORD))
                {
                    die("Cannot login. Message: ".$session->getMessage());
                }
            $clickTracker = new Pap_Api_ClickTracker($session);
            try
                {
                    $clickTracker->track();
                }
            catch (Exception $e)
                {
                    die("Click tracker: ".$e->getMessage());
                }
            if ($clickTracker->getAffiliate() != null && $clickTracker->getAffiliate()->getValue('userid') != null)
            {
                $refferer_UID = $clickTracker->getAffiliate()->getValue('userid'); // prints affiliate userid
            }*/
            global $site_url, $site_name;
            $passwd = $ld['join_pass'];
            
            $trainer_id = $this->dbu->query_get_id("
                                INSERT INTO 
                                            trainer 
                                SET 
                                            username='".mysql_real_escape_string($ld['join_email'])."', 
                                            email='".mysql_real_escape_string($ld['join_email'])."',
                                            password='".mysql_real_escape_string($passwd)."', 
                                            create_date=NOW(), 
                                            is_trial='1', 
                                            expire_date='', 
                                            active = '1',
                                            affiliate_refferer_id = '".mysql_real_escape_string($refferer_UID)."',
                                            lang = '".mysql_real_escape_string($ld['join_language'])."'
                                ");
			$ld['trainer_id'] = $trainer_id;

			//add to activity table
			$activity_username = mysql_real_escape_string($this->dbu->field("select username from trainer where trainer_id=".$trainer_id));
			$this->dbu->query("insert into activity_list (activity_type, activity_date, modify_date, activity_text, trainer_id, is_showed)
							  values('sign_up', '".time()."', '".time()."', '$activity_username just signed up for our free trial', '".$trainer_id."', 0);");
			
			//create default additional notes
			$add_notes = "Stop any exercise that causes pain.
If you have any questions with an exercise, just email the clinic.";
			$this->dbu->query("
                                INSERT INTO 
                                    exercise_notes 
                                SET 
									trainer_id='".mysql_real_escape_string($trainer_id)."', 
									exercise_notes='".mysql_real_escape_string($add_notes)."'
                                ");
			
			//add test data
			$this->add_test_data_to_new_user($ld);
			
            // mail here
            $message_data=get_sys_message('nmjoin');
            $ordermail = $ld['join_email'];
            $fromMail = $message_data['from_email'];
            $replyMail = $message_data['from_email'];

            $body=$message_data['text'];
    
            $body=str_replace('[!SITE_NAME!]', $site_name, $body );
            $body=str_replace('[!SITE_URL!]', $site_url, $body );
            $body=str_replace('[!USER_NAME!]', $ld['join_email'], $body );
            $body=str_replace('[!PASSWORD!]', $ld['join_pass'], $body );
            $body=str_replace('[!ADMIN_MAIL!]', $fromMail, $body );
            $body = nl2br($body);
            
            require_once ('class.phpmailer.php');        
            include_once ("classes/class.smtp.php"); // optional, gets called from within class.phpmailer.php if not already loaded
    
            $mail = new PHPMailer();
            //$body             = file_get_contents('contents.html');
            //$body             = eregi_replace("[\]",'',$body);
            $mail->IsSMTP(); // telling the class to use SMTP
            $mail->SMTPDebug = 1; // enables SMTP debug information (for testing)
            // 1 = errors and messages
            // 2 = messages only
            $mail->SMTPAuth = true; // enable SMTP authentication
            $mail->Host = SMTP_HOST; // sets the SMTP server
            $mail->Port = SMTP_PORT; // set the SMTP port for the GMAIL server
            $mail->Username = SMTP_USERNAME; // SMTP account username
            $mail->Password = SMTP_PASSWORD; // SMTP account password
            $mail->SetFrom($replyMail, $replyMail);
            $mail->AddReplyTo($replyMail, $replyMail);
            $mail->Subject = $message_data['subject'];
            
            //$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
             
            $mail->MsgHTML($body);
            
            //$address = $receiver_email;
            $mail->AddAddress($ordermail, '');
            $mail->Send();
			$ld['error'] = get_template_tag('affiliate', $ld['lang'], 'T.CHECK_EMAIL');
		
            $registerEmail = 'info@rehabmypatient.com';
            //$registerEmail = 'ole_gi@miralex.com.ua';
            $mail1 = new PHPMailer();
            $mail1->IsSMTP(); // telling the class to use SMTP
            $mail1->SMTPDebug = 1; // enables SMTP debug information (for testing)
            $mail1->SMTPAuth = true; // enable SMTP authentication
            $mail1->Host = SMTP_HOST; // sets the SMTP server
            $mail1->Port = SMTP_PORT; // set the SMTP port for the GMAIL server
            $mail1->Username = SMTP_USERNAME; // SMTP account username
            $mail1->Password = SMTP_PASSWORD; // SMTP account password
            $body = 'User '.$ld['join_email'].' has been registered. Check admin panel.';
            $mail1->SetFrom($registerEmail, $registerEmail);
            $mail1->Subject = 'New user registered.';
            $mail1->MsgHTML($body);
            $mail1->AddAddress($registerEmail, '');
            $mail1->Send();
	
            return true;
		}

	 }

	function validate_join(&$ld)
		{
			$is_ok=true;
			
			if(!$ld['join_email'])
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.FILL_EMAIL')."<br>";
					$is_ok=false;
				}
			if($ld['join_email'] && !secure_email($ld['join_email']))
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.PROVIDE_EMAIL')."<br>";
					$is_ok=false;
				}
			if(!$ld['join_pass'])
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.FILL_PASS')."<br>";
					$is_ok=false;
				}
			if(!$ld['join_pass_repeat'])
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.FILL_REPEAT')."<br>";
					$is_ok=false;
				}
			if(strlen($ld['join_pass'])<5 || strlen($ld['join_pass_repeat'])<5)
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.OVER_5')."<br>";
					$is_ok=false;
				}
			if($ld['join_pass']!==$ld['join_pass_repeat'])
				{
					$ld['error'].=get_template_tag('affiliate', $ld['lang'], 'T.NOT_MATCH')."<br>";
					$is_ok=false;
				}
			return $is_ok;
		}
	 
	function make_pass_pattern()
		{
			/* PREPARE A NEW RANDOM PATTERN FOR THE RANDOM PASSWORD */
			$pass_gen_pattern = array(
							"0" => "x",
							"1" => "X",
							"2" => "c",
							"3" => "C",
							"4" => "v",
							"5" => "V",
							"6" => "0",
							"7" => "*",
						);
			$pattern = "";
			$patt = rand(6,9);
			$i = 0;
			while($i<$patt)
				{
					if($i==0) $pattern .= $pass_gen_pattern[rand(0,5)];
					else $pattern .= $pass_gen_pattern[rand(0,6)];
					$i++;
				}
			return $pattern;
		}

	function forgotpass(&$ld)
	{
		if(!$this->validate_forgotpass($ld))
		{
			return false;
		}
			
		global $user_level;
	    
	    $this->dbu->query("
							SELECT 
								trainer_id, password, email
							FROM 
								trainer 
							WHERE 
								username = '".mysql_real_escape_string($ld['username'])."'
						");
	    /* CHECK IF EMAIL EXIST IN DB, IF NOT, THROW ERR */
		if($this->dbu->move_next())
			{
				global $site_url, $site_name;
				
				// mail here
		$message_data=get_sys_message('fpne');
        $ordermail = $this->dbu->gf('email');
        $fromMail = $message_data['from_email'];
        $replyMail = $message_data['from_email'];

		$body=$message_data['text'];

		$body=str_replace('[!SITE_NAME!]', $site_name, $body );
		$body=str_replace('[!SITE_URL!]', $site_url, $body );
		$body=str_replace('[!USER_NAME!]', $ld['username'], $body );
		$body=str_replace('[!PASSWORD!]', $this->dbu->f('password'), $body );
		$body=str_replace('[!ADMIN_MAIL!]', $fromMail, $body );
		$body = nl2br($body);
                
        require_once ('class.phpmailer.php');        
        include_once ("classes/class.smtp.php"); // optional, gets called from within class.phpmailer.php if not already loaded

        $mail = new PHPMailer();
        //$body             = file_get_contents('contents.html');
        //$body             = eregi_replace("[\]",'',$body);
        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->SMTPDebug = 1; // enables SMTP debug information (for testing)
        // 1 = errors and messages
        // 2 = messages only
        $mail->SMTPAuth = true; // enable SMTP authentication
        $mail->Host = SMTP_HOST; // sets the SMTP server
        $mail->Port = SMTP_PORT; // set the SMTP port for the GMAIL server
        $mail->Username = SMTP_USERNAME; // SMTP account username
        $mail->Password = SMTP_PASSWORD; // SMTP account password
        $mail->SetFrom($replyMail, $replyMail);
        $mail->AddReplyTo($replyMail, $replyMail);
        $mail->Subject = $message_data['subject'];
        
        //$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
         
        $mail->MsgHTML($body);
        
        //$address = $receiver_email;
        $mail->AddAddress($ordermail, '');
        $mail->Send();

		$ld['error'] = get_template_tag($ld['pag'], $ld['lang'], 'T.CHECK_EMAIL');

		return true;

		}
		else 
		{
			$ld['error'] = get_template_tag($ld['pag'], $ld['lang'], 'T.USER_NOT_EXIST');
			return false;				
		}

	}
		
	function validate_forgotpass(&$ld)
	{
		$is_ok=true;
		
		if(!$ld['username'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_USER')."<br>";
			$is_ok=false;
		}
		return $is_ok;
	}
		
/* MEMBER PROFILE SECTION */

	function update_licence(&$ld)
    {

        if(!$this->validate_update_licence($ld))
        {
            $ld['pag']= "profile_choose_clinic"; 
            return false;
        }

        if($ld['is_clinic']==0) $update = "is_clinic='".mysql_real_escape_string($ld['is_clinic'])."', first_name='".mysql_real_escape_string($ld['single_name'])."'";
        else if($ld['is_clinic']==1) $update = "is_clinic='".mysql_real_escape_string($ld['is_clinic'])."', clinic_name='".mysql_real_escape_string($ld['clinic_name'])."'";
     
        $this->dbu->query("
                            UPDATE 
                                trainer 
                            SET 
                                ".$update." 
                            WHERE 
                                trainer_id=".$_SESSION[U_ID]." 
                        ");
		
		//check existing header paper
		if(!$this->dbu->field("select count(*) from trainer_header_paper where trainer_id='".$_SESSION[U_ID]."'"))
		{
			$this->dbu->query("SELECT first_name, surname, username, clinic_name FROM trainer WHERE trainer_id='".$_SESSION[U_ID]."' ");
			$this->dbu->move_next();
			$this->dbu->query("
							  INSERT INTO trainer_header_paper
							  SET trainer_id='".mysql_real_escape_string($_SESSION[U_ID])."',
							  first_name='".mysql_real_escape_string($this->dbu->f('first_name'))."',
							  surname='".mysql_real_escape_string($this->dbu->f('surname'))."',
							  email='".mysql_real_escape_string($this->dbu->f('username'))."',
							  company_name='".mysql_real_escape_string($this->dbu->f('clinic_name'))."' ");
		}
    
        $ld['error']="Licence Succesfully Saved.";

        return true;
    }
		
	function validate_update_licence(&$ld)
	{
		$is_ok=true;
/*	
		if(!$ld['clinic_name'])
			{
				$ld['error'].="Please fill in the 'Clinic Name' field."."<br>";
				$is_ok=false;
			}
*/
		return $is_ok;
	}
	
	function update_address(&$ld)
    {
		if(!$this->validate_update_address($ld))
        {
            $ld['pag']= "profile_put_address"; 
            return false;
        }
		
		$trainer_id = $_SESSION[U_ID] ? $_SESSION[U_ID] : $ld['trainer_id'];
		
		$update_str = "address = '".mysql_escape_string($ld['address'])."',";
		
		if(isset($ld['lang']) && ($ld['lang'] == 'us'))
			$update_str .= "state_zip = '".mysql_escape_string($ld['state_zip'])."'";
		else
			$update_str .= "city = '".mysql_escape_string($ld['city'])."', post_code = '".mysql_escape_string($ld['post_code'])."'";

		$this->dbu->query("
			update trainer_header_paper
			set $update_str
			where trainer_id = $trainer_id
		");
		
		return true;
	}
	
	function validate_update_address(&$ld)
	{
		$is_ok=true;
		
		return $is_ok;
	}
	
	function update_contact(&$ld)
    {
		if(!$this->validate_update_contact($ld))
        {
            $ld['pag']= "profile_put_contact"; 
            return false;
        }
		
		$trainer_id = $_SESSION[U_ID] ? $_SESSION[U_ID] : $ld['trainer_id'];
		
		$update_str = "email = '".mysql_escape_string($ld['email'])."',";
		$update_str .= "website = '".mysql_escape_string($ld['website'])."',";
		$update_str .= "phone = '".mysql_escape_string($ld['phone'])."',";
		$update_str .= "mobile = '".mysql_escape_string($ld['mobile'])."',";
		$update_str .= "fax = '".mysql_escape_string($ld['fax'])."'";
		

		$this->dbu->query("
			update trainer_header_paper
			set $update_str
			where trainer_id = $trainer_id
		");
		
		return true;
	}
	
	function validate_update_contact(&$ld)
	{
		$is_ok=true;
		if(!$ld['email'])
		{
			$ld['error'].="Please fill in the 'Email' field."."<br>";
			$is_ok=false;
		}
		return $is_ok;
	}
	
	function update_logo(&$ld)
    {
		if(!$this->validate_update_logo($ld))
        {
            $ld['pag']= "profile_put_logo"; 
            return false;
        }
		
		$trainer_id = $_SESSION[U_ID] ? $_SESSION[U_ID] : $ld['trainer_id'];
		
		if(!empty($_FILES['upload_image']['name']))
		{
			if($_FILES['upload_image']['error'] === 0)
				$success = $this->upload_custom_file($ld);
            $ld['pag'] = 'profile_header_paper';
		}
		
		$update_str = "himage_pos = '".mysql_escape_string($ld['himage_position'])."'";
		
		$this->dbu->query("
			update trainer_header_paper
			set $update_str
			where trainer_id = $trainer_id
		");
		return true;
	}
	
	function validate_update_logo(&$ld)
	{
		$is_ok=true;
		if($_FILES['upload_image']['error'] == 4)
		{
			$ld['error'].="Please select 'Logo' to upload."."<br>";
			$is_ok=false;
		}
		return $is_ok;
	}

	function add_profile(&$ld)
	{
		if(!$this->validate_add_profile($ld))
		{
			$ld['pag'] = "profile";
			return false;
		}
		$profile_id = $this->dbu->query_get_id("
										INSERT INTO 
													trainer_profile 
										SET 
													company_name='".$ld['company_name']."', 
													first_name='".$ld['first_name']."', 
													surname='".$ld['surname']."', 
													address='".$ld['address']."', 
													city='".$ld['city']."', 
													post_code = '".$ld['post_code']."',
													website = '".$ld['website']."',
													phone = '".$ld['phone']."',
													mobile = '".$ld['mobile']."',
													trainer_id = '".$_SESSION[U_ID]."'
										");

		if(!$profile_id)
		{
			$this->dbu->query("select trainer.*, trainer_profile.* from trainer 
				INNER JOIN trainer_profile ON trainer.profile_id=trainer_profile.profile_id
			where trainer.trainer_id=".$_SESSION[U_ID]." ");
			$this->dbu->move_next();
			$profile_id = $this->dbu->f('profile_id');
		}
	
		$get_mail = $this->dbu->field("SELECT email FROM trainer WHERE 1=1 AND trainer_id = ".$_SESSION[U_ID]);

		$ld['header_id']=$this->dbu->query_get_id("
													INSERT INTO 
																trainer_header_paper 
													SET 
																company_name='".$ld['company_name']."', 
																first_name='".$ld['first_name']."', 
																surname='".$ld['surname']."', 
																address='".$ld['address']."', 
																city='".$ld['city']."', 
																post_code = '".$ld['post_code']."',
																website = '".$ld['website']."',
																phone = '".$ld['phone']."',
																mobile = '".$ld['mobile']."',
																email = '".$get_mail."',
																trainer_id = '".$_SESSION[U_ID]."',
																profile_id = '".$profile_id."'
													");

		$this->dbu->query("UPDATE trainer SET first_name='".$ld['first_name']."', surname='".$ld['surname']."', 
			profile_id=".$profile_id." WHERE trainer_id=".$_SESSION[U_ID]." ");

		$this->dbu->query("UPDATE trainer_profile SET email='".$get_mail."' WHERE 1=1 AND trainer_id=".$_SESSION[U_ID]." AND profile_id=".$profile_id." ");
		$ld['profile_id'] = $profile_id;
		$ld['error']=get_template_tag('profile_add', $ld['lang'], 'T.PROFILE_ADDED');
		
		return true;
	}
		
	function validate_add_profile(&$ld)
	{
		$is_ok=true;

		if(!$ld['first_name'])
		{
			$ld['error'].=get_template_tag('profile_add', $ld['lang'], 'T.FILL_FIRST')."<br>";
			$is_ok=false;
		}
		if(!$ld['surname'])
		{
			$ld['error'].=get_template_tag('profile_add', $ld['lang'], 'T.FILL_SURNAME')."<br>";
			$is_ok=false;
		}
		if(!$ld['city'])
		{
			$ld['error'].=get_template_tag('profile_add', $ld['lang'], 'T.FILL_CITY')."<br>";
			$is_ok=false;
		}
		if(!$ld['address'])
		{
			$ld['error'].=get_template_tag('profile_add', $ld['lang'], 'T.FILL_ADDRESS')."<br>";
			$is_ok=false;
		}
		if(!$ld['post_code'])
		{
			$ld['error'].=get_template_tag('profile_add', $ld['lang'], 'T.FILL_POST')."<br>";
			$is_ok=false;
		}

		return $is_ok;
	}

	function update_email(&$ld)
	{
		//$changed_email = $this->dbu->field("SELECT email FROM trainer WHERE trainer_id=".$_SESSION[U_ID]." ");
		//$ld['changed_email'] = $changed_email;
		if(!$this->validate_update_email($ld))
		{
			$ld['pag']= "profile_edit_email"; 
			return false;
		}

		//check has profile username
		//$username = $this->dbu->field("SELECT username FROM trainer WHERE 1=1 AND trainer_id = ".$_SESSION[U_ID]);
		//if(!$username)
		$this->dbu->query("UPDATE trainer SET username='".mysql_real_escape_string($ld['email'])."' WHERE trainer_id=".$_SESSION[U_ID]." ");

		$this->dbu->query("UPDATE trainer SET email='".mysql_real_escape_string($ld['email'])."' WHERE trainer_id=".$_SESSION[U_ID]." ");

		//$get_profile_id = $this->dbu->field("SELECT profile_id FROM trainer WHERE 1=1 AND trainer_id = ".$_SESSION[U_ID]);
		//$this->dbu->query("UPDATE trainer_profile SET email='".$ld['email']."' WHERE 1=1 AND trainer_id=".$_SESSION[U_ID]." AND profile_id=".$get_profile_id." ");
	
		$ld['error']=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS_EMAIL');
		$_SESSION[USER_EMAIL] = $ld['email'];
		
		if($this->dbu->field("SELECT is_clinic FROM trainer WHERE trainer_id = ".$_SESSION[U_ID]) == 2)
			$ld['pag']= "profile_choose_clinic"; 
		
		return true;
	}
	
	function validate_update_email(&$ld)
	{
		$is_ok=true;

		if(!$ld['email'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_EMAIL')."<br>";
			$is_ok=false;
		}

		if($ld['email'] && !secure_email($ld['email']))
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.PROVIDE_EMAIL')."<br>";
			$is_ok=false;
		}

		return $is_ok;
	}

	function update_pass(&$ld)
	{
		$changed_pass = $this->dbu->field("SELECT password FROM trainer WHERE trainer_id=".$_SESSION[U_ID]." ");
		$ld['changed_pass'] = $changed_pass;
		
		if(!$this->validate_update_pass($ld))
		{
			$ld['pag']= "profile_edit_password"; 
			return false;
		}

		$this->dbu->query("UPDATE trainer SET password='".$ld['pass']."' WHERE trainer_id=".$_SESSION[U_ID]." ");
		
		$ld['error']=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS_PASS');
	
		return true;
	}
		
	function validate_update_pass(&$ld)
	{
		$is_ok=true;
		if($ld['changed_pass'] && !$ld['old_pass'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_OLD')."<br>";
			$is_ok=false;			
		}
		if(!$ld['pass'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_PASS')."<br>";
			$is_ok=false;			
		}
		if(!$ld['pass1'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_REPEAT')."<br>";
			$is_ok=false;			
		}
		if($ld['old_pass'])
		{
			$this->dbu->query("select trainer.password from trainer where trainer.trainer_id='".$_SESSION[U_ID]."' ");
			
			$this->dbu->move_next();
			if($this->dbu->f('password')!=$ld['old_pass'])
			{
				$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.OLD_NOT_MATCH')."<br>";
				$is_ok=false;
			}
		}		
		if ($ld['pass']!=$ld['pass1'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.PASS_NOT_MATCH')."<br>";
			$is_ok=false;
		}
		if(strlen($ld['pass'])<5)
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.OVER_5')."<br>";
			$is_ok=false;
		}
		return $is_ok;
	}

	function update_profile(&$ld)
	{

		if(!$this->validate_update_profile($ld))
		{
			$ld['pag']= "profile_edit"; 
			return false;
		}
			
		if(!$ld['profile_id'])
		{
			$this->dbu->query("select trainer.*, trainer_profile.* from trainer 
				INNER JOIN trainer_profile ON trainer.profile_id=trainer_profile.profile_id
			where trainer.trainer_id=".$_SESSION[U_ID]." ");
			$this->dbu->move_next();
			$ld['profile_id'] = $this->dbu->f('profile_id');
		}

		//		
		$this->dbu->query("							UPDATE 
																trainer_profile 
													SET 
																company_name='".$ld['company_name']."', 
																first_name='".$ld['first_name']."', 
																surname='".$ld['surname']."', 
																address='".$ld['address']."', 
																city='".$ld['city']."', 
																post_code = '".$ld['post_code']."',
																website = '".$ld['website']."',
																phone = '".$ld['phone']."',
																mobile = '".$ld['mobile']."' 
													WHERE trainer_id=".$_SESSION[U_ID]."
													");

		$this->dbu->query("UPDATE trainer SET first_name='".$ld['first_name']."', surname='".$ld['surname']."', 
			profile_id=".$ld['profile_id'].", clinic_name=''".$ld['company_name']." WHERE trainer_id=".$_SESSION[U_ID]." ");

		$ld['error']=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS');
		//$ld['pag'] = "profile";
	    return true;
	}
		
	function update_profile_notes(&$ld){

		$this->dbu->query("
						  UPDATE trainer
						  SET title_set='".mysql_real_escape_string($ld['title_set'])."',
						  email_set='".mysql_real_escape_string($ld['email_set'])."'
						  WHERE trainer_id='".$_SESSION[U_ID]."'");
		
		$this->dbu->query("UPDATE  trainer SET lang='".mysql_real_escape_string($ld['language'])."' WHERE trainer_id='".$_SESSION[U_ID]."'");
		//check exists in db
		$this->dbu->query("SELECT exercise_note_id FROM exercise_notes WHERE trainer_id=".$_SESSION[U_ID]." ");
		
		if(!$this->dbu->move_next())
			$this->dbu->query("INSERT INTO exercise_notes SET exercise_notes = '".mysql_real_escape_string($ld['exercise_notes'])."', trainer_id=".$_SESSION[U_ID]." ");
		else
			$this->dbu->query("UPDATE exercise_notes SET exercise_notes = '".mysql_real_escape_string($ld['exercise_notes'])."' WHERE trainer_id=".$_SESSION[U_ID]." ");
			
		//save custom outgoing msg
		$this->dbu->query("select * from custom_out_message where trainer_id='".$_SESSION[U_ID]."' and in_use='1'");
		if($this->dbu->move_next())
			$this->dbu->query("update custom_out_message set message_content='".mysql_real_escape_string($ld['custom_outgoing_message'])."', in_use='1' where trainer_id='".$_SESSION[U_ID]."'");
		else
			$this->dbu->query("insert into custom_out_message (trainer_id, message_content, in_use) values('".$_SESSION[U_ID]."', '".mysql_real_escape_string($ld['custom_outgoing_message'])."', '1')");
		

		$ld['error'] = get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS');
        $_GET['pag'] = 'profile';
        
		if($_COOKIE['language'] != $ld['language'])
		{
			setcookie('language', $ld['language'], 0, '/');
			$lang = ($ld['language'] == 'us') ? 'us/' : '';
			header("location: /".$lang.'index.php?pag=profile&success=1&error='.urlencode($ld['error']));
			exit;
		}
		
		return true;
	}
		
	function validate_update_profile(&$ld)
	{
		$is_ok=true;

		if(!$ld['first_name'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_FIRST')."<br>";
			$is_ok=false;
		}
		if(!$ld['surname'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_SURNAME')."<br>";
			$is_ok=false;
		}
		if(!$ld['address'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_ADDRESS')."<br>";
			$is_ok=false;
		}
		if(!$ld['city'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_CITY')."<br>";
			$is_ok=false;
		}
		if(!$ld['post_code'])
		{
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_POST')."<br>";
			$is_ok=false;
		}
		return $is_ok;
	}
	
	function update_custom_header(&$ld)
	{
		$this->check_header_paper_exists();
		
		$this->dbu->query("
							UPDATE 
								trainer_header_paper 
							SET 
								company_name='".mysql_real_escape_string($ld['company_name'])."',
								address='".mysql_real_escape_string($ld['address'])."',
								first_name='".mysql_real_escape_string($ld['first_name'])."',
								surname='".mysql_real_escape_string($ld['surname'])."',
								post_code='".mysql_real_escape_string($ld['post_code'])."',
								website='".mysql_real_escape_string($ld['website'])."',
								phone='".mysql_real_escape_string($ld['phone'])."',
								mobile='".mysql_real_escape_string($ld['mobile'])."',
								email='".mysql_real_escape_string($ld['email'])."',
								city='".mysql_real_escape_string($ld['city'])."',
								fax='".mysql_real_escape_string($ld['fax'])."',
								state_zip='".mysql_real_escape_string($ld['state_zip'])."',
								himage_pos='".mysql_real_escape_string($ld['himage_position'])."'
							WHERE 
								trainer_id='".$_SESSION[U_ID]."'");
        
        //$ld['pag'] = 'dashboard';
		$success = true;
		if(!empty($_FILES['upload_image']['name']))
		{
			//if(!$this->check_image_size($_FILES['upload_image']['tmp_name']))
			//{
			//	$ld['error'] = 'Cannot Upload Logo. Please contact Support.';
			//	return false;
			//}

			if($_FILES['upload_image']['error'] === 0)
				$success = $this->upload_custom_file($ld);
            $ld['pag'] = 'profile_header_paper';
			//$ld['error']='unchecked';
		}
		else
		{
			$cur_logo = $this->dbu->field("select logo_image from trainer_header_paper where trainer_id='".$_SESSION[U_ID]."'");
			if($cur_logo)
			{
				$file_info = pathinfo($cur_logo);
				$orig_image=$file_info['filename'].'_orig.'.$file_info['extension'];
				
				$orig_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$orig_image;
				$img_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$cur_logo;
				
				if(!$this->check_image_size($_FILES['upload_image']['tmp_name']))
				{
					$ld['error'] = 'Can not upload logo. Contact to support, please.';
					return false;
				}
				
				$cur_image = $this->createImgFromFile($img_path);
				$cur_image_sx = imagesx($cur_image);
				$cur_image_sy = imagesy($cur_image);
				unset($cur_image);

				if($cur_image_sx != $ld['width'] || $cur_image_sy != $ld['height'])
				{
					if(!file_exists($orig_path))
						copy($img_path, $orig_path);
					
					@unlink($img_path);
					copy($orig_path, $img_path);
					
					$cur_image = $this->createImgFromFile($img_path);
					$cur_image_sx = imagesx($cur_image);
					$cur_image_sy = imagesy($cur_image);
					unset($cur_image);

					$max_width = ($ld['width'] <= 400 ) ? $ld['width'] : 400;
					$max_height = ($ld['height'] <= 220 ) ? $ld['height'] : 220;
					
					$img_ext = pathinfo($img_path, PATHINFO_EXTENSION);		
					
					if($ld['width'] && $ld['height'])
					{
						$ld['width'] = $ld['width'] >= $max_width  ? $max_width : $ld['width'];
						$ld['height'] = $ld['height'] >= $max_height  ? $max_height : $ld['height'];
						$this->resize($img_path, $ld['width'], $ld['height'], $cur_logo, $img_ext, 75);
					}
					else
					{
						if($cur_image_sy>$max_height)
							$this->resize($img_path, 0, $max_height, $f_title, $img_ext, 75);
						
						$cur_image = $this->createImgFromFile($img_path);
						$cur_image_sx = imagesx($cur_image);
						$cur_image_sy = imagesy($cur_image);
						unset($cur_image);
						
						if($cur_image_sx>$max_width)
							$this->resize($img_path, $max_width, 0, $f_title, $img_ext);
					}
				}
			}
		}
        if($success){
            $ld['error']=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS');
            return true;
        } else{
            return false;
        }
	}
	
	function resize_existing_image(&$ld)
	{
		$cur_logo = $this->dbu->field("select logo_image from trainer_header_paper where trainer_id='".$_SESSION[U_ID]."'");

		if($cur_logo)
		{
			$file_info = pathinfo($cur_logo);
			$orig_image=$file_info['filename'].'_orig.'.$file_info['extension'];
			
			$orig_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$orig_image;
			$img_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$cur_logo;
			
			$cur_image = $this->createImgFromFile($img_path);
			$cur_image_sx = imagesx($cur_image);
			$cur_image_sy = imagesy($cur_image);

			if($cur_image_sx != $ld['width'] || $cur_image_sy != $ld['height'])
			{
				if(!file_exists($orig_path))
					copy($img_path, $orig_path);
				
				@unlink($img_path);
				copy($orig_path, $img_path);
				
				$cur_image = $this->createImgFromFile($img_path);

				$max_width = ($ld['width'] <= 400 ) ? $ld['width'] : 400;
				$max_height = ($ld['height'] <= 220 ) ? $ld['height'] : 220;
				
				$img_ext = pathinfo($img_path, PATHINFO_EXTENSION);		
				
				if($ld['width'] && $ld['height'])
				{
					$ld['width'] = $ld['width'] >= $max_width  ? $max_width : $ld['width'];
					$ld['height'] = $ld['height'] >= $max_height  ? $max_height : $ld['height'];
					$this->resize($img_path, $ld['width'], $ld['height'], $cur_logo, $img_ext, 75);
				}
				else
				{
					if(imagesy($cur_image)>$max_height)
						$this->resize($img_path, 0, $max_height, $f_title, $img_ext, 75);
					
					$cur_image = $this->createImgFromFile($img_path);
					
					if(imagesx($cur_image)>$max_width)
						$this->resize($img_path, $max_width, 0, $f_title, $img_ext);
				}
			}
		}
	}
		
	function check_header_paper_exists()
	{
		$this->dbu->query("SELECT header_id FROM trainer_header_paper WHERE trainer_id='".$_SESSION[U_ID]."' ");
		$this->dbu->move_next();
		if(!$this->dbu->f('header_id'))
		{
			$this->dbu->query("SELECT first_name, surname FROM trainer WHERE trainer_id='".$_SESSION[U_ID]."' ");
			$this->dbu->move_next();
			
			$this->dbu->query("
							  INSERT INTO trainer_header_paper
							  SET trainer_id='".mysql_real_escape_string($_SESSION[U_ID])."',
								first_name='".mysql_real_escape_string($this->dbu->f('first_name'))."',
								surname='".mysql_real_escape_string($this->dbu->f('last_name'))."'
							");
			
		}
	}

	function validate_update_custom_header(&$ld){
		$is_ok= true;

		if(!$ld['first_name']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_FIRST');
			$is_ok = false;
		}
		if(!$ld['surname']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_SURNAME');
			$is_ok = false;
		}
		if(!$ld['address']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_ADDRESS');
			$is_ok = false;
		}
		if(!$ld['post_code']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_POST');
			$is_ok = false;
		}
		if(!$ld['mobile']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_MOBILE');
			$is_ok = false;
		}
		
		return $is_ok;
	}

	function upload_file(&$ld)
	{
        global $_FILES, $script_path, $is_live;
        $allowed['.gif']=1;
        $allowed['.jpg']=1;
        $allowed['.jpeg']=1;
        $f_ext=substr($_FILES['upload_image']['name'],strrpos($_FILES['upload_image']['name'],"."));
        if(!$allowed[strtolower($f_ext)])
        {
        	$ld['error']="Only jpg, jpeg and gif files are accepted.";
        	return false;
        }
        
        if(!is_numeric($_SESSION[U_ID]))
        {
        	$ld['error'].="Error.".'<br>';
        	return false;
        }
        else 
        {
         	$this->dbu->query("SELECT trainer_id, logo_image FROM trainer_profile WHERE trainer_id='".$_SESSION[U_ID]."'");
        	if(!$this->dbu->move_next())
        	{
	        	$ld['error'].="Error.".'<br>';
	        	return false;
        	}
        	else 
        	{
        		@unlink( $script_path.UPLOAD_PATH.$this->dbu->f('logo_image') );
				$this->dbu->query("UPDATE trainer_profile SET logo_image=NULL WHERE trainer_id='".$_SESSION[U_ID]."'");
        	}
        }
		
        $f_title="headed_logo_".$_SESSION[U_ID].$f_ext;
        $f_out=$script_path.UPLOAD_PATH.$f_title;
        
        if(!$_FILES['upload_image']['tmp_name'])
        {
			$ld['error'].="Please upload a file!"."<br>";
			return false;
        }        
        if(!$is_live || (strtolower($f_ext) =='.gif'))
        {
        	if(FALSE === move_uploaded_file($_FILES['upload_image']['tmp_name'],$f_out))
	        {
				// $ld['error'].="Unable to upload the file.  Move operation failed."."<!-- Check file permissions -->";
				return false;
	        }
	        
        	$this->dbu->query("UPDATE trainer_profile SET
	                           logo_image='".$f_title."'
	                           WHERE trainer_id='".$_SESSION[U_ID]."'" 
	                          );
			@chmod($f_out, 0777);
        	$ld['error'].="Image Succesfully saved.<br>";
        	return true;
        }
        else
        {
        	
//        	$this->resize($_FILES['upload_image']['tmp_name'], 276, 0, $f_title);
        	$this->resize($_FILES['upload_image']['tmp_name'], 200, 0, $f_title, $f_ext);
	        @chmod($f_out, 0777);
        	$this->dbu->query("UPDATE trainer_profile SET
	                           logo_image='".$f_title."'
	                           WHERE trainer_id='".$_SESSION[U_ID]."'" 
	                          );
	        $ld['error'].="Image Succesfully saved.".'<br>';
	        return true;
        }
	}

	function upload_custom_file(&$ld)
	{
        global $_FILES, $script_path, $is_live;
        $allowed['.gif']=1;
        $allowed['.jpg']=1;
        $allowed['.jpeg']=1;
        $allowed['.png']=1;
        $f_ext=substr($_FILES['upload_image']['name'],strrpos($_FILES['upload_image']['name'],"."));

        if(!$allowed[strtolower($f_ext)])
        {
            $ld['error']=get_template_tag($ld['pag'], $ld['lang'], 'T.ONLY');
            return false;
        }
        
        if(!is_numeric($_SESSION[U_ID]))
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.ERROR').'<br>';
            return false;
        }
        else 
        {
            $this->dbu->query("SELECT trainer_id, logo_image FROM trainer_header_paper WHERE trainer_id='".$_SESSION[U_ID]."'");

            if(!$this->dbu->move_next())
            {
                $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.ERROR').'<br>';
                return false;
            }
            else 
            {
                @unlink( $script_path.UPLOAD_PATH.$this->dbu->f('logo_image') );
                $this->dbu->query("UPDATE trainer_header_paper SET logo_image=NULL WHERE trainer_id='".$_SESSION[U_ID]."'");
            }
        }

        $f_title="headed_logo_".$_SESSION[U_ID].$f_ext;
		$f_orig="headed_logo_".$_SESSION[U_ID].'_orig'.$f_ext;
        $f_out=$script_path.UPLOAD_PATH.$f_title;
        
        if(!$_FILES['upload_image']['tmp_name'])
        {
			$ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.UPLOAD').'<br>';
			return false;
        }        
        if(!$is_live || (strtolower($f_ext) =='.gif'))
        {
            if(FALSE === move_uploaded_file($_FILES['upload_image']['tmp_name'],$f_out))
            {
				// $ld['error'].="Unable to upload the file.  Move operation failed."."<!-- Check file permissions -->";
				return false;
            }
            
            $this->dbu->query("UPDATE trainer_header_paper SET
                               logo_image='".$f_title."'
                               WHERE trainer_id='".$_SESSION[U_ID]."'" 
                              );
            @chmod($f_out, 0777);
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS_IMAGE').'<br>';
            return true;
        }
        else
        {
			$img_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$f_title;
			$orig_path = dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.$f_orig;
			//save original file
			move_uploaded_file($_FILES['upload_image']['tmp_name'], $orig_path);
			
			copy($orig_path, $img_path);
			
            $max_width = (intval($ld['width']) <= 400 && $ld['width']) ? $ld['width'] : 400;
			$max_height = (intval($ld['height']) <= 220 && $ld['height']) ? $ld['height'] : 220;

			$img_ext = pathinfo($img_path, PATHINFO_EXTENSION);		

			if($ld['width'] && $ld['height'])
			{
 				$ld['width'] = $ld['width'] >= $max_width  ? $max_width : $ld['width'];
				$ld['height'] = $ld['height'] >= $max_height  ? $max_height : $ld['height'];
				
				$this->resize($img_path, $ld['width'], $ld['height'], $f_title, $img_ext, 100);
			}
			else
			{
				$cur_image = $this->createImgFromFile($img_path);
				$cur_image_x = imagesx($cur_image);
				$cur_image_y = imagesy($cur_image);
				unset($cur_image);
				
				$resized = false;
				
				if($cur_image_y>$max_height)
				{
					$resized = true;
				    $this->resize($img_path, 0, $max_height, $f_title, $img_ext, 100);
				}
				
				if($cur_image_x>$max_width)
				{
					$resized = true;
					$this->resize($img_path, $max_width, 0, $f_title, $img_ext);
				}
				
				if(!$resized)
					$this->resize($img_path, $cur_image_x, $cur_image_y, $f_title, $img_ext);
			}
			
            @chmod($f_out, 0777);
            $this->dbu->query("UPDATE trainer_header_paper SET
                               logo_image='".$f_title."'
                               WHERE trainer_id='".$_SESSION[U_ID]."'" 
                              );
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.SUCCESS_IMAGE').'<br>';
            return true;
        }
	}
	
	function createImgFromFile($img_path)
	{
		$img_ext = pathinfo($img_path, PATHINFO_EXTENSION);
		
		if(in_array(strtolower($img_ext), array('jpg', 'jpeg')))
			$img_created = ImageCreateFromJPEG($img_path);
		elseif(strtolower($img_ext) == 'png')
			$img_created = imagecreatefrompng($img_path);
		elseif(strtolower($img_ext) == 'gif')
			$img_created = imagecreatefromgif($img_path);
		
		return $img_created;
	}
	
	function createImgFromRes($img_res, $img_path, $img_ext, $quality=80)
	{
		imagepng($img_res, $img_path) or die("Problem In saving");
		//if(in_array(strtolower($img_ext), array('jpg', 'jpeg')))
		//	ImageJPEG($img_res, $img_path, $quality) or die("Problem In saving");
		//elseif(strtolower($img_ext) == 'png')
		//	imagepng($img_res, $img_path) or die("Problem In saving");
		//elseif(strtolower($img_ext) == 'gif')
		//	imagegif($img_res, $img_path) or die("Problem In saving");
	}
	
	function check_image_size($img_path)
	{
		$img_size = getimagesize($img_path);
		if($img_size[0] * $img_size[0] > MAX_IMG_RESOLUTION)
			return false;

		return true;
	}

	/****************************************************************
	* function erasepicture(&$ld)                                   *
	****************************************************************/
	function erasepicture(&$ld)
	{
			$this->dbu->query("SELECT logo_image FROM trainer_profile WHERE trainer_id='".$_SESSION[U_ID]."'");
			if(!$this->dbu->move_next())
			{
				$ld['error'].="Invalid ID.<br>";
				return false;
			}
			else 
			{
				global $script_path;
				@unlink( $script_path.UPLOAD_PATH.$this->dbu->f('logo_image'));
				$this->dbu->query("UPDATE trainer_profile SET logo_image=NULL WHERE trainer_id='".$_SESSION[U_ID]."'");
			}
		$ld['error'] .= "Image Succesfully deleted!<br>";
		return true;
	}
	
	function erasecustompicture(&$ld)
	{
        $this->dbu->query("SELECT logo_image FROM trainer_header_paper WHERE trainer_id='".$_SESSION[U_ID]."'");
        if(!$this->dbu->move_next())
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.INVALID_ID')."<br>";
            return false;
        }
        else 
        {
            global $script_path;
			
			$file_info = pathinfo($this->dbu->f('logo_image'));
			@unlink( $script_path.UPLOAD_PATH.$file_info['filename'].'_orig.'.$file_info['extension']);
            @unlink( $script_path.UPLOAD_PATH.$this->dbu->f('logo_image'));
            $this->dbu->query("UPDATE trainer_header_paper SET logo_image=NULL WHERE trainer_id='".$_SESSION[U_ID]."'");
        }
		$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.IMAGE_DELETED')."<br>";
		return true;
	}
	/****************************************************************
	* function resize(&$ld)                                         *
	****************************************************************/

	function resize($original_image, $new_width, $new_height, $image_title, $img_ext, $quality = 80) 
	{
		global $script_path;
		
		$original_image = $this->createImgFromFile($original_image);
		$original_image_x = imagesx($original_image);
		$original_image_y = imagesy($original_image);
		$aspect_ratio = $original_image_x / $original_image_y;

		if (empty($new_width)) 
			$new_width = $aspect_ratio * $new_height; 
		elseif (empty($new_height)) 
			$new_height= $new_width / $aspect_ratio; 

		if (imageistruecolor($original_image))	
			$image = imagecreatetruecolor($new_width, $new_height); 
		else 
			$image = imagecreate($new_width, $new_height);
		
		$bg_color = imagecolorallocate($image, 255, 255, 255);
		imagefill($image, 0, 0, $bg_color);
		
		// copy the original image onto the smaller blank 
		imagecopyresampled($image, $original_image, 0, 0, 0, 0, $new_width, $new_height, $original_image_x, $original_image_y);
		
		//imagealphablending($image, true);
		$this->createImgFromRes($image, $script_path.UPLOAD_PATH.$image_title, $img_ext, $quality);
	}
	
	function pay(&$ld){
		$_SESSION['userEmail'] = $userEmail = $this->dbu->field("select email from trainer where trainer_id=".$_SESSION[U_ID]);
		
		$this->dbu->query("select * from price_plan_new where price_id='".$ld['price_id']."' ");
		$this->dbu->move_next();
		$_SESSION['price_id'] = $ld['price_id'];
		$currencyCodeType = $this->dbu->f('currency');
		$paymentType = "Sale";

		$_SESSION['pay_type'] = $ld['pay_type'];
		switch($ld['pay_type']){
			case 'monthly':
			{
				$paymentAmount = $_SESSION['Payment_Amount'] = urlencode(round($this->dbu->f('price_value')/12, 2));
				$description = $_SESSION['description'] = 'Monthly payment ('.$paymentAmount.' '.$currencyCodeType.')';
				$is_recurring = $_SESSION['is_recurring'] = true; break;
			}
			case 'per_year':
			{
				$paymentAmount = $_SESSION['Payment_Amount'] = urlencode($this->dbu->f('price_value'));
				$description = $_SESSION['description'] = 'Pay per year';
				$is_recurring = $_SESSION['is_recurring'] = false; break;
			}
			case 'yearly':
			{
				$paymentAmount = $_SESSION['Payment_Amount'] = urlencode($this->dbu->f('price_value'));
				$description = $_SESSION['description'] = 'Yearly payment ('.$paymentAmount.' '.$currencyCodeType.')';
				$is_recurring = $_SESSION['is_recurring'] = true; break;
			}
			default: header("Location: http://rehabmypatient.com/index.php?pag=profile_payment&paym=0"); exit;
		}

		//include_once('classes/cls_paypal_new.php');
		include_once('classes/cls_paypal_new_recurring.php');
		paypal_init();
		
		//$paymentAmount = $_SESSION['Payment_Amount'] = $is_recurring ? round(urlencode($this->dbu->f('price_value'))/12, 2) : urlencode($this->dbu->f('price_value'));
		
		
		if(SANDBOX)
		{
			$returnURL = urlencode('http://rehab.loc/index.php?act=member-confirm_pay&user_id='.$_SESSION[U_ID]);
			$cancelURL = urlencode('http://rehab.loc/index.php');
		}
		else
		{
			$returnURL = urlencode('http://rehabmypatient.com/index.php?act=member-confirm_pay&user_id='.$_SESSION[U_ID]);
			$cancelURL = urlencode('http://rehabmypatient.com/index.php');
		}
		$NOTIFYURL = 'http://rehabmypatient.com/ipn.php';
        //$custom = 'referralId';
		
		//if($is_recurring)
		//	$description = $_SESSION['description'] = 'Monthly payment ('.$paymentAmount.' '.$currencyCodeType.')';
		//else
		//	$description = $_SESSION['description'] = 'Yearly payment';
		
		$resArray = CallShortcutExpressCheckout ($paymentAmount, $currencyCodeType, $paymentType, $returnURL, $cancelURL, $NOTIFYURL, $description, $userEmail, $is_recurring);
	
		$ack = strtoupper($resArray["ACK"]);
		if($ack=="SUCCESS" || $ack=="SUCCESSWITHWARNING")
			RedirectToPayPal ( $resArray["TOKEN"] );
		else  
			header("Location: /index.php?pag=profile_payment&paym=0");
	}

	function confirm_pay(&$ld)
	{
		global $glob;
		include_once('classes/cls_paypal_new_recurring.php');
		$token = "";
		if (isset($_REQUEST['token']))
			$token = $_REQUEST['token'];
	
		if ( $token != "" )
		{
			$resArray = GetShippingDetails( $token );
	
			$ack = strtoupper($resArray["ACK"]);
			if( $ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") 
			{
				$this->dbu->query("select * from price_plan_new where price_id='".$_SESSION['price_id']."' ");
				$this->dbu->move_next();
				
				$_SESSION['TOKEN'] = $token;
				$_SESSION['PaymentType'] = 'Sale';
				$_SESSION['payer_id'] = $resArray["PAYERID"];
                $NOTIFYURL = 'http://rehabmypatient.com/ipn_s.php';
                $curTime = time();

				if($_SESSION['is_recurring']){
					/* parameter reference: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_CreateRecurringPayments */
					$TOKEN = $_SESSION['TOKEN'];
					$PROFILESTARTDATE = date("c", ($curTime + (1 * 24 * 3600)));
					$DESC = $_SESSION['description'];
					if($_SESSION['pay_type'] == 'monthly')
					{
						$BILLINGPERIOD = 'Month';
						$BILLINGFREQUENCY = 1;
						$TOTALBILLINGCYCLES = '0';
					}
					else
					{
						$BILLINGPERIOD = 'Year';
						$BILLINGFREQUENCY = 1;
						$TOTALBILLINGCYCLES = '0';
					}
					
					$AUTOBILLOUTAMT = 'AddToNextBilling';
					$AMT = $_SESSION['Payment_Amount'];
					$CURRENCYCODE = $_SESSION['currencyCodeType'];
					$EMAIL = $_SESSION['userEmail'];
					$L_PAYMENTREQUEST_0_ITEMCATEGORY0 = 'Physical';
					$L_PAYMENTREQUEST_0_NAME0 = 'License';
					$L_PAYMENTREQUEST_0_AMT0 = $_SESSION['Payment_Amount'];
					$L_PAYMENTREQUEST_0_QTY0 = 1;
					//$INITAMT = $_SESSION['Payment_Amount'];
					//$FAILEDINITAMTACTION = 'CancelOnFailure';
					$MAXFAILEDPAYMENTS = 2;
	
					$resArray = CreateRecurringPaymentsProfile($TOKEN, $PROFILESTARTDATE, $DESC, $BILLINGPERIOD, $BILLINGFREQUENCY, $TOTALBILLINGCYCLES, $AUTOBILLOUTAMT,
															   $AMT, $CURRENCYCODE, $EMAIL, $L_PAYMENTREQUEST_0_ITEMCATEGORY0, $L_PAYMENTREQUEST_0_NAME0, $L_PAYMENTREQUEST_0_AMT0,
															   $L_PAYMENTREQUEST_0_QTY0, /*$INITAMT,$FAILEDINITAMTACTION,*/ $MAXFAILEDPAYMENTS, $NOTIFYURL);

					$resArray = GetRecurringPaymentsProfileDetails($resArray['PROFILEID']);

				}
				else
					$resArray = ConfirmPayment($_SESSION['Payment_Amount'], $NOTIFYURL);
				
				$ack = strtoupper($resArray["ACK"]);
				if( $ack=="SUCCESS" || $ack=="SUCCESSWITHWARNING" )
				{
					$startDate = 0;
					$expireTime = $this->dbu->field("
						SELECT expire_date FROM trainer WHERE trainer_id=".$ld['user_id']."
					");
					
					$expireTime = strtotime($expireTime);
					if(($expireTime - $curTime) < 0)
						$startDate = $curTime;
					else
						$startDate = $expireTime;
					
					$daysToAdd = 0;
					switch($this->dbu->f('licence_period')){
						case '1 year':{}
						default: {$daysToAdd = 365;}
					}
					
					if($_SESSION['pay_type'] == 'yearly' || $_SESSION['pay_type'] == 'monthly')
						$daysToAdd *= 10;
						
					$expireTime = date("Y-m-d H:i:s", ($startDate + ($daysToAdd * 24 * 3600)));
		
					switch($glob['lang']){
						case 'us': $dbCountryCode = 'US'; break;
						default: $dbCountryCode = 'GB';
					}
					$this->dbu->query("SELECT * from `country` WHERE code='".$dbCountryCode."'");
					$this->dbu->move_next();
					$country_id = $this->dbu->f('country_id');
                    
                    $str = "UPDATE trainer 
									SET 
										paypal_profile_id = '".mysql_real_escape_string($_SESSION['payer_id'])."',
										country_id 	    = '".mysql_real_escape_string($country_id)."',
										price_plan_id 	= '".mysql_real_escape_string($_SESSION['price_id'])."',
										is_trial		= '0',
										active			= '2',
										expire_date		= '".mysql_real_escape_string($expireTime)."'
									WHERE 
										trainer_id=".$ld['user_id']." ";

                    $trans_id = $this->dbu->query_get_id("INSERT INTO `paypal_transactions`
                                (`trainer_id`, `name`, `profile_id`, `status`, `type`, `amount`, `currency`, `timestamp`, `ack`, `request`, `correlation_id`, `error`, `answer`)
                         VALUES ('{$ld['user_id']}', 'mysql Update trainer after Confirm pay', '{$_SESSION['payer_id']}', 'error', '', '', '', '".date('c')."', '', '".mysql_real_escape_string($str)."', '',
                                '', '')");
                    $this->dbu->query($str);
                    $this->dbu->query("UPDATE `paypal_transactions` SET `status` = 'ok' WHERE id=$trans_id ");
					
					//add to activity table
					$activity_username = mysql_real_escape_string($this->dbu->field("select username from trainer where trainer_id=".$ld['user_id']));
					$this->dbu->query("insert into activity_list (activity_type, activity_date, modify_date, activity_text, trainer_id, is_showed)
									  values('payed', '".time()."', '".time()."', '$activity_username just bought subscription to RMP', '".$ld['user_id']."', 0);");
                    
                    $message_data=get_sys_message('succ_pay', $this->dbu->f('lang'));
                    
                    if($message_data['text']!=null) 
                    {
                        $this->dbu->query("SELECT trainer.email AS email, trainer.is_clinic  AS clinic, trainer_header_paper.first_name AS clinic_first, trainer_header_paper.surname AS clinic_last, trainer.first_name AS first, trainer.surname AS surname
                                            FROM `trainer` INNER JOIN `trainer_header_paper` USING(trainer_id) WHERE trainer_id=".$_SESSION[U_ID]." ");
                        $this->dbu->move_next();
                        require_once (dirname(dirname(__FILE__)).'/classes/class.phpmailer.php');
                        $name = ($this->dbu->f('clinic')) ? $this->dbu->f('clinic_first').' '.$this->dbu->f('clinic_last') : $this->dbu->f('first').' '.$this->dbu->f('surname');
                        $this->send_mail($this->dbu->f('email'), $name, $message_data);
                    }
					
					//send message notification of payments to admin
					$paid_email = $this->dbu->field("select username from trainer WHERE trainer_id=".$_SESSION[U_ID]."");
                    $this->send_mail('support@rehabmypatient.com', '', array('subject'=>'User paid', 'text'=>"New client: $paid_email has paid.", 'from_email'=>'support@rehabmypatient.com'));
					
					header("Location: /index.php?pag=profile&paym=1");
				}
				else{
					header("Location: /index.php?pag=profile&paym=0");
				}
			}
			else  
			{
				header("Location: /index.php?pag=profile&paym=0");
			}
		}
	}

	/****************************************************************
	* function add_validate(&$ld)                                   *
	****************************************************************/
	function validate_pay(&$ld)
	{
		$is_ok = true;
		if(!$ld['first_name']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_FIRST')."<br>";
		   $is_ok = false;
		}
		if(!$ld['surname']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_SURNAME')."<br>";
		   $is_ok = false;
		}
		if(!$ld['email']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_EMAIL')."<br>";
			$is_ok = false;
		}	
		elseif(!secure_email($ld['email']))
			{
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.INVALID_EMAIL')."<br>";
				
				$is_ok=false;
			}
		if(!$ld['country_id'])
			{
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.SELECT_COUNTRY')."<br>";
			
				$is_ok=false;
			}	
		
		
		if(!$ld['credit_card_type']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.SELECT_CARD_TYPE')."<br>";
		   $is_ok = false;
		}
		if(!$ld['credit_card_no']){
			$ld['error'] .= get_template_tag($ld['pag'], $ld['lang'], 'T.SELECT_CARD_NUM')."<br>";
		   $is_ok = false;
		}	
			
		return $is_ok;
	}

	function cancel_payment(&$ld)
	{
		if(!$ld['pp_del_key']){
			return false;
		}elseif ($ld['pp_del_key']!='123delkey321'){
			return false;
		}
		$this->dbu->query("SELECT * FROM trainer WHERE trainer_id=".$_SESSION[U_ID]." ");
		if(!$this->dbu->move_next()){
			return false;
		}
		else
		{
			include_once('misc/CreateRecurringPaymentsProfile.php');
			$nvpStr = "&PROFILEID=".urlencode($this->dbu->f('paypal_profile_id'))."&ACTION=Cancel";
		
			$httpParsedResponseAr = PPHttpPost('ManageRecurringPaymentsProfileStatus', $nvpStr);
			
			if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
			
			 $this->dbu->query("UPDATE trainer 
								SET 
									paypal_profile_id = '',
									country_id 	    = '0',
									price_plan_id 	= '0'
								WHERE 
									trainer_id=".$_SESSION[U_ID]."
													");
			 
				$ld['error'] =get_template_tag($ld['pag'], $ld['lang'], 'T.CANCEL').'<br />';
			return true;
			} else{
				$ld['error'] .= urldecode($httpParsedResponseAr['L_LONGMESSAGE0']);
				return false;
			}	
		}
	}
	
	function ipn_confirm(&$ld){
        $this->dbu->query("INSERT INTO `paypal_transactions`
                                (`name`)
                         VALUES('Paypal has been here')");
    }

	function delete_profile(&$ld)
	{
		$ld['error']="Not implemented yet.";
		return true;
	}
	
    function exercise_add(&$ld)
	{
        // we'll use some functions from admin lib to avoid code doubling 
        include_once('admin/classes/cls_programs.php');
        $programs = new programs();
		$this->dbu->query("SELECT SUBSTR(programs_code, 4) AS last_code, sort_order FROM programs WHERE programs_code LIKE 'USR%' ORDER BY programs_code DESC ");
		if($this->dbu->move_next())
			$last_code = $this->dbu->f('last_code');
		else
			$last_code = 0;
		
		$last_code++;
		$count_zeros = (3-strlen($last_code) > 0) ? 3-strlen($last_code) : 0;
		$last_code = 'USR'.str_repeat('0', $count_zeros).$last_code;
		
		$ld['pag'] = 'profile_exercise_add';
        if(!$this->validate_exercise_add(&$ld))
        {
            return false;
        }

        if($programs->image_validate()){
			$ld['programs_id']=$this->dbu->query_get_id("INSERT INTO programs SET 
														programs_code='".mysql_real_escape_string($last_code)."', 
														sort_order='".mysql_real_escape_string($this->dbu->f('sort_order')+10)."',
														active = 1,
														owner = ".$_SESSION['m_id']);
			foreach(array('en', 'us') as $lang){
				$this->dbu->query("INSERT INTO programs_translate_".$lang." SET 
																	programs_id='".mysql_real_escape_string($ld['programs_id'])."', 
																	programs_title='".mysql_real_escape_string($ld['name'])."',
																	description = '".mysql_real_escape_string($ld['description'])."' ");
			}
			$this->dbu->query("INSERT INTO programs_in_category ( programs_id, category_id, main )
														values ( '".$ld['programs_id']."', '".$ld['subcategory']."', '1' ) ");
			
			if(isset($_FILES['upload_pdf']) && pathinfo($_FILES['upload_pdf']['name'], PATHINFO_EXTENSION) == 'pdf')
			{
				move_uploaded_file($_FILES['upload_pdf']['tmp_name'], 'pdf/uploaded_pdf/user_program_'.$ld['programs_id'].'.pdf');
				$this->dbu->query("UPDATE programs SET uploaded_pdf='user_program_".$ld['programs_id'].".pdf' WHERE programs_id = '".$ld['programs_id']."' ");
				unset($_FILES['upload_pdf']);
			}
			
            $programs->upload_file($ld);
			$ld['pag'] = 'programs_user';
			$ld['error'] = 'Exercise added.';
			return true;
        }
        else {
			$ld['error'] = 'Invalid picture.';
            return false;
        }
    }
	
    function validate_exercise_add(&$ld)
	{
		$is_ok=true;

		if(!$ld['name'])
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_NAME')."<br>";
            $is_ok=false;
        }
		if(!$ld['description'])
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_DESCR')."<br>";
            $is_ok=false;
        }
		if($ld['category'] == -1)
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_CAT')."<br>";
            $is_ok=false;
        }
		if($ld['subcategory'] == -1)
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_SUBCAT')."<br>";
            $is_ok=false;
        }
		return $is_ok;
	}
	
	function exercise_update(&$ld)
	{
        // we'll use some functions from admin lib to avoid code doubling 
        include_once('admin/classes/cls_programs.php');
        $programs = new programs();
		
        if(!$this->validate_exercise_update(&$ld))
        {
            $ld['pag'] = 'profile_exercise_update';
            return false;
        }

		if(isset($_FILES['upload_pdf']) && pathinfo($_FILES['upload_pdf']['name'], PATHINFO_EXTENSION) == 'pdf')
		{
			move_uploaded_file($_FILES['upload_pdf']['tmp_name'], 'pdf/uploaded_pdf/user_program_'.$ld['programs_id'].'.pdf');
			$this->dbu->query("UPDATE programs SET uploaded_pdf='user_program_".$ld['programs_id'].".pdf' WHERE programs_id = '".$ld['programs_id']."' ");
			unset($_FILES['upload_pdf']);
		}
		
        foreach(array('en', 'us') as $lang){
            $this->dbu->query("UPDATE programs_translate_".$lang." SET 
								   programs_title='".mysql_real_escape_string($ld['name'])."',
								   description = '".mysql_real_escape_string($ld['description'])."'
								WHERE programs_id='".$ld['programs_id']."'
							");
        }
		
		$this->dbu->query("DELETE FROM programs_in_category WHERE programs_id = '".$ld['programs_id']."' ");
		$this->dbu->query("INSERT INTO programs_in_category ( programs_id, category_id, main )
                									values ( '".$ld['programs_id']."', '".$ld['subcategory']."', '1' ) ");
		
		
        
        if($programs->image_validate($ld)){
            $programs->upload_file($ld);
			$ld['error'] = 'Exercise updated.';
			return true;
        }
        else {
			$ld['error'] = 'Invalid image.';
            return false;
        }
		
		return true;
    }
	
	function validate_exercise_update(&$ld)
	{
		$is_ok=true;

		if(!$ld['name'])
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_NAME')."<br>";
            $is_ok=false;
        }
		if(!$ld['description'])
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_DESCR')."<br>";
            $is_ok=false;
        }
		if($ld['category'] == -1)
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_CAT')."<br>";
            $is_ok=false;
        }
		if($ld['subcategory'] == -1)
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.FILL_SUBCAT')."<br>";
            $is_ok=false;
        }       
		return $is_ok;
	}
	
	function exercise_delete(&$ld)
	{
		if(!$this->validate_exercise_delete(&$ld))
        {
            $ld['pag'] = 'programs_user';
            return false;
        }
		include_once('admin/classes/cls_programs.php');
        $programs = new programs();
		
		$programs->erasepicture($ld);
		
		$this->dbu->query("DELETE FROM programs WHERE programs_id='".$ld['programs_id']."'");
        foreach(array('en', 'us') as $lang)
			$this->dbu->query("DELETE FROM programs_translate_".$lang." WHERE programs_id='".$ld['programs_id']."'");

		$this->dbu->query("DELETE FROM programs_in_category WHERE programs_id='".$ld['programs_id']."'");
		
		//delete program id
		$query_string = "SELECT * FROM exercise_plan WHERE (exercise_program_id LIKE '%,".$ld['programs_id']."%' OR exercise_program_id LIKE '%,".$ld['programs_id'].",%' OR exercise_program_id LIKE '%".$ld['programs_id']."%' OR exercise_program_id LIKE '%".$ld['programs_id'].",%') AND trainer_id = ".$_SESSION[U_ID];
		$this->dbu->query($query_string);
		while($this->dbu->move_next())
		{
			$replace_str = $this->dbu->f('exercise_program_id');
			$replace_str = str_replace(','.$ld['programs_id'].',', ',', $replace_str);
			$replace_str = str_replace(array($ld['programs_id'].',', ','.$ld['programs_id'], $ld['programs_id']), '', $replace_str);
			
			$query_string = "UPDATE exercise_plan SET exercise_program_id='$replace_str' WHERE exercise_plan_id=".$this->dbu->f('exercise_plan_id')." AND trainer_id = ".$_SESSION[U_ID];
			$this->dbu->query($query_string);
		}
		
		$query_string = "SELECT * FROM exercise_program_plan WHERE (exercise_program_id LIKE '%,".$ld['programs_id']."%' OR exercise_program_id LIKE '%,".$ld['programs_id'].",%' OR exercise_program_id LIKE '%".$ld['programs_id']."%' OR exercise_program_id LIKE '%".$ld['programs_id'].",%') AND trainer_id = ".$_SESSION[U_ID];
		$this->dbu->query($query_string);
		while($this->dbu->move_next())
		{
			$replace_str = $this->dbu->f('exercise_program_id');
			$replace_str = str_replace(','.$ld['programs_id'].',', ',', $replace_str);
			$replace_str = str_replace(array($ld['programs_id'].',', ','.$ld['programs_id'], $ld['programs_id']), '', $replace_str);
			
			$query_string = "UPDATE exercise_program_plan SET exercise_program_id='$replace_str' WHERE exercise_program_plan_id=".$this->dbu->f('exercise_program_plan_id')." AND trainer_id = ".$_SESSION[U_ID];
			$this->dbu->query($query_string);
		}
		
		$query_string = "DELETE FROM exercise_plan_set WHERE exercise_program_id = '".$ld['programs_id']."' AND trainer_id = ".$_SESSION[U_ID];
		$this->dbu->query($query_string);
		
		$ld['error'] = 'Exercise succesfully deleted.';
		return true;
	}
	
	function validate_exercise_delete(&$ld)
	{
		$is_ok=true;

		if(!$ld['programs_id'] || !$ld['programs_id'])
        {
            $ld['error'].=get_template_tag($ld['pag'], $ld['lang'], 'T.EMPTY_ID')."<br>";
            $is_ok=false;
        }
		return $is_ok;
	}
    function delete_image(&$ld){
        if($ld['programs_id']){
			$this->dbu->query("select * from `programs` where programs_id='".$ld['programs_id']."'");
			if($this->dbu->move_next())
			{
				@unlink(dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.'/'.$this->dbu->f('image'));
				@unlink(dirname(dirname(__FILE__)).'/'.$script_path.UPLOAD_PATH.'/'.$this->dbu->f('thumb_image'));
			}
            $this->dbu->query("UPDATE `programs` SET lineart = NULL, thumb_lineart = '', image = NULL, thumb_image = '' WHERE programs_id=".$ld['programs_id']);
            return true;
        }
        else
            return false;
    }
    function send_mail($send_to_email,$send_to_name,$message_data)
    {
        $ordermail = $send_to_email;
        $fromMail = $message_data['from_email']; 
        $replyMail = $message_data['from_email'];

		$body=$message_data['text'];
		$body=str_replace('[!NAME!]',$send_to_name, $body );
		$body = nl2br($body);

/*
		mail($ordermail,$message_data['subject'],$body);
		print_r($ordermail);
*/
                
        $mail = new PHPMailer();
		$mail->Mailer = 'sendmail';
		$mail->IsHTML(true);
        //$body             = file_get_contents('contents.html');
        //$body             = eregi_replace("[\]",'',$body);
        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->SMTPDebug = 1; // enables SMTP debug information (for testing)
        // 1 = errors and messages
        // 2 = messages only
        $mail->SMTPAuth = true; // enable SMTP authentication
        $mail->Host = SMTP_HOST; // sets the SMTP server
        $mail->Port = SMTP_PORT; // set the SMTP port for the GMAIL server
        $mail->Username = SMTP_USERNAME; // SMTP account username
        $mail->Password = SMTP_PASSWORD; // SMTP account password

        $mail->SetFrom($fromMail, $fromMail);
        $mail->AddReplyTo($replyMail, $replyMail);

		$subject = $message_data['subject'];
		$mail->Subject = $subject;
        //$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
        //$mail->MsgHTML($body);
		$mail->Body = $body;

        $mail->AddAddress($ordermail, $send_to_name);
        $mail->Send();	
    }
	
	function add_test_client(&$ld)
	{
		$ld['client_id']=$this->dbu->query_get_id("
								INSERT INTO 
											client 
								SET 
											first_name='".mysql_escape_string($ld['first_name'])."', 
											surname='".mysql_escape_string($ld['surname'])."',
											appeal='".mysql_escape_string($ld['appeal'])."',
											email='".mysql_escape_string($ld['email'])."', 
											print_image_type='".mysql_escape_string($ld['print_image_type'])."', 
											client_note='".mysql_escape_string($ld['client_note'])."', 
											create_date=NOW(),
											modify_date=NOW(),
											trainer_id = ".$ld['trainer_id']." 
								");
			
		if($ld['client_id'])
		{
			//log to history
			$this->dbu->query("insert into client_history (trainer_id, client_id, date, action, client_name)
								values('".$ld['trainer_id']."', '".$ld['client_id']."', '".time()."', 'Added.', '".mysql_escape_string($ld['first_name'])." ".mysql_escape_string($ld['surname'])."')");
		}
		
		return true;
	}
	
	function add_test_exercise(&$ld)
	{
		$trainer_id = $ld['trainer_id'];
		
		$ld['exercise_plan_id']=$this->dbu->query_get_id("
			INSERT INTO 
				exercise_plan 
			SET 
				exercise_program_id='".$ld['exercise_id']."', 
				date_created=NOW(), 
				date_modified=NOW(), 
				trainer_id='".$trainer_id."', 
				client_id='".$ld['client_id']."',
				exercise_desc = '".$ld['exercise_desc']."'
		");
		
		if($ld['exercise_plan_id'])
		{
			//log to history
			$desc = mysql_real_escape_string($this->dbu->field("select exercise_desc from exercise_plan where exercise_plan_id=".$ld['exercise_plan_id']));
			$client_name = mysql_real_escape_string($this->dbu->field("select concat(first_name, ' ', surname) from  client where client_id='".$ld['client_id']."'"));
			$this->dbu->query("insert into client_history (trainer_id, client_id, date, action, client_name)
								values('".$trainer_id."', '".$ld['client_id']."', '".time()."', 'Exercise $desc created.', '$client_name')");
		}
	}
	
	function update_test_exercise_plan(&$ld)
	{
		$trainer_id = $ld['trainer_id'];
		
		$exercise = explode(',',$ld['exercise_id']);
		
		$i=0;
		while ($i<count($exercise)) 
		{
			$this->dbu->query("SELECT * FROM exercise_plan_set WHERE 
					 exercise_plan_id = '".$ld['exercise_plan_id']."' AND
					 exercise_program_id = '".$exercise[$i]."' AND
					 trainer_id = '".$trainer_id."' AND
					 client_id = '".$ld['client_id']."' AND
					 is_program_plan = 0
					 ");
			
			if($this->dbu->move_next())
			{
				$this->dbu->query("UPDATE exercise_plan_set 
					SET 
						plan_description = '".mysql_escape_string($ld['description'.$exercise[$i]])."',
						plan_set_no = '".mysql_escape_string($ld['sets'.$exercise[$i]])."',
						plan_repetitions = '".mysql_escape_string($ld['repetitions'.$exercise[$i]])."',
						plan_time = '".mysql_escape_string($ld['time'.$exercise[$i]])."',
						both_sides = '".(isset($ld['both_sides'.$exercise[$i]]) ? 1 : 0)."'
					WHERE
						exercise_plan_id = '".$ld['exercise_plan_id']."' AND
						exercise_program_id = '".$exercise[$i]."' AND
						trainer_id = '".$trainer_id."' AND
						client_id = '".$ld['client_id']."' 
					");
			}
			else
			{		
				$this->dbu->query("
					 INSERT INTO
						 exercise_plan_set 
					 SET
						 exercise_plan_id = '".$ld['exercise_plan_id']."',
						 exercise_program_id = '".$exercise[$i]."',
						 plan_description = '".mysql_escape_string($ld['description'.$exercise[$i]])."',
						 plan_set_no = '".mysql_escape_string($ld['sets'.$exercise[$i]])."',
						 plan_repetitions = '".mysql_escape_string($ld['repetitions'.$exercise[$i]])."',
						 plan_time = '".mysql_escape_string($ld['time'.$exercise[$i]])."',
						 trainer_id = '".$trainer_id."',
						 client_id = '".$ld['client_id']."',
						 is_program_plan = 0,
						 both_sides = '".(isset($ld['both_sides'.$exercise[$i]]) ? 1 : 0)."'
					");			
			}
			$i++;			
		}
		
		$this->dbu->query("UPDATE exercise_plan 
			SET 
				exercise_notes = '".mysql_escape_string($ld['exercise_notes'])."'
			WHERE
				exercise_plan_id = '".$ld['exercise_plan_id']."' AND
				trainer_id = '".$trainer_id."' AND
				client_id = '".$ld['client_id']."' 
			");
		
		$this->dbu->query("UPDATE client SET modify_date=NOW() WHERE client_id=".$ld['client_id']." AND
											trainer_id = ".$trainer_id." ");
		return true;	
	}
	
	function add_test_program_plan(&$ld)
	{
		$trainer_id = $ld['trainer_id'];
		
		global $user_level;
	
		$this->dbu->query("
							SELECT 
								exercise_program_plan_id
							FROM 
								exercise_program_plan 
							WHERE 
								1=1 
								AND trainer_id = ".$trainer_id." 
								AND program_name = '".mysql_real_escape_string($ld['program_name'])."'
								AND print_image_type = '".mysql_real_escape_string($ld['print_image_type'])."'
						");
		

		/* CHECK IF Programme EXIST IN DB, IF NOT, SAVE IT IN DB */
		if($this->dbu->move_next())
		{
			$ld['error'] = get_template_tag('dashboard', $ld['lang'], 'T.PROG_EXIST');
			$ld['pag'] = 'programs';
			return false;
		}
		else 
		{

			$ld['program_id']=$this->dbu->query_get_id("
							INSERT INTO 
										exercise_program_plan 
							SET 
										program_name='".mysql_escape_string($ld['program_name'])."', 
										print_image_type='".mysql_real_escape_string($ld['print_image_type'])."', 
										client_note='".mysql_real_escape_string($ld['exercise_note'])."', 
										date_created=NOW(),
										date_modified=NOW(),
										exercise_program_id='".$ld['exercise_id']."',
										trainer_id = ".$trainer_id." 
							");

			$ld['program_name']=''; 
			$ld['print_image_type']='';
			$ld['exercise_note']='';
		
			$ld['error']= get_template_tag('dashboard', $ld['lang'], 'T.PROG_SUCCESS');
		
			return true;
		}
	}
	
	function update_test_program_exercise_plan(&$ld)
	{
		$trainer_id = $ld['trainer_id'];
		
		$is_custom = $this->dbu->field("select parent_plan from exercise_program_plan where trainer_id=".$trainer_id." and exercise_program_plan_id=".$ld['program_id']) ? true : false;
		
		//update secondary
		$this->dbu->query("select exercise_program_plan_id, client_id from exercise_program_plan where trainer_id=".$trainer_id." and parent_plan=".$ld['program_id']);
		while($this->dbu->move_next())
		{
			$secondary_program[] = array('client_id'=> $this->dbu->f('client_id'), 'program_id'=>$this->dbu->f('exercise_program_plan_id'));
		}
		if(count($secondary_program) && !$is_custom)
		{
			$this->dbu->query("select * from exercise_program_plan where exercise_program_plan_id=".$ld['program_id']);
			$this->dbu->move_next();
			$program_name = $this->dbu->f('program_name');
			$exercise_program_id = $this->dbu->f('exercise_program_id');
			$exercise_notes = $this->dbu->f('exercise_notes');
			$print_image_type = $this->dbu->f('print_image_type');
			$client_note = $this->dbu->f('client_note');
			
			for($i=0; $i<count($secondary_program);$i++)
			{
				$this->dbu->query("update exercise_program_plan
									set program_name='".$program_name."',
										exercise_program_id='".$exercise_program_id."',
										exercise_notes='".$exercise_notes."',
										print_image_type='".$print_image_type."',
										client_note='".$client_note."'
								  where exercise_program_plan_id=".$secondary_program[$i]['program_id']);
				
				$this->dbu->query("delete from exercise_plan_set where exercise_plan_id=".$secondary_program[$i]['program_id']);
				$this->dbu->query("delete from programs_custom_descr where program_id=".$secondary_program[$i]['program_id']);
			}
		}
		
		$exercise = explode(',',$ld['exercise_id']);
		$i=0;
		while ($i<count($exercise)) 
		{
			
			$this->dbu->query('SELECT * FROM `programs_custom_descr` WHERE program_id = '.$ld['program_id'].' AND exercise_id ='.$exercise[$i]);
            $this->dbu->move_next();
            if($this->dbu->f('exercise_id')){
                $this->dbu->query('UPDATE `programs_custom_descr`
                                    SET description = "'.mysql_real_escape_string($ld['description'.$exercise[$i]]).'"
                                    WHERE exercise_id = "'.$exercise[$i].'" AND program_id = "'.$ld['program_id'].'"');
            }
            else{
                $this->dbu->query('INSERT INTO `programs_custom_descr` (exercise_id, program_id, description)
                                    VALUES ('.$exercise[$i].', '.$ld['program_id'].', "'.mysql_real_escape_string($ld['description'.$exercise[$i]]).'");');
            }
			
			if(count($secondary_program) && !$is_custom)
			{
				for($j=0; $j<count($secondary_program);$j++)
				{
					$this->dbu->query('INSERT INTO `programs_custom_descr` (exercise_id, program_id, description)
                                    VALUES ('.$exercise[$i].', '.$secondary_program[$j]['program_id'].', "'.mysql_real_escape_string($ld['description'.$exercise[$i]]).'");');
				}
			}
			
			$this->dbu->query("SELECT * FROM exercise_plan_set WHERE 
								exercise_plan_id = '".$ld['program_id']."' AND
								exercise_program_id = '".$exercise[$i]."' AND
								trainer_id = '".$trainer_id."' AND
								client_id = '".$ld['program_id']."' AND
								is_program_plan = 1
								");
			
			if($this->dbu->move_next())
			{
				$this->dbu->query("UPDATE exercise_plan_set 
					SET 
						plan_description = '".mysql_escape_string($ld['description'.$exercise[$i]])."',
						plan_set_no = '".mysql_escape_string($ld['sets'.$exercise[$i]])."',
						plan_repetitions = '".mysql_escape_string($ld['repetitions'.$exercise[$i]])."',
						plan_time = '".mysql_escape_string($ld['time'.$exercise[$i]])."',
						both_sides = '".(isset($ld['both_sides'.$exercise[$i]]) ? 1 : 0)."'
					WHERE
						exercise_plan_id = '".$ld['program_id']."' AND
						exercise_program_id = '".$exercise[$i]."' AND
						trainer_id = '".$trainer_id."' AND
						client_id = '".$ld['program_id']."' 
					");
			}
			else
			{		
				$this->dbu->query("
					 INSERT INTO
						exercise_plan_set 
					 SET
						exercise_plan_id = '".$ld['program_id']."',
						exercise_program_id = '".$exercise[$i]."',
						plan_description = '".mysql_escape_string($ld['description'.$exercise[$i]])."',
						plan_set_no = '".mysql_escape_string($ld['sets'.$exercise[$i]])."',
						plan_repetitions = '".mysql_escape_string($ld['repetitions'.$exercise[$i]])."',
						plan_time = '".mysql_escape_string($ld['time'.$exercise[$i]])."',
						trainer_id = '".$trainer_id."',
						client_id = '".$ld['program_id']."',
						is_program_plan = 1,
						both_sides = '".(isset($ld['both_sides'.$exercise[$i]]) ? 1 : 0)."'
					");			
			}
			
			//update secondary
			if(count($secondary_program) && !$is_custom)
			{
				for($j=0; $j<count($secondary_program);$j++)
				{
					$this->dbu->query("
						 INSERT INTO
							exercise_plan_set 
						 SET
							exercise_plan_id = '".$secondary_program[$j]['program_id']."',
							exercise_program_id = '".$exercise[$i]."',
							plan_description = '".mysql_escape_string($ld['description'.$exercise[$i]])."',
							plan_set_no = '".mysql_escape_string($ld['sets'.$exercise[$i]])."',
							plan_repetitions = '".mysql_escape_string($ld['repetitions'.$exercise[$i]])."',
							plan_time = '".mysql_escape_string($ld['time'.$exercise[$i]])."',
							trainer_id = '".$trainer_id."',
							client_id = '".$secondary_program[$j]['program_id']."',
							is_program_plan = 1,
							both_sides = '".(isset($ld['both_sides'.$exercise[$i]]) ? 1 : 0)."'
						");
				}
			}
			
			$i++;
		}

		$this->dbu->query("UPDATE exercise_program_plan 
							SET 
								exercise_notes = '".mysql_real_escape_string($ld['exercise_notes'])."',
								date_modified = NOW()
							WHERE
								trainer_id = '".$trainer_id."' AND
								exercise_program_plan_id = '".$ld['program_id']."' 
							");
		
		return true;	
	}
	
	function add_test_data_to_new_user(&$ld)
	{
		//add test client
		$ld['first_name'] = 'Test'; 
		$ld['surname'] = 'Patient';
		$ld['email'] = ''; 
		$ld['print_image_type'] = '1';
		$ld['client_note'] = '';
		
		$this->add_test_client($ld);
		
		$ld['exercise_id'] = '294,328,315';
        $ld['exercise_desc'] = 'Tennis Elbow exercsies';
		$this->add_test_exercise($ld);
		
		$ld['sets294'] = '3';
		$ld['sets328'] = '3';
		$ld['sets315'] = '5';
		$ld['repetitions294'] = '3';
		$ld['repetitions328'] = '10';
		$ld['repetitions315'] = '';
		$ld['time294'] = '30';
		$ld['time328'] = '';
		$ld['time315'] = '5 mins';
		
		//get description for exercise
		$exercise_ids = explode(',', $ld['exercise_id']);
		foreach($exercise_ids as $exercise_id)
		{
			$ld['description'.$exercise_id] = $this->get_exercise_desc($ld, $exercise_id);
		}
		$this->update_test_exercise_plan($ld);
		
		$ld['exercise_id'] = '448,460,392,493';
        $ld['exercise_desc'] = '';
		$ld['program_name'] = 'Acute Lower Back Pain';
		$ld['print_image_type'] = '1';
		$ld['program_desc'] = '1-2 weeks duration';
		$this->add_test_program_plan($ld);
		
		$ld['sets448'] = '3';
		$ld['sets460'] = '3';
		$ld['sets392'] = '';
		$ld['sets493'] = '3';
		$ld['repetitions448'] = '10';
		$ld['repetitions460'] = '10';
		$ld['repetitions392'] = '';
		$ld['repetitions493'] = '';
		$ld['time448'] = '';
		$ld['time460'] = '';
		$ld['time392'] = '';
		$ld['time493'] = '5 mins';
		$ld['both_sides448'] = '1';
		$ld['both_sides460'] = '1';
		
		$ld['exercise_notes'] = "Stop any exercise that causes pain.
If you have any questions with an exercise, just email the clinic.";
		
		//get description for exercise
		$exercise_ids = explode(',', $ld['exercise_id']);
		foreach($exercise_ids as $exercise_id)
		{
			$ld['description'.$exercise_id] = $this->get_exercise_desc($ld, $exercise_id);
		}
		$this->update_test_program_exercise_plan($ld);
		
	}
	
	function get_exercise_desc($ld, $exercise_id)
	{
		$lang = isset($ld['lang']) ? $ld['lang'] : 'en';
		$query = "select description from  programs_translate_$lang where programs_id=$exercise_id";
		$result = mysql_result(mysql_query($query), 0) ? mysql_result(mysql_query($query), 0) : '';
		return $result;
	}
	
	function track_memory_usage()
	{
		print_r(memory_get_usage(true));
		print_r("<br>");
	}
}//end class