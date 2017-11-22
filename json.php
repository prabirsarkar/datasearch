<?php
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_input_time', 300);
ini_set('max_execution_time', 300);

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Json extends MX_Controller {

    var $data;

    public function __construct() {
        parent::__construct();
        $this->load->model(array('json/login_model', 'json/user_model', 'json/broadcast_model', 'json/profile_model', 'user/profile_model', 'json/usertask_model', 'json/notification_model',
        'json/attendance_model', 'json/chat_model', 'json/report_model', 'json/news_model', 'json/localprediction_model', 'json/issue_model', 'json/dashboard_model', 'json/crime_category_model',
        'json/taskallocation_model', 'globalnotification_model', 'common_model'));
    }

    public function renderJson($arr = array()) {
        header('Content-Type: application/json');
        echo json_encode($arr);
        die();
    }

    /**
     * Used to check if it is a valid URL or not
     * @param string $signature
     * @return boolean true if valid and false if invalid
     */
    public function signature($signature) {
        switch ($signature) {
            case APP_SIGN_D: case APP_SIGN_R: case APP_SIGN_R_DEBUG1: case APP_SIGN_R_DEBUG2: case APP_SIGN_R_DEBUG3: case APP_SIGN_R_DEBUG4:
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    public function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0)
            return true;
        return (substr($haystack, -$length) === $needle);
    }

    public function startWith($string, $char) {
        if (substr($string, 0, 1) === $char)
            return true;
        else
            return false;
    }

    public function endsWithCustom($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0)
            return $needle;
        return "";
    }

    public function getaccesssecretkey() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $access_secret_key = array('s3_access_key' => urlencode(S3_ACCESS_KEY),
                                        's3_access_secret' => urlencode(S3_ACCESS_SECRET),
                                        's3_bucket_name' => urlencode(S3_BUCKET_NAME),
                                        's3_bucket_region' => urlencode(S3_BUCKET_REGION)
                                        );
            $this->renderJson(array('status' => 'success', 'access_secret_key' => $access_secret_key));
        } catch(Exception $e) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $e->getMessage()));
            die();
        }
    }

    public function fileUpload($bEcho = true) {
        try {
            $config = array(
                    'upload_path' => "./uploads/thumb/",
                    'file_name' => $_FILES['image']['name'],
                    'allowed_types' => "gif|jpg|png|jpeg",
            );

            $this->load->library('upload', $config);

            if ($this->upload->do_upload("image")) {
                $image = $this->upload->data();
                    echo basename($_FILES['image']['name']);
                } else {
                echo $this->upload->display_errors();
                }
        } catch (Exception $e) {
            if ($bEcho) {
                die('File did not upload: ' . $e->getMessage());
            } else {
                return false;
            }
        }
    }

    /**
     * Login page
     */
    
    public function login() {

        $arr = array();
        $rval = array();
        $arr_device=array();

        if ($this->signature($this->input->get("signature"))) {

            $arr['user_name'] = $this->input->get("username");
            $arr['password'] = base64_decode(urldecode($this->input->get("password")));
            $arr['ip'] = $this->input->ip_address();
            
            $authentic_user = $this->login_model->user_authentication($arr);

            $id=$authentic_user['emp_id'];
             
            //Previously Active
            if (empty($authentic_user)) {
                $rval['status'] = 'authentication error';
            } else {
                if (isset($authentic_user['status'])) {
                    $rval['status'] = 'inactive';
                } else {
                    $rval['status'] = 'success';
                    $rval['login_details']['id'] = $authentic_user['emp_id'];
                    $rval['login_details']['lastloginlogId'] = $authentic_user['last_loginlog_id'];
                    $rval['login_details']['app_version'] = APP_VERSION;
                    $rval['login_details']['s3_bucket_name'] = urlencode(S3_BUCKET_NAME);
                    $rval['login_details']['s3_bucket_region'] = urlencode(S3_BUCKET_REGION);
                    $rval['login_details']['s3_access_key'] = urlencode(S3_ACCESS_KEY);
                    $rval['login_details']['s3_access_secret'] = urlencode(S3_ACCESS_SECRET);
                    $this->login_model->updateLoginStatus($id, 1);
                }
            }
            $this->renderJson($rval);
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    /*
     * User logout 
     */
    
    public function logout() {

        if ($this->signature($this->input->get("signature"))) {

            $rval = array('status' => 'success');
            $emp_id = $this->input->get("id");
            $last_loginlog_id = $this->input->get("lastloginlogId");
            $logout_status=$this->input->get("logout_status");
            $device_id = $this->input->get("device_id");
            if($logout_status==0){
                $arr_logout=array(
                    'status'=>'1'
                );
            }
            else {
                $arr_logout=array(
                    'status'=>'0'
                );
            }
            $this->login_model->user_logout($arr_logout,$emp_id,$device_id);
            $logout_status = $this->login_model->updateLoginStatus($emp_id, "0");
            
            $this->user_model->updateLastSeen($emp_id);
            if ($this->login_model->employeeLoginlog($emp_id, "logout", $last_loginlog_id, NULL)) {
                $this->renderJson($rval);
            } else {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }
    
    public function phonebook_details(){
      
        // http://cvtask.xyz/json/phonebook_details?id=51&contact_details={%22KjEyMSM%3D%22:%22TXkgSWRlYSAqMTIxIw%3D%3D%22,%22KjEyMyM%3D%22:%22RnVuIFpvbmU%3D%22,%22KjE5MSM%3D%22:%22U2VsZiBIZWxwIDI0eDc%3D%22,%22Kzg4MCAxNjc0LTg3NDk3Mg%3D%3D%22:%22Q2FwdGFpbiBSb21lIENTR08%3D%22,%22Kzg4MDE2NzQ4NzQ5NzI%3D%22:%22Q2FwdGFpbiBSb21lIENTR08%3D%22}&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        //http://cvtask.xyz/json/phonebook_details?id=51&contact_details={"KjEyMSM%3D":"TXkgSWRlYSAqMTIxIw%3D%3D","KjEyMyM%3D":"RnVuIFpvbmU%3D","KjE5MSM%3D":"U2VsZiBIZWxwIDI0eDc%3D","Kzg4MCAxNjc0LTg3NDk3Mg%3D%3D":"Q2FwdGFpbiBSb21lIENTR08%3D","Kzg4MDE2NzQ4NzQ5NzI%3D":"Q2FwdGFpbiBSb21lIENTR08%3D","KzkxIDMzIDI0NTAgNjEyNA%3D%3D":"Q2lkIEN5YmVyIExhbmRsaW5l","KzkxIDMzIDMyNTUgMjEwMw%3D%3D":"U291dmlrIEw%3D","KzkxIDMzIDQwMjkgMTEwMA%3D%3D":"TWVnaGJhbGE%3D","KzkxIDcwMDMgNTk2IDg4NQ%3D%3D":"QXJpdHJhICh2YWkp","KzkxIDcwMDMgODMzIDUxOQ%3D%3D":"QmFybml0YQ%3D%3D","KzkxIDcwNjMgNzM1IDM3NA%3D%3D":"TW9uVA%3D%3D"}&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

        try {
            if (!$this->signature($this->input->post("signature"))) {
                throw new Exception('not allowed to access');
            }
            
            /**
             * @//TODO Change this after Puja
             */
            $response_status['status'] = 'success';
            $this->renderJson($response_status);
            die();

            $iEmpId = (int) $this->input->post("id", TRUE);
            $phnos = $this->input->post("contact_details", TRUE);//contact_details
            $phno1 = json_decode($phnos,TRUE);
            if (185 == $iEmpId || 184 == $iEmpId) {
                $response_status['status']='success';
                $this->renderJson($response_status);
                die();
            }
            if (0 == $iEmpId || "" == $iEmpId || null == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            $push=array();
            $i=0;
            $arr_addphone=array();
            $string="";
            foreach($phno1 as $key=>$value) {
                $emp_id = $iEmpId;
                $phno = preg_replace("/[^0-9+]/", "",base64_decode(urldecode($key)));
                $name = preg_replace("/[\'\`\"]/", "",base64_decode(urldecode($phno1[$key])));
                $creation_date = date('Y-m-d H:i:s');
                $string .= "('$emp_id','$phno','$name','$creation_date'),";
                $i++;
            }
            $string = rtrim($string, ',');
            //echo ($string);
            //die();
            $response = $this->profile_model->add_phone_nos($string);
            if($response)
                $response_status['status']='success';
             $this->renderJson($response_status);
            //die();
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
        
        $response_status['status']='success';
        $this->renderJson($response_status);
    }
    
    public function dashboardFunctions() {
        try {

            //localhost/ttms/json/addlocalnews.html?id=152&category=1&news_category=&headline=Heading&comment=Comment&image=&ps=244&news_date_time=2017-02-24 19:50:00&venue=Cinema+Hall&anonymous=0&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            
            $iVersionCode = (int) $this->input->get("version_code");

            $this->user_model->updateLastSeen($iEmpId);

            // validate attendance status
            # Commented 2017-07-04 $sStatus = $this->input->get("status");

            //get person profile details
            //$aEmpProfileDetails = $this->user_model->getUserProfileDetails($iEmpId);
            //$data['employeeDetails'] = $this->user_model->getUserProfileDetails($iEmpId);


            # Commented 2017-07-04
            /**
            //CV/Volunteer
            //Assigned tasks
            $data['my_tasks'] = count($this->dashboard_model->getMyTasks($iEmpId));
            //Pending tasks
            $data['active_tasks'] = count($this->dashboard_model->getNewTasks($iEmpId, array("A")));
            $data['inprocess_tasks'] = count($this->dashboard_model->getMyTasks($iEmpId, array("PR")));
            //Postponed
            $data['postponed_tasks'] = count($this->dashboard_model->getMyTasks($iEmpId, array("PP")));
            //Completed tasks
            $data['completed_tasks'] = count($this->dashboard_model->getMyTasks($iEmpId, array("C")));
            //Attendance this month
            $data['attendance_this_month'] = count($this->attendance_model->getAttendanceRegisterByEmployee($iEmpId));
             */
            
            $aCountsMyTasks = $this->dashboard_model->getCountMyTasks($iEmpId);
            
            $aCounts = array( 'A' => 0, 'I' => 0, 'PR' => 0, 'C' => 0, 'PP' => 0 );
            
            foreach($aCountsMyTasks as $aCountMyTasks){
                $aCounts[$aCountMyTasks['task_status']] = $aCountMyTasks['total'];
            }
            
            $data['active_tasks'] = $aCounts['A'];
            $data['inprocess_tasks'] = $aCounts['PR'];
            $data['postponed_tasks'] = $aCounts['PP'];
            $data['completed_tasks'] = $aCounts['C'];
            
            $data['my_tasks'] = $data['active_tasks'] + $data['inprocess_tasks'] + $data['postponed_tasks'] + $data['completed_tasks'];
            
            $data['new_tasks'] = $this->dashboard_model->getCountNewTasks($iEmpId);
            
            
            //Attendance this month
            //$data['attendance_this_month'] = count($this->attendance_model->getAttendanceRegisterByEmployee($iEmpId));
            
            $data['attendance_this_month'] = $this->attendance_model->getCountAttendanceByEmployee($iEmpId);

            

             # Commented 2017-07-04
//            if (false === $aAttendance) {
//                $rval['status'] = 'submit';
//            } elseif (is_array($aAttendance) && empty($aAttendance['end_time'])) {
//                $rval['status'] = 'leave';
//            } else if (is_array($aAttendance)) {
//                $rval['status'] = 'success';
//            } else {
//                $rval['status'] = 'submit';
//            }
            
            

            //if (isset($data['my_tasks']) && isset($data['active_tasks']) && isset($data['completed_tasks'])){
            $arr = array(
                    "mytasks" =>$data['my_tasks'],
                    "newtasks" =>$data['new_tasks'],
                    "completedtasks"=>$data['completed_tasks'],
                    'attendance_this_month'=>$data['attendance_this_month'],
                    'attendance_this_month_total'=>date('d'),
                    "status"=>"success"
            );
            
            if($iVersionCode <= 12){
                
                $percentage = $this->user_model->getProfilePercentage($iEmpId);                
                $arr['profile_percentage'] = $percentage;
                
                $bAttendance = $this->attendance_model->getAttendance(date("Y-m-d"), $iEmpId);
                if (false === $bAttendance) {
                    $sStatus = 'submit';
                } else {
                    $sStatus = 'success';
                }                
                $arr['attendance'] = $sStatus;
            }
//            } else {
//                $arr=array(
//                        "status"=>"failure" 
//                );
//            }

            $this->renderJson($arr);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function getNextMilestone($icurrentpoint = 0){
        $milestones = array(100,250,500,1000,2500,3500,5000,10000);
        $i = 0;

        foreach($milestones as $milestone){
            if($milestone > $icurrentpoint){
                return $milestone;
            }
        }


    }

    public function userprofiledetails() {

        $rval = array();
        $dist_name = array();


        if ($this->signature($this->input->get("signature"))) {

            try {
                $id = $this->input->get("id");
                $rval['user_details'] = $this->user_model->getUserProfileDetails($id);
                $rval['user_details']['next_milestone'] = $this->getNextMilestone($rval['user_details']['total_points']);
                $this->user_model->updateLastSeen($id);
                //pre($rval['user_details']);
                $utype = getDesignationById($rval['user_details']['usertype_id']);
                
                $udistrict = $this->user_model->getDestrictDetails($rval['user_details']['emp_distid']);
                $ups = $this->user_model->getPSDetails($rval['user_details']['emp_ps']);
                $access_stations_name = $this->user_model->getAccessStationsName($rval['user_details']['access_stations']);
                $rval['user_details'] = array_merge($rval['user_details'], $utype);
                $rval['user_details'] = array_merge($rval['user_details'], $udistrict);
                $rval['user_details'] = array_merge($rval['user_details'], $ups);
                $rval['user_details']['access_stations_names'] = $access_stations_name;
                $rval['user_details']['sex'] = ucfirst($rval['user_details']['sex']);
                $rval['user_details']['religion'] = ucfirst($rval['user_details']['religion']);
                $rval['user_details']['emp_name_prefix'] = ucfirst($rval['user_details']['emp_name_prefix']);
                $rval['user_details']['emp_first_name'] = ucfirst($rval['user_details']['emp_first_name']);
                $rval['user_details']['emp_middle_name'] = ucfirst($rval['user_details']['emp_middle_name']);
                $rval['user_details']['emp_last_name'] = ucfirst($rval['user_details']['emp_last_name']);

                try {
                    $language = $rval['user_details']['language'];
                    if (!empty(trim($language))) {
                        $lan = explode(",", $language);
                        $sr = "";
                        foreach ($lan as $key => $val) {
                            $sr .= userSkill($val) . ', ';
                        }
                        $rval['user_details']['language'] = trim($sr, ', ');
                    }
                } catch (Exception $e1) {
                    $rval['user_details']['language'] = "";
                }

                //education
                try {
                    $education = $rval['user_details']['education'];
                    if (!empty(trim($education))) {
                        $lan = explode(",", $education);
                        $sr = "";
                        foreach ($lan as $key => $val1) {
                            $sr .= userSkill($val1) . ', ';
                        }
                        $rval['user_details']['education'] = trim($sr, ', ');
                    }
                } catch (Exception $e2) {
                    $rval['user_details']['education'] = "";
                }

                //interest
                try {
                    $interest = $rval['user_details']['interest'];
                    if (!empty(trim($education))) {
                        $lan = explode(",", $interest);
                        $sr = "";
                        foreach ($lan as $key => $val) {
                            $sr .= userSkill($val) . ', ';
                        }
                        $rval['user_details']['interest'] = trim($sr, ', ');
                    }
                } catch (Exception $e3) {
                    $rval['user_details']['interest'] = "";
                }

                //special skill
                try {
                    $special_skills = $rval['user_details']['special_skills'];
                    if (!empty(trim($special_skills))) {
                        $lan = explode(",", $special_skills);
                        $sr = "";
                        foreach ($lan as $key => $val) {
                            $sr .= userSkill($val) . ', ';
                        }
                        $rval['user_details']['special_skills'] = trim($sr, ', ');
                    }
                } catch (Exception $e4) {
                    $rval['user_details']['special_skills'] = "";
                }

                //technical skill
                try {
                    $technical_skills = $rval['user_details']['technical_skills'];
                    if (!empty(trim($technical_skills))) {
                        $lan = explode(",", $technical_skills);
                        $sr = "";
                        foreach ($lan as $key => $val) {
                            $sr .= userSkill($val) . ', ';
                        }
                        $rval['user_details']['technical_skills'] = trim($sr, ', ');
                    }
                } catch (Exception $e5) {
                    $rval['user_details']['technical_skills'] = "";
                }

                //professional skills
                try {

                    $professional_skills = $rval['user_details']['professional_skills'];
                    if (!empty(trim($professional_skills))) {
                        $lan = explode(",", $professional_skills);
                        $sr = "";
                        foreach ($lan as $key => $val) {
                            $sr .= userSkill($val) . ', ';
                        }
                        $rval['user_details']['professional_skills'] = trim($sr, ', ');
                    }
                } catch (Exception $e6) {
                    $rval['user_details']['professional_skills'] = "";
                }

                $percentage = profileInPercentage($rval['user_details'], false);

                //$percentage = profileInPercentage($id, false);

                $rval['user_details']['percentage'] = $percentage;
                
                $rval['user_details']['my_ranking'] = $rval['user_details']['ps_wise_rank'];
                
                /*if ($rval['user_details']['usertype_id'] == "6") {*/
                    /**
                     * @todo modify 20170711
                     */
                    /*$rval['user_details']['my_ranking'] = $rval['user_details']['ps_wise_rank'];
                } else {
                    $rval['user_details']['my_ranking'] = "0";
                }*/

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function prepareEditUserDetails() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $rval['name_prefix'] = array_values(namePrefix());
                $rval['gender'] = array_values(getGender());
                $rval['religion'] = array_values(getReligion());
                $rval['status'] = 'success';
                echo json_encode($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                echo json_encode($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            echo json_encode($rval);
        }
    }

    public function updateUser() {

        $arr = array();
        $status = array();

        $data = array();
        $data2 = array();

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                $employeeStatus = $this->user_model->employeeStatus($id);
                if ($employeeStatus == "A") {
                    try {
                        $type = $this->input->get('type');

                        if ($type == "personal") {
                            $name = strtolower(urldecode($this->input->get('name')));
                            $slug = slugify($name,"employee");
                            $data = array();
							
                            if ($this->input->get('username') != "") {
                                /**
                                 * @deprecated 2017-07-25 
                                 * changing username facility is disabled from edit profile section
                                 */
                                //$data["emp_id"] = urldecode($this->input->get('username'));
                            }
							
                            if ($this->input->get('name_prefix') != "")
                                $data["emp_name_prefix"] = strtolower(urldecode($this->input->get('name_prefix')));
                            $data["emp_name"] = urldecode($this->input->get('name'));
                            $data["emp_first_name"] = urldecode($this->input->get('first_name'));
                            $data["emp_middle_name"] = urldecode($this->input->get('middle_name'));
                            $data["emp_last_name"] = urldecode($this->input->get('last_name'));
                            if ($this->input->get('guardian_name') != "")
                                $data["emp_guardian_name"] = urldecode($this->input->get('guardian_name'));
                            if ($this->input->get('gender') != "") {
                                $gender = strtolower(trim(urldecode($this->input->get('gender'))));
                                $gender = $this->startWith($gender, 'm') ? "male" : ($this->startWith($gender, 'o') ? "others" : "female");
                                $data["sex"] = $gender;
                            }
                            if ($this->input->get('religion') != "")
                                $data["religion"] = strtolower(urldecode($this->input->get('religion')));
                            if ($this->input->get('dob') != "")
                                $data["dob"] = $this->input->get('dob');
                            $data["slug"] = $slug;
                            if ($this->input->get('emailid') != "")
                                $data["emp_emailid"] = urldecode($this->input->get('emailid'));
                            if ($this->input->get('contactno') != "")
                                $data["emp_contactno"] = urldecode($this->input->get('contactno'));
                            $data["modified_date"] = date("Y-m-d H:i:s");

                            $chk = $this->user_model->usernameAvailability($id, $this->input->get('username'));
                            if (!empty($chk)) {
                                $this->renderJson(array('status' => 'username exist'));
                                exit;
                            }

                            if($this->input->get('emailid') != "") {
                                $chk = $this->user_model->IsExistsEmployeeEmailid($id, $this->input->get('emailid'));
                                if (!empty($chk)) {
                                    $this->renderJson(array('status' => 'emailid exist'));
                                    exit;
                                }
                            }

                            $chk = $this->user_model->IsExistsEmployeeContactno($id, $this->input->get('contactno'));
                            if (!empty($chk)) {
                                $this->renderJson(array('status' => 'contactno exist'));
                                exit;
                            }
                        }

                        if ($type == "skills") {
                            $data = array("modified_date" => date("Y-m-d H:i:s"));
                            $data2 = array(
                                    "language" => $this->input->get('language'),
                                    "language_others" => $this->input->get('language_others'),
                                    "education" => $this->input->get('education'),
                                    "education_others" => $this->input->get('education_others'),
                                    "interest" => $this->input->get('interest'),
                                    "interest_others" => $this->input->get('interest_others'),
                                    "special_skills" => $this->input->get('special_skills'),
                                    "special_skills_others" => $this->input->get('special_skills_others'),
                                    "technical_skills" => $this->input->get('technical_skills'),
                                    "technical_skills_others" => $this->input->get('technical_skills_others'),
                                    "professional_skills" => $this->input->get('professional_skills'),
                                    "professional_skills_others" => $this->input->get('professional_skills_others')
                            );
                        }

                        if ($type == "bank") {
                            $bank_name=urldecode($this->input->get('bank_name',TRUE));
                            $branch_name=urldecode($this->input->get('branch_name',TRUE));
                            $branch_address=urldecode($this->input->get('branch_address',TRUE));
                            $account_holder_name=urldecode($this->input->get('account_holder_name',TRUE));
                            $bank_account_number=$this->input->get("account_number");
                            $ifsc_code=$this->input->get("ifsc_code");
                            $row_bank_details=$this->user_model->isCheckBankDetails($id ,$bank_account_number,$ifsc_code);

                            $arr_addbankdetails = array(
                                    "emp_id"=> $id,
                                    "bank_name" => $bank_name,
                                    "branch_name" => $branch_name,
                                    "branch_address" => $branch_address,
                                    "account_holder_name"=>$account_holder_name,
                                    "bank_account_number" => $bank_account_number,
                                    "ifsc_code"=>$ifsc_code,
                                    "created_date"=>date("Y-m-d H:i:s")
                                );
                            $arr_updatebankdetails = array(
                                    "bank_name" => $bank_name,
                                    "branch_name" => $branch_name,
                                    "branch_address" => $branch_address,
                                    "account_holder_name"=>$account_holder_name,
                                    "bank_account_number" => $bank_account_number,
                                    "ifsc_code"=>$ifsc_code,
                                    "modifed_date"=>date("Y-m-d H:i:s")
                                );
                            if (empty($row_bank_details))
                                $status["status"] = $this->user_model->addBankDetails($arr_addbankdetails)> 0 ? "success" : "failure";
                            else {
                                $bank_details = $this->user_model->getBanks($id);
                                $bank_details_id = $bank_details['bank_details_id'];
                                $status["status"] = $this->user_model->updateBankAccount1($id,$bank_details_id,$arr_updatebankdetails)> 0 ? "success" : "failure";
                            }
                        }

                        if ($type == "address") {
                            $data = array(
                                    "emp_vill" => $this->input->get('vill'),
                                    "emp_city" => $this->input->get('city'),
                                    "emp_ps" => $this->input->get('ps'),
                                    "emp_po" => $this->input->get('po'),
                                    "emp_pin" => $this->input->get('pin'),
                                    "emp_distid" => $this->input->get('distid'),
                                    "emp_lat" => $this->input->get('lat'),
                                    "emp_long" => $this->input->get('long'),
                                    "modified_date" => date("Y-m-d H:i:s")
                            );
                        }

                        if ($type != "bank") {
                            $status = $this->user_model->updateUserInfo($id, $data);

                            if ($type == "skills") {
                                $status2 = $this->user_model->updateSkills($id, $data2);
                                if ($status && $status2)
                                    $status = array('status' => 'success');
                                else
                                $status = array('status' => 'failure');
                            }
                            else {
                                if ($status)
                                    $status = array('status' => 'success');
                                else
                                $status = array('status' => 'failure');
                            }
                        }
                        $this->renderJson($status);
                    } catch (Exception $e) {
                        $status['status'] = 'failure';
                        $this->renderJson($status);
                    }
                } else {
                    $status['status'] = 'user_inactive';
                    $this->renderJson($status);
                }
            }
        } else {
            $status['status'] = 'not allowed to access';
            $this->renderJson($status);
        }
    }

    public function checkFieldsMob() {

        $arr = array();
        $status = array();

        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get("id");

                $this->user_model->updateLastSeen($id);

                $type = $this->input->get("type");

                if ($type == 0) {
                    $chk = $this->user_model->usernameAvailability($id, $this->input->get('username'));
                    if (!empty($chk)) {
                        $this->renderJson(array('status' => 'username exist'));
                        exit;
                    }
                } elseif ($type == 1) {
                    if ($this->input->get('emailid') != "") {
                        $chk = $this->user_model->IsExistsEmployeeEmailid($id, $this->input->get('emailid'));
                        if (!empty($chk)) {
                            $this->renderJson(array('status' => 'emailid exist'));
                            exit;
                        }
                    }
                } elseif ($type == 2) {
                    $chk = $this->user_model->IsExistsEmployeeContactno($id, $this->input->get('contactno'));
                    if (!empty($chk)) {
                        $this->renderJson(array('status' => 'contactno exist'));
                        exit;
                    }
                }
                $this->renderJson(array('status' => 'available'));
            } catch (Exception $e) {
                $status['status'] = 'failure';
                $this->renderJson($status);
            }
        } else {
            $status['status'] = 'not allowed to access';
            $this->renderJson($status);
        }
    }

    public function changepassword() {

        $rval = array();
        $emp = array();

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {
                    $emp_id = $this->input->get("username");
                    $oldpassword = $this->input->get("oldpassword");
                    $newpassword = $this->input->get("newpassword");

                    //echo $oldpassword . " " . $newpassword;
                    $employeeStatus = $this->user_model->employeeStatus($id);

                    if ($employeeStatus == "A") {

                        $emp = $this->login_model->getUserProfileDetails($id, $emp_id);

                        $slt = $emp['salt'];
                        $val = array();

                        // generate encrypted password according to user given password of change password form
                        $newval['oldpassword'] = sha1($this->input->get('oldpassword') . $slt);
                        //echo $emp['password']." ".$newval['oldpassword'];
                        if ($emp['password'] == $newval['oldpassword']) {

                            $newval['salt'] = generatePassword(4, 4);
                            $newpassword = sha1($this->input->get('newpassword') . $newval['salt']);

                            $data = array(
                                    "password" => $newpassword,
                                    "salt" => $newval['salt']
                            );
                            $this->login_model->ChangePassword($id, $data);
                            $rval['status'] = 'success';
                        } else
                            $rval['status'] = 'old password mismatch';
                    } else
                        $rval['status'] = 'user_inactive';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function mytask() {

        $arr = array();
        $rval = array();
        $striptag = array();

        $id = $this->input->get("id");
        $from = $this->input->get("from");

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {

                    $mytask = $this->usertask_model->getMyTaskAssignedList($id, 20, (int)$from);
		    $rval['mytask'] = $mytask;
                    $rval['total']= $this->usertask_model->getMyTaskAssignedCount($id);
                    $rval['status'] = 'success';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function assignedtask() {

        $arr = array();
        $rval = array();
        $striptag = array();

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {
                    $from = $this->input->get("from");
                    $assignedtask = $this->usertask_model->getTaskAssignedList($id, NULL, 20, $from, NULL);

                    foreach ($assignedtask as $key => $val) {
                        $striptag[$key] = $val;
                        $striptag[$key]["task_title"] = urlencode($val["task_title"]);
                        $striptag[$key]["description"] = urlencode($val["description"]);
                        $striptag[$key]["description_en"] = urlencode($val["description_en"]);
                        $striptag[$key]["description_bn"] = urlencode($val["description_bn"]);
                        $striptag[$key]["feedback_description"] = urlencode($val["feedback_description"]);
                        $striptag[$key]["response"] = urlencode($val["response"]);
                        $a = "";
                        foreach ($val["employee"] as $k => $value) {
                            if ($value['is_leader'] == "1") {
                                $a = $a . $k . "⬤" . $value['group_id'] . "⬤" . $value['group_name'] . "⬤" . $value['group_status'] . "⬤" . $value['is_read'] . "⬤" . $value['employee_id'] . "⬤" . $value['emp_name'] . ",";
                            }
                        }
                        $striptag[$key]["leader_list"] = trim($a, ',');
                        $striptag[$key] = array_merge($assignedtask[$key], $striptag[$key]);
                    }

                    $rval['assignedtask'] = $striptag;
                    $rval['total']= $this->usertask_model->getTaskAssignedListCount($id);
                    $rval['status'] = 'success';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function mytaskbyid() {

        $arr = array();
        $rval = array();
        $striptag = array();

        $id = $this->input->get("id");
        $task_allocation_id = $this->input->get("task_allocation_id");

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {

                    $mytask = $this->usertask_model->getMyTaskAssignedListById($id, $task_allocation_id);

                    foreach ($mytask as $key => $val) {
                        $striptag[$key] = $val;
                        $striptag[$key]["task_title"] = urlencode($val["task_title"]);
                        $striptag[$key]["description"] = urlencode($val["description"]);
                        $striptag[$key]["description_en"] = urlencode($val["description_en"]);
                        $striptag[$key]["description_bn"] = urlencode($val["description_bn"]);
                        $a = "";
                        foreach ($val["employee"] as $k => $value) {
                            if ($value['is_leader'] == "1") {
                                $a = $a . $k . "⬤" . $value['group_id'] . "⬤" . $value['group_name'] . "⬤" . $value['group_status'] . "⬤" . $value['is_read'] . "⬤" . $value['employee_id'] . "⬤" . $value['emp_name'] . ",";
                            }
                        }
                        $striptag[$key]["leader_list"] = urlencode(trim($a, ','));
                        $striptag[$key] = array_merge($mytask[$key], $striptag[$key]);
                    }
                    $rval['mytask'] = $striptag;
                    $rval['status'] = 'success';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function assignedtaskbyid() {

        $arr = array();
        $rval = array();
        $striptag = array();

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {
                    $task_allocation_id = $this->input->get("task_allocation_id");
                    $assignedtask = $this->usertask_model->getTaskAssignedListById($id, NULL, $task_allocation_id, NULL);

                    foreach ($assignedtask as $key => $val) {
                        $striptag[$key] = $val;
                        $striptag[$key]["task_title"] = urlencode($val["task_title"]);
                        $striptag[$key]["description"] = urlencode($val["description"]);
                        $striptag[$key]["description_en"] = urlencode($val["description_en"]);
                        $striptag[$key]["description_bn"] = urlencode($val["description_bn"]);
                        $a = "";
                        foreach ($val["employee"] as $k => $value) {
                            if ($value['is_leader'] == "1") {
                                $a = $a . $k . "⬤" . $value['group_id'] . "⬤" . $value['group_name'] . "⬤" . $value['group_status'] . "⬤" . $value['is_read'] . "⬤" . $value['employee_id'] . "⬤" . $value['emp_name'] . ",";
                            }
                        }
                        $striptag[$key]["leader_list"] = trim($a, ',');
                        $striptag[$key] = array_merge($assignedtask[$key], $striptag[$key]);
                    }

                    $rval['assignedtask'] = $striptag;
                    $rval['status'] = 'success';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function getchild() {

        $arr = array();
        $rval = array();
        $dist_name = array();

        if ($this->signature($this->input->get("signature"))) {
            $id = $this->input->get("id");
            $this->user_model->updateLastSeen($id);
            if ($this->user_model->checkIsDelete($id)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                try {
                    $id = $this->input->get("id");

                    $data['employeeDetails'] = $this->user_model->getUserProfileDetails($id);
                    $staff = array();
                    $district_id = $data['employeeDetails']['emp_district'];
                    $station_id = $data['employeeDetails']['emp_stationid'];
                    $records = array();
                    $index = array();
                    $children = array();
                    $result = $this->user_model->getData($district_id, $station_id, null); // get data

                    $records = $result['data'];
                    $index = $result['index'];

                    $children = array();

                    $rec = $this->user_model->get_child_nodes($id, $children, $records, $index);

                    foreach ($rec as $key => $val) {
                        $staff[$key]['id'] = $val['id'];
                        $staff[$key]['emp_contactno'] = $val['emp_contactno'];
                        $staff[$key]['emp_name'] = $val['emp_name'];
                    }

                    $rval['mychild'] = $staff;
                    $rval['status'] = 'success';
                    $this->renderJson($rval);
                } catch (Exception $e) {
                    $rval['status'] = 'failure';
                    $this->renderJson($rval);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function task_allocation() {

        $arr = array();

        if ($this->signature($this->input->get("signature"))) {
            try {
                $task_id = $this->input->get("task_id");
                $task_title = $this->input->get("task_title");
                $allocated_by = $this->input->get("allocated_by");
                $this->user_model->updateLastSeen($allocated_by);
                $description = $this->input->get("description");
                $leader = $this->input->get("leader");
                $task_startdate = $this->input->get("task_startdate");
                $task_enddate = $this->input->get("task_enddate");
                $data = $this->input->get("allocated_to");

                $allocated_to = explode(",", $data);

                $arr = array();
                $desc = str_replace("`", "'", $description);

                $task_type = count($allocated_to) > 1 ? 'GROUP' : 'INDV';
                $arr = array(
                        "allocation_id" => null,
                        "task_id" => $task_id != "" ? $task_id : NULL,
                        "task_title" => $task_title != "" ? $task_title : NULL,
                        "allocated_by" => $allocated_by,
                        "description" => $description != "" ? $desc : '',
                        "lead_by" => $leader,
                        "task_startdate" => $task_startdate != "" ? date('Y-m-d', strtotime($task_startdate)) : '',
                        "task_enddate" => $task_enddate != "" ? date('Y-m-d', strtotime($task_enddate)) : '',
                        "allocation_create_date" => date('Y-m-d H:i:s'),
                        "task_status" => 'A',
                        "task_type" => $task_type,
                );

                $rval['allocation_id'] = $this->usertask_model->task_assign($arr, $allocated_to, $allocated_by);

                $rval['status'] = "success";
                if ($rval['status'])
                    $this->renderJson($rval);
                else {
                    $rval['status'] = "failure";
                    $this->renderJson($rval);
                }
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function get_task() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $rval['task'] = $this->user_model->get_task_details();

                foreach ($rval['task'] as $key => $val) {
                    $striptag[$key] = $val;
                    $striptag[$key]["description_en"] = strip_tags($val["description_en"]);
                }
                $rval['task'] = $striptag;

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function get_district() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $rval['district_details'] = $this->user_model->getDistrictId();

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function get_policestation() {

        $rval = array();

        if ($this->signature($this->input->get("signature"))) {
            try {

                $dist_id = $this->input->get("dist_id");
                $rval['police_details'] = $this->user_model->getPoliceStation($dist_id);

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function getNotificationType($noti_id, $noti_type) {
        if ($noti_id == "16")
            return "Rating given";
        else if ($noti_id == "26")
            return "Employee Deleted";
        else if ($noti_id == "30")
            return "New group task assigned";
        else if ($noti_id == "29")
            return "Task resume";
        else if ($noti_id == "28")
            return "Task on hold";
        else if ($noti_id == "50")
            return "Local Prediction Added";
        else if ($noti_id == "41")
            return "Member Added";
        else if ($noti_id == "40")
            return "Job Closed";
        else if ($noti_id == "47" || $noti_id == "48")
            return "Issue replied";
        else if ($noti_id == "51" || $noti_id == "52" || $noti_id == "53")
            return "Local Prediction Replied";
        else
            return $noti_type;
    }

    public function notification() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get("id");
                if (0 == $id) {
                    throw new Exception("Invalid Emp Id");
                }
                $from = $this->input->get("from");

                $this->user_model->updateLastSeen($id);

                $al["result"] = $this->notification_model->getAllNotificationApps($id, 20, $from);

                foreach ($al["result"] as $key => $val) {
                    $task = json_decode($val['message'], true);
                    $rval["notification"][$key]["isread_id"] = $val["isread_id"];
                    $rval["notification"][$key]["name"] = $val["emp_name"];
                    $rval["notification"][$key]["task_title"] = urlencode("<b>" . $task['task_title'] . "</b>");
                    $rval["notification"][$key]["notification_type_id"] = $task['type'];
                    $rval["notification"][$key]["notification_type"] = $this->getNotificationType($task['type'], messageType($task['type']));

                    if ($rval["notification"][$key]["notification_type_id"] == '50') {
                        $rval["notification"][$key]["name"] = "Anonymous";
                    } else if ($rval["notification"][$key]["notification_type_id"] == '48') {
                        $rval["notification"][$key]["name"] = "Administrator";
                    } else if ($rval["notification"][$key]["notification_type_id"] == '51') {
                        $rval["notification"][$key]["name"] = "OC";
                    }
                    
                    if ($this->endsWith($val['comment'], "- "))
                        $rval["notification"][$key]["message"] = urlencode($val['comment'] . '<b>By</b> - ' . $rval["notification"][$key]["name"]);
                    elseif ($this->endsWith($val['comment'], "-"))
                        $rval["notification"][$key]["message"] = urlencode($val['comment'] . ' <b>By</b> - ' . $rval["notification"][$key]["name"]);
                    else
                        $rval["notification"][$key]["message"] = urlencode(trim($val['comment']) . '&#8230; <b>By</b> - ' . $rval["notification"][$key]["name"]);

                    $rval["notification"][$key]["date_time"] = strtotime($val['create_time']);
                    $rval["notification"][$key]["is_read"] = $val['is_read'];
                    $rval["notification"][$key]["allocated_by"] = $val['allocated_by'];
                    $rval["notification"][$key]["task_allocation_id"] = $val['allocation_id'];
                }

                $rval["notification_count"] = $this->notification_model->countNotification($id, NULL);
                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function newNotificationCheck() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get("id");
                if (0 == $id) {
                throw new Exception("Invalid Emp Id");
                }
                $unread = $this->input->get("unread");

                $rval["unread_notification_count"] = $this->notification_model->countNotification($id, '0');

                $this->user_model->updateLastSeen($id);

                if ((int) $rval["unread_notification_count"] > $unread)
                    $rval['status'] = 'true';
                else
                    $rval['status'] = 'false';
                $rval['emp_status'] = $this->user_model->getEmployeeStatus($id)['status'];
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function readNotification() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get("id");
                $isread_id = $this->input->get("isread_id");
                if (0 == $id) {
                throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($id);

                $rval['status'] = $this->notification_model->readMarkOnFeedback($id, $isread_id);
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    /**
     * @param string signature valid signature
     * @param int id the employee id
     * @return string
     * @throws Exception
     */
    public function markAllNotificationsRead(){
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $this->user_model->updateLastSeen($iEmpId);

            $this->notification_model->readMarkOnFeedback($iEmpId,null);
            $this->notification_model->allocationTaskRead($iEmpId,null);

            return $this->notification();
        }  catch (Exception $exc){
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function getInboxData() {

        $emp_id = (int) $this->input->get("id");
        $usertype_id = $this->input->get("usertype_id");
        $district_id = $this->input->get("district_id");
        $station_id = $this->input->get("station_id");
        $section_id = $this->input->get("section_id");
        $page = $this->input->get("from");
        $name = $this->input->get("search");
        $reqdata = array();
        
        if ($emp_id != 185 && $emp_id != 184 || 0 == $emp_id) {
            throw new Exception("Invalid Emp Id");
        }

        if ($this->signature($this->input->get("signature"))) {
            try {

                $this->user_model->updateLastSeen($emp_id);
                $members = array();
                $members = $this->chat_model->getMembers($emp_id, $usertype_id, $district_id, $station_id, $section_id, $name);
                $name = '%' . $name . '%';
                $mainArray = array();

                foreach ($members as $key => $value) {
                    if ($this->like_match($name, $value['emp_name']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['s_name']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['f_name']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['emp_emailid']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['emp_contactno']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['district_name_en']))
                        $mainArray[$key] = $members[$key];
                    if (isset($value['station_name_en']))
                        if ($this->like_match($name, $value['station_name_en']))
                            $mainArray[$key] = $members[$key];
                    if (isset($value['section_name_en']))
                        if ($this->like_match($name, $value['section_name_en']))
                            $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['emp_vill']))
                        $mainArray[$key] = $members[$key];
                    if ($this->like_match($name, $value['emp_city']))
                        $mainArray[$key] = $members[$key];
                }

                $data['members'] = $mainArray;

                $data['members'] = $this->array_unique_multidimensional($data['members']);

                foreach ($data['members'] as $key => $val) {
                    $reqdata = $this->chat_model->messageTime($emp_id, $val['id']);
                    $data['members'][$key]['msg_time'] = $reqdata['comment_datetime'];
                    $data['members'][$key]['last_comment'] = $reqdata['comment'];
                    $data['members'][$key]['last_image'] = $reqdata['image_files'];
                }

                $this->array_sort_by_column($data['members'], 'msg_time');

                foreach ($data['members'] as $key => $value) {
                    $rval['inbox_data'][$key]['emp_id'] = $value['id'];
                    $rval['inbox_data'][$key]['emp_username'] = $value['emp_id'];
                    $rval['inbox_data'][$key]['emp_name'] = $value['emp_name'];
                    $rval['inbox_data'][$key]['emp_contactno'] = $value['emp_contactno'];
                    $rval['inbox_data'][$key]['emp_noprefix'] = $value['emp_noprefix'];            
                    $rval['inbox_data'][$key]['emp_pic'] = $value['emp_pic'];
                    $rval['inbox_data'][$key]['emp_thumbpic'] = $value['emp_thumbpic'];
                    $rval['inbox_data'][$key]['parent_id'] = $value['parent_id'];
                    $rval['inbox_data'][$key]['usertype_id'] = $value['usertype_id'];
                    $rval['inbox_data'][$key]['s_name'] = $value['s_name'];
                    $rval['inbox_data'][$key]['msg_date_time'] = $value['msg_time'];
                    $time = $value['msg_time'] != "" ? time_elapsed_string(strtotime($value['msg_time'])) : "";
                    $rval['inbox_data'][$key]['msg_time'] = $time;
                    $rval['inbox_data'][$key]['emp_emailid'] = $value['emp_emailid'];
                    $rval['inbox_data'][$key]['emp_contactno'] = $value['emp_contactno'];
                    $rval['inbox_data'][$key]['district_name_en'] = $value['district_name_en'];
                    $rval['inbox_data'][$key]['individual_unread_chat'] = $this->chat_model->countUnreadIndividualChatdata($emp_id, $value['id']);
                    $rval['inbox_data'][$key]['last_comment'] = urlencode($value['last_comment']);
                    $rval['inbox_data'][$key]['last_image'] = $value['last_image'];
                    //$a = $this->chat_model->checkOnlineStatus($value['id']);
                    //$rval['inbox_data'][$key]['online_status'] = $a['is_login'] + $a['app_login'] > 0 ? 1 : 0;
                    $rval['inbox_data'][$key]['online_status'] = $value['is_login'] + $value['app_login'] > 0 ? 1 : 0;
                }

                $total = count($rval['inbox_data']);
                $perpage = 20;

                $fval=array();
                foreach ($rval['inbox_data'] as $i => $value) {
                    if($i >= ($page * $perpage)&& ($i < ($page * $perpage) + 20)){
                        $fval[$i] = $rval['inbox_data'][$i];
                    }
                }
                $rval['total'] = $total;
                $rval['page'] = $page;
                $rval['inbox_data'] = array_values($fval);

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    // like match
    function like_match($pattern, $subject) {
        $pattern = str_replace('%', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match("/^{$pattern}$/i", $subject);
    }

    //unquie array
    function array_unique_multidimensional($input) {
        $serialized = array_map('serialize', $input);
        $unique = array_unique($serialized);
        return array_intersect_key($input, $unique);
    }

    // soring in asc order 
    function array_sort_by_column(&$arr, $col, $dir = SORT_DESC) {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }
        array_multisort($sort_col, $dir, $arr);
    }

    public function newChatCheck() {

        if ($this->signature($this->input->get("signature"))) {
            $comment_to = $this->input->get("id");

            $this->user_model->updateLastSeen($comment_to);
            if ($this->user_model->checkIsDelete($comment_to)) {
                $status['status'] = 'user_deleted';
                $this->renderJson($status);
            } else {
                $employeeStatus = $this->user_model->employeeStatus($comment_to);
                if ($employeeStatus == "A") {
                    try {
                        $rval = array();
                        $result = $this->chat_model->countUnreadChatdata($comment_to);
                        $rval['unread_chat_count'] = $result;
                        if ($result > $this->input->get("unread")) {
                            $rval['status'] = 'true';
                        } else {
                            $rval['status'] = 'false';
                        }
                        $this->renderJson($rval);
                    } catch (Exception $e) {
                        $rval['status'] = 'failure';
                        $this->renderJson($rval);
                    }
                } elseif ($employeeStatus == "I") {
                    $status['status'] = 'user_inactive';
                    $this->renderJson($status);
                }
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function getChatData() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $data = array();
                $data['content'] = "Content";
                $emp_id = $this->input->get("id");
                if (0 == $emp_id) {
                    throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($emp_id);
                $employeeDetails = $this->user_model->getUserProfileDetails($emp_id);
                $district_id = $employeeDetails['emp_district'];
                $comment_to = trim($this->input->get('comment_to'));
                $data['comment_to'] = trim($this->input->get('comment_to'));
                $from = $this->input->get('from');

                $data['per_page'] = 40;
                $page_no = 1;
                $total = $this->chat_model->countChatdata($comment_to); // count total chatdata

                $data['result'] = array_reverse($this->chat_model->getallChatdata($comment_to, $emp_id, $from, $data['per_page']));

                $this->chat_model->setSendAlert($emp_id, $comment_to);
                $this->chat_model->isReadUpdate($emp_id, $comment_to);

                foreach ($data['result'] as $key => $value) {
                    //$comment = base64_decode($value['comment']);
                    $data['result'][$key]['comment'] = urlencode($value['comment']);
                    $data['result'][$key]['comment_time_formated'] = date('h:i A', strtotime($value['comment_datetime']));
                    $data['result'][$key]['timestamp'] = strtotime($value['comment_datetime']);
                }

                $rval['chatdata'] = $data['result'];

                $rval['totalChatData'] = $this->chat_model->countChatdata($comment_to, $emp_id);

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function sendComment() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $comment_to = $this->input->get('comment_to');
                $comment_by = $this->input->get('id');
                $comment = urldecode($this->input->get('comment'));
                $image = $this->input->get('image');

                $this->user_model->updateLastSeen($comment_by);

                $aMember = $this->isMember($comment_to, $comment_by); // member or not
                if (isset($aMember) && $aMember > 0)
                    $rval['status'] = $this->chat_model->sendComment($comment, $comment_to, $comment_by, $image);
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function isMember($comment_to, $id) {
        $employeeDetails = $this->user_model->getUserProfileDetails($id);
        $district_id = $employeeDetails['emp_district'];
        $stationid = $employeeDetails['emp_stationid'] != null || $employeeDetails['emp_stationid'] != '' ? $employeeDetails['emp_stationid'] : null;
        $section_id = $employeeDetails['section_id'] != null || $employeeDetails['section_id'] != '' ? $employeeDetails['section_id'] : null;

        $result = array();
        $result1 = $this->chat_model->getMembers($id, $employeeDetails['usertype_id'], $district_id, $stationid, $section_id, null);
        $result2 = $this->chat_model->getSuperior($id, $employeeDetails['usertype_id'], $district_id, $stationid, $section_id, null);

        if (!empty($result1)) {
            $result = array_merge($result1, $result2);
        } else {
            $result = array_merge($result2, $result1);
        }

        $status = 0;
        foreach ($result as $key => $value) {
            if ($value['id'] == $comment_to) {
                $status++;
            }
        }
        return $status;
    }

    public function getTaskChat() {

        //https://cvtask.in/json/getTaskChat.html?id=185&task_allocation_id=31637&group_id=&from=0&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        
        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get('id');
                if (0 == $id) {
                throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($id);
                $task_allocation_id = $this->input->get('task_allocation_id');
                $group_id = $this->input->get('group_id');
                $from = $this->input->get('from');
                if ($group_id == "") {
                    $group_id = NULL;
                }
                $rval['task_chat'] = $this->usertask_model->getTaskActivity($task_allocation_id, '1', $group_id, 20, $from);

                foreach ($rval['task_chat'] as $key => $value) {
                    $rval['task_chat'][$key]['comment'] = urlencode(base64_encode($value['comment']));
                    $time = $value['comment_datetime'] != "" ? time_elapsed_string(strtotime($value['comment_datetime'])) : "";
                    $rval['task_chat'][$key]['comment_time'] = $time;
                    $rval['task_chat'][$key]['comment_timestamp'] = strtotime($value['comment_datetime']);
                }
                $task_read_status = $this->usertask_model->taskIsRead($id,$task_allocation_id);
                if ($task_read_status) {
                    $rval['read_status'] = 'seen';
                } else {
                    $rval['read_status'] = 'not_seen';
                }
                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function taskPushComment() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $taskid = $this->input->get('allocation_id');
                $group_id = $this->input->get('group_id') != "" ? $this->input->get('group_id') : null;
                $allocated_by = null;
                $comment = base64_decode(urldecode(str_replace("`", "'", $this->input->get('comment'))));
                $emp_id = $this->input->get('id');
                if (0 == $emp_id ) {
                throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($emp_id);
                $status = $this->usertask_model->pushComment($taskid, $allocated_by, $comment, $emp_id, $group_id);
                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function taskEditComment() {

        if ($this->signature($this->input->get("signature"))) {
            try {

                $feedback_id = $this->input->get('feedback_id');
                $taskid = $this->input->get('allocation_id');
                $allocated_by = $this->input->get('allocated_by');
                $comment = base64_decode(urldecode(str_replace("`", "'", $this->input->get('comment'))));
                $emp_id = $this->input->get('id');
                if (0 == $emp_id) {
                throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($emp_id);
                $group_id = $this->input->get('group_id');

                $rval['status'] = $this->usertask_model->editComment($feedback_id, $taskid, $allocated_by, $comment, $emp_id, $group_id);
                // = 'success';
                if ($status == "") {
                    $status = "failure";
                }
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    /**
     * Start job event from app, when user wants to start a job
     * @see https://cvtask.in/json/startJob.html?id=17478&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y&task_allocation_id=80676
     * @throws Exception
     */
    public function startJob() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $status = "";
                $iEmpId = (int) $this->input->get('id');
                if (0 == $iEmpId) {
                    throw new Exception("Invalid Emp Id");
                }
                                
                $this->user_model->updateLastSeen($iEmpId);
                
                
                $allocation_id = $this->input->get('task_allocation_id');
                $latitude = $this->input->get('latitude') ? $this->input->get('latitude') : "0.00000000";
                $longitude = $this->input->get('longitude') ? $this->input->get('longitude') : "0.00000000";

                // get the task
                $aJobAllocation = $this->usertask_model->getEmpTaskAllocationByAllocationId($iEmpId, $allocation_id);                              
                
                if(count($aJobAllocation)){
                    // check my status
                    $aResponse['my_status'] = $aJobAllocation['status'];
                    
                    if(in_array($aJobAllocation['status'], array("A","PE"))){
                        //new task employee can start this                        
                        
                        if ($aJobAllocation['task_enddate'] >= date('Y-m-d')) {                            
                            //check if user has ablity to start this job within time
                            if(in_array($aJobAllocation['task_status'], array('A', 'PR'))){
                                $comment_by = $aJobAllocation['allocated_by'];
                                $iGroupId = $aJobAllocation['group_id'];
                                $bStatus = $this->usertask_model->startJob($iEmpId, $allocation_id, $comment_by,$iGroupId, $latitude, $longitude);
                                if ($bStatus) {
                                    $actionH = array(
                                        "action_log_id" => null,
                                        "action_by" => $iEmpId,
                                        "action_status" => '3', //volunteer start task
                                        "allocation_id" => $allocation_id,
                                        "in_description" => messageType('3'),
                                        "action_datetime" => date('Y-m-d H:i:s')
                                    );
                                    $this->usertask_model->actionHistory("action_log", $actionH); 
                                    $aResponse['message'] = "Job Started"; 
                                }else{
                                    // unable to start the job
                                    $aResponse['error_msg'] = "Unable to start the job";
                                }                                                               
                            }else if(in_array($aJobAllocation['task_status'], array('C'))){
                                // this job is completed                       
                                $bStatus = true;
                                $aResponse['message'] = "This job is already completed";                                
                            }else{
                                //'I', 'PE',  'PP', 'CN'
                                // this job is no longer available
                                $bStatus = false; 
                                $aResponse['error_msg'] = "This job is no longer available";
                            }                            
                        }else{
                            //timeline over
                            $bStatus = false; 
                            $aResponse['error_msg'] = "Timeline over";
                        }
                    }else if(in_array($aJobAllocation['status'], array("PR","C"))){
                        // already started or completed jobs                        
                        $bStatus = true;
                        $aResponse['message'] = "This job is already started or completed";
                    }else{
                        //I,MV,RV,CN,H
                        $bStatus = false;
                        $aResponse['error_msg'] = "This job is no longer available.";
                    }
                }else{
                    // no task found for this employee with this allocation id
                    $bStatus = false; 
                    $aResponse['error_msg'] = "Invalid request";
                }
                $aResponse['status'] = $bStatus ? "success": "failure";
                $this->renderJson($aResponse);
            } catch (Exception $e) {
                $aResponse['status'] = 'failure';
                $this->renderJson($aResponse);
            }
        } else {
            $aResponse['status'] = 'not allowed to access';
            $this->renderJson($aResponse);
        }
    }
    
    

    public function feedback() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $id = $this->input->get('id');
            
                if (0 == $id) {
                    throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($id);
                $allocation_id = $this->input->get('allocation_id');
                $group_id = $this->input->get('group_id');
                $feedback = base64_decode(urldecode($this->input->get('feedback')));
                $status = "";

                $data['employeeDetails'] = $this->user_model->getUserProfileDetails($id);
                $employeeStatus = $this->user_model->employeeStatus($id);

                if ($employeeStatus == "P") {
                    $data['employeeStatusMsg'] = "Account wait for approval";
                }
                $data['task_details'] = $this->usertask_model->getTaskDetailsByAllocationId($allocation_id, null, $id);
                if (!$data['task_details']) {
                    $status = "error";
                }
                if ($data['task_details']['task_status'] == 'PR' || $data['task_details']['task_status'] == 'C') {
                    $data['activity'] = $this->usertask_model->getTaskActivityByIde($data['task_details']['allocation_id'], null);
                    $data['task_details']['volunteer'] = $this->usertask_model->getVolunteer($data['task_details']['allocation_id']);

                    foreach ($data['task_details']['volunteer'] as $key => $val) {
                        if ($val['employee_id'] == $id) {
                            $data['is_leader'] = $val['is_leader'];
                            $data['group_name'] = $val['group_name'];
                            $data['description'] = $val['description'];
                        }
                    }
                    $data['group_id'] = $group_id;

                    $data['volunteerStatus'] = $this->usertask_model->checkVolunteerStatusAccordingToTask($allocation_id, $id);

                    $data['feedbackStatus'] = $this->report_model->getFeedback($allocation_id, $id);

                    /* if($this->input->post('submit')){ */

                    $taskDetails = $this->usertask_model->getTaskDetailsByAllocationId($allocation_id, null, $id);

                    $feedback_status = $this->report_model->getFeedback($allocation_id, $id);

                    /* $this->form_validation->set_rules('status', 'Complete status', 'trim|required');
                      $this->form_validation->set_rules('feedback', 'feedback & experience', 'trim|required');
                      if ($this->form_validation->run($this)) { */

                    $arr = array(
                            "feedback_id" => null,
                            "task_allocation_id" => $allocation_id,
                            "group_id" => $group_id,
                            "feedback_by" => $id,
                            "description" => str_replace("`", "'", $feedback),
                            "volunteer_usertype" => $data['employeeDetails']['usertype_id'],
                            "create_date" => date('Y-m-d H:i:s')
                    );

                    if (isset($feedback_status) && !empty($feedback_status)) {
                        $currentTime = time();
                        $createTime = $feedback_status['create_date'] != null ? strtotime($feedback_status['create_date']) : time();
                        $diff = round(abs($currentTime - $createTime) / 60);
                        if ($diff <= 1440) {
                            $status = $this->report_model->updateFeedback($feedback_status['feedback_id'], "allocation_feedback", $arr);
                            $this->usertask_model->updateVolunteerStatus($taskDetails['allocation_id'], $id, $group_id);
                        }
                    } else {
                        $status = $this->report_model->pushFeedback("allocation_feedback", $arr);
                        $this->usertask_model->updateVolunteerStatus($taskDetails['allocation_id'], $id, $group_id);
                    }

                    if ($status) {
                        /* action history log */
                        $actionH = array(
                                "action_log_id" => null,
                                "action_by" => $id,
                                "action_status" => '13',
                                "allocation_id" => $taskDetails['allocation_id'],
                                "in_description" => messageType('13'),
                                "action_datetime" => date('Y-m-d H:i:s')
                        );
                        $this->usertask_model->actionHistory("action_log", $actionH);
                    }
                    /* }
                      } */
                    if ($status) {
                        $status = "success";
                    } else {
                        $status = "failure";
                    }
                } else {
                    $status = $data['task_details']['task_status'];
                }
                $rval['status'] = $status;
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function completeJobStatus() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $allocation_id = $this->input->get('allocation_id');
                $id = $this->input->get('id');
                if (0 == $id) {
                throw new Exception("Invalid Emp Id");
                }
                $this->user_model->updateLastSeen($id);

                $row = $this->usertask_model->getTaskById($allocation_id, $id);
                $arr['timeline'] = "";

                if ($row['allocation_status'] == 'PR') {
                    $status = $this->usertask_model->completeJob($allocation_id, $id);
                    if ($status) {
                        /* action history log */
                        $actionH = array(
                                "action_log_id" => null,
                                "action_by" => $id,
                                "action_status" => '40', // close the job
                                "allocation_id" => $allocation_id,
                                "in_description" => messageType('7'),
                                "action_datetime" => date('Y-m-d H:i:s')
                        );
                        $this->common_model->actionHistory("action_log", $actionH);
                        $rval['status'] = 'success';
                    } else {
                        $rval['status'] = $status;
                    }
                } else {
                    $rval['status'] = 'Task not started yet.';
                }
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function supervisor_feedback() {

        //https://cvtask.xyz/json/give_feedback_rating_comment?id=184&rating=4.0&comment=good&task_allocation_id=1800&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

        try  {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $id = $this->input->get("id", TRUE);
            if (0 == $id) {
                throw new Exception("Invalid Emp Id");
            }
            $this->user_model->updateLastSeen($id);
            $rating =  $this->input->get("rating", TRUE);
            $comment = urldecode($this->input->get("comment", TRUE));
            $task_allocation_id=$this->input->get("allocation_id", TRUE);
            $allocation_feedback_details=$this->taskallocation_model->get_allocation_feedbackdetails($task_allocation_id);

            //pre($allocation_feedback_details);
            //die();

            $user = $this->user_model->userprofiledetails($id);

            $feedback_arr=array(
                    'task_allocation_id'=>$task_allocation_id,
                    'group_id'=>$allocation_feedback_details['group_id'],
                    'feedback_by'=>$id,
                    'volunteer_usertype'=>$allocation_feedback_details['volunteer_usertype'],
                    'response_by'=>$id,
                    'response_usertype'=>$user['usertype_id'],
                    'feedback_id'=>$allocation_feedback_details['feedback_id']
                    );

            $arr=array(
                    'task_allocation_id'=>$task_allocation_id,
                    'rating'=>$rating,
                    'response_by'=>$allocation_feedback_details['response_by'],
                    'response_usertype'=>$allocation_feedback_details['response_usertype'],
                    'response'=>$comment
                    );

            //$status["status"]=$this->taskallocation_model->updateCompleteTask($feedback_arr,$arr)> 0 ? "success" : "failure";

            if($this->taskallocation_model->updateCompleteTask($feedback_arr,$arr)) {
                $status["status"]='success' ;
            }
            else {
                $status["status"]='failure' ;
            }

            $this->renderJson(($status));

        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function task_active(){

        try  {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $id = $this->input->get("id",TRUE);
            if (0 == $id) {
                throw new Exception("Invalid Emp Id");
            }
            $this->user_model->updateLastSeen($id);
            $task_allocation_id = $this->input->get("task_allocation_id",TRUE);
            $arr = array();
            $row = $this->usertask_model->getTaskById($task_allocation_id,$id);
            $previous_status = $this->usertask_model->getPreviousAllocationStatus($task_allocation_id);
            $arr['timeline'] = "";
            if($row['task_enddate'] < date('Y-m-d')){
                $arr['timeline'] = "Timeline over";
            }
            if ($row['allocation_status'] == 'PR' || $row['allocation_status'] == 'PP' || $row['allocation_status'] == 'I' || $row['allocation_status'] == 'PE') {

                $status = $this->usertask_model->activeTasknew($task_allocation_id,$previous_status['allocation_status'],$id);
                if ($status) {

                    /* action history log */

                    $actionH = array(

                            "action_log_id" => null,

                            "action_by" => $id,

                            "action_status" => '10', //volunteer re-allocate

                            "allocation_id" => $task_allocation_id,

                            "in_description" => messageType('10'),

                            "action_datetime" => date('Y-m-d H:i:s')

                    );

                    $this->common_model->actionHistory("action_log", $actionH);



                    /**/

                    $arr['id'] = $task_allocation_id;

                    //$arr['status'] = '1';

                    $arr['status'] = $previous_status['allocation_status'];

                    //$arr['msg'] = statusName($previous_status['allocation_status'], true);

                    //$arr['html'] = "<i class='fa fa-close' title='postponed' onclick='ajaxPostponed(" . $allocation_id . ");'>&nbsp;Postponed</i><br>";

                } else {

                    $arr['id'] = $task_allocation_id;

                    $arr['status'] = '0';

                    $arr['msg'] = '';

                    $arr['html'] = "";

                }

            } else if ($row['allocation_status'] == 'A') {

                $arr['status'] = $row['allocation_status'];

                $arr['msg'] = " Already task is " . statusName($row['allocation_status'], true);

            } else {

                $arr['status'] = $row['allocation_status'];

                $arr['msg'] = "Task status is : " . statusName($row['allocation_status'], true);


            }
            $this->renderJson(($arr));
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function task_postponed(){

        try  {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $id = $this->input->get("id", TRUE);
            if (0 == $id) {
                throw new Exception("Invalid Emp Id");
            }
            $this->user_model->updateLastSeen($id);
            $task_allocation_id = $this->input->get("task_allocation_id", TRUE);
            
            $row = $this->usertask_model->getTaskById($task_allocation_id,$id );
            $arr['timeline'] = "";
            if($row['task_enddate'] < date('Y-m-d')) {
                $arr['timeline'] = "Timeline over";
            }
            if ($row['allocation_status'] == 'PR' || $row['allocation_status'] == 'A' || $row['allocation_status'] == 'I' || $row['allocation_status'] == 'PE') {
                $status = $this->usertask_model->postponedTask($task_allocation_id,$id);
                $this->usertask_model->taskStatusLog($task_allocation_id,$row['allocation_status']);
                if ($status) {
                    /* action history log */
                    $actionH = array(
                            "action_log_id" => null,
                            "action_by" => $id,
                            "action_status" => '7', // assigned task postponded
                            "allocation_id" => $task_allocation_id,
                            "in_description" => messageType('7'),
                            "action_datetime" => date('Y-m-d H:i:s')
                    );
                    $this->common_model->actionHistory("action_log", $actionH);
                    /**/
                    //$aVolunteersStatus = $this->usertask_model->getVolunteer($task_allocation_id);
                    // $aVolunteersStatus = $this->usertask_model->getVolunteer($task_allocation_id);
                    //pre($aVolunteersStatus);
                    //die();
                    //$arr['volunteers'] = $aVolunteersStatus;
                    $arr['id'] = $task_allocation_id;
                    //$arr['status'] = '1';
                    $arr['status'] = 'PP';
                    //$arr['msg'] = statusName('PP', true);
                    //$arr['html'] = "<i class='fa fa-check' title='active' onclick='ajaxActive(" . $allocation_id . ");'>&nbsp;Active</i><br>";
                } else {
                    $arr['id'] = $task_allocation_id;
                    $arr['status'] = '0';
                    $arr['msg'] = '';
                    $arr['html'] = "";
                }
            } else if ($row['allocation_status'] == 'PP') {
                $arr['status'] = $row['allocation_status'];
                $arr['msg'] = " Already task is " . statusName($row['allocation_status'], true);
            } else {
                $arr['status'] = $row['allocation_status'];
                $arr['msg'] = "Task status is : " . statusName($row['allocation_status'], true);
            }

//                } else { // date over

//                    $arr['status'] = '0';

//                    $arr['msg'] = "Date is crossed";

//                }

            // echo json_encode($arr);
            $this->renderJson(($arr));
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    /**
     * 
     * @throws Exception
     */
    public function attendance() {

        if ($this->signature($this->input->get("signature"))) {
            try {
                $iEmpId = (int) $this->input->get("id");
                $sStatus = $this->input->get("status");

                $this->user_model->updateLastSeen($iEmpId);

                if (0 == $iEmpId) {
                    throw new Exception("Invalid Emp Id");
                }

                $sLat = $this->input->get("lat", TRUE);
                $sLng = $this->input->get("lng", TRUE);
                $sAddress = $this->input->get("address", TRUE);
                $sIP = $this->input->ip_address();

                switch ($sStatus) {
                    case "submit":
                        if ($this->attendance_model->attendance($iEmpId, $sLat, $sLng, $sIP, $sAddress)) {
                            $rval['status'] = 'success';
                        } else {
                            $rval['status'] = 'submit';
                        }
                        break;
                        
                        #Commented on 2017-07-04
//                    case "leave":
//                        if ($this->attendance_model->leave($iEmpId, $sLat, $sLng, $sIP, $sAddress)) {
//                            $rval['status'] = 'success';
//                        } else {
//                            $rval['status'] = 'leave';
//                        }
//                        break;
                        #End commented on 2017-07-04
                        
                    case "check":
                    default :
                        $bAttendance = $this->attendance_model->getAttendance(date("Y-m-d"), $iEmpId);
//                        var_dump($bAttendance);exit;
                        $rval['status'] = $bAttendance ? 'success' : 'submit';
                        
                        #Commented on 2017-07-04
                        
//                        if (false === $aAttendance) {
//                            $rval['status'] = 'submit';
//                        } elseif (is_array($aAttendance) && empty($aAttendance['end_time'])) {
//                            $rval['status'] = 'leave';
//                        } else if (is_array($aAttendance)) {
//                            $rval['status'] = 'success';
//                        } else {
//                            $rval['status'] = 'submit';
//                        }
                        # End Commented on 2017-07-04
                    break;
                }
            } catch (Exception $e) {
                $rval['status'] = 'not allowed to access';
                $rval['ex_status'] = $e->getMessage();
                $this->renderJson($rval);
            }
            $this->renderJson($rval);
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function myattendance_register() {

        try {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", TRUE);
            $iPage = (int) $this->input->get("page", TRUE);
            $iMonth = (int) $this->input->get("month", TRUE);
            $iYear = (int) $this->input->get("year", TRUE);
            $iMonth = ( $iMonth==0 )? date('m'): $iMonth;
            $iYear = ( $iYear==0 )? date('Y'): $iYear;

            $this->user_model->updateLastSeen($iEmpId);

            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $aAtdRs = $this->attendance_model->getAttendanceRegisterByEmployee($iEmpId, $iMonth, $iYear);

            $number = cal_days_in_month(CAL_GREGORIAN, $iMonth, $iYear);

            if((date('m') == $iMonth) && (date('Y') == $iYear)){
                $number = date('d');
            }

            for($i = (int)$number; $i > 0 ; $i--){
                $aMyAtdR[$i] = array(
                        'start_day' => $i,
                        'start_time' => 0,
                        'start_lat' => 0,
                        'start_lng' => 0,
                        'start_address' => '',
                        'end_time' => 0,
                        'end_lat' => 0,
                        'end_lng' => 0,
                        'end_address' => ''
                    );
            }
            // replace with original
            foreach($aAtdRs as $aAtdR){
                $aMyAtdR[(int)$aAtdR['start_day']] = $aAtdR;
            }

            $aResp = array(
                    'myAtdR' => array_values($aMyAtdR)          
            );

            $aResp['employee_id'] = $iEmpId;
            $aResp['current_page'] = $iPage;
            $aResp['current_month'] = $iMonth;
            $aResp['current_year'] = $iYear;
            $aResp['status'] = count($aAtdRs) != 0 ? 'success' : 'No Records Found.';
            $aResp['error'] = 0;
            $this->renderJson($aResp);

        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    /*public function checkAttendanceSupervisor() {

        //localhost:8088/ttms/json/checkAttendanceSupervisor?id=1&month=01&year=2017&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $supid=$this->input->get("id", TRUE);

            //$this->user_model->updateLastSeen($supid);
            $iMonth = (int) $this->input->get("month", TRUE);
            $iYear = (int) $this->input->get("year", TRUE);
            $iMonth = (int) ( $iMonth==0 )? date('m'): $iMonth;
            $iYear = (int) ( $iYear==0 )? date('Y'): $iYear;

            //$data = $this->data;
            $employeedetails=$this->user_model->getEmployeeDetails($supid);
            $id=$employeedetails['usertype_id'];
            $supid=$employeedetails['supervisor'];
            $district_id=$employeedetails['emp_district'];
            $section_id=$employeedetails['section_id'];
            $sAccessStationIds = $employeedetails['access_stations'];

            //echo $id . "\n" . $supid . "\n" . $district_id . "\n" . $section_id . "\n" . $sAccessStationIds . "\n";

            $aStations = explode(',', $sAccessStationIds);

            $result = $this->attendance_model->getEmployees($id,$supid,$district_id ,$section_id,$sAccessStationIds, $searchdata = null); // get data            
            
            $staffs = $result['countCVF'];
            $empid=$employeedetails['id'];
            $usertype_id=$employeedetails['usertype_id'];
            $supervisor=$employeedetails['supervisor'];
            $distid=$employeedetails['emp_district'];
            $section_id=$employeedetails['section_id'];
            //$report = $this->attendance_model->getAttendanceReport($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations=array(), $iMonth == 1 ? 12 : $iMonth - 1, $iMonth == 1 ? $iYear - 1 : $iYear);
            //$aCurrentMonth = $this->attendance_model->getAttendanceReport($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations=array(), $iMonth,$iYear);
            //pre($aStations);
            $report = $this->attendance_model->getAttendanceReport2($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations, $iMonth == 1 ? 12 : $iMonth - 1, $iMonth == 1 ? $iYear - 1 : $iYear);
            //pre($report);
            $aCurrentMonth = $this->attendance_model->getAttendanceReport2($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations, $iMonth,$iYear);
            if( (!empty($aCurrentMonth))&&(!empty($report)))
                $report = array_merge($report,$aCurrentMonth);
                if (empty($report)) {
                    $report=$aCurrentMonth;
                }
                $count = count($report);
                $slno = 1;
                $attendance=array();
                $attendance_array=array();
                $date=array();
                $present=array();
                if(!empty($report)) {
                foreach($report as $key=>$val) {
                    $date = str_replace("-",",",$val['date']);
                    $date=array('date'=>$val['date']);
                    $present=array('present'=>$val['present']);
                    $attendance_array=array_merge($date,$present);
                    array_push($attendance,$attendance_array);
                    $slno++;
                }
            }
           else {
                $attendance=null;
                $attendance_array=null;
                //array_push($attendance,$attendance_array);
            }

            $data['attendance'] = $attendance;
            $data['total'] = $staffs;
            $data['status'] = "success";
            $this->renderJson($data);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }*/
    
    public function checkAttendanceSupervisor() {

        //localhost:8088/ttms/json/checkAttendanceSupervisor?id=1&month=01&year=2017&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        //https://cvtask.xyz/json/checkAttendanceSupervisortest.html?id=469&month=4&year=2017&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            //echo 'Hello';
            $supid=$this->input->get("id", TRUE);
            $sup_watcher=$supid;

            if (0 == $supid) {
                throw new Exception("Invalid Emp Id");
            }
            
            $this->user_model->updateLastSeen($supid);
            $iMonth = (int) $this->input->get("month", TRUE);
            $iYear = (int) $this->input->get("year", TRUE);
            $iMonth = (int) ( $iMonth==0 )? date('m'): $iMonth;
            $iYear = (int) ( $iYear==0 )? date('Y'): $iYear;

            //$data = $this->data;
            $employeedetails=$this->user_model->getEmployeeDetails($supid);
            $id=$employeedetails['usertype_id'];
            $supid=$employeedetails['supervisor'];
            $district_id=$employeedetails['emp_district'];
            $section_id=$employeedetails['section_id'];
            $sAccessStationIds = $employeedetails['access_stations'];

            //echo $id . "\n" . $supid . "\n" . $district_id . "\n" . $section_id . "\n" . $sAccessStationIds . "\n";

            $aStations = explode(',', $sAccessStationIds);
            
            //previously active
            //$result = $this->attendance_model->getEmployees($id,$supid,$district_id ,$section_id,$sAccessStationIds, $searchdata = null); // get data            
            //previously active
            //previously later active
            //$result = $this->attendance_model->getEmployees2($id,$supid,$district_id ,$section_id,$sAccessStationIds, $searchdata = null); // get data 
            
            //previously later active
            $staffs =$this->attendance_model->get_no_of_staffs($id,$supid,$district_id ,$section_id,$sAccessStationIds, $searchdata = null);
            //echo $sup_watcher;
            $staffs_watcher=$this->attendance_model->get_no_of_staffs_for_watcher($sup_watcher);
            
            //echo $staffs.'  '.$staffs_watcher;
            
            //$staffs = $result['countCVF'];
            
            //if(($staffs_watcher<=$staffs)&&($staffs_watcher!=0)){
            if($id == "17") {
             
                //echo 'hello1';
                $report = $this->attendance_model->getCVAttendanceReportByParent($sup_watcher,$iMonth == 1 ? 12 : $iMonth - 1, $iMonth == 1 ? $iYear - 1 : $iYear);
                $aCurrentMonth =$this->attendance_model->getCVAttendanceReportByParent($sup_watcher, $iMonth,$iYear);
               
                //pre($report);
                //pre($aCurrentMonth);
                
                if( (!empty($aCurrentMonth))&&(!empty($report)))
                $report = array_merge($report,$aCurrentMonth);
                if (empty($report)) {
                    $report=$aCurrentMonth;
                }
                $data['attendance'] = $report;
                $data['total'] = $staffs_watcher;
                $data['status'] = "success";  
            } else {
                //echo 'hello2';
                $empid=$employeedetails['id'];
                $usertype_id=$employeedetails['usertype_id'];
                $supervisor=$employeedetails['supervisor'];
                $distid=$employeedetails['emp_district'];
                $section_id=$employeedetails['section_id'];
                //$report = $this->attendance_model->getAttendanceReport($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations=array(), $iMonth == 1 ? 12 : $iMonth - 1, $iMonth == 1 ? $iYear - 1 : $iYear);
                //$aCurrentMonth = $this->attendance_model->getAttendanceReport($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations=array(), $iMonth,$iYear);
                //pre($aStations);
                $report = $this->attendance_model->getAttendanceReport2($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations, $iMonth == 1 ? 12 : $iMonth - 1, $iMonth == 1 ? $iYear - 1 : $iYear);
                //pre($report);
                $aCurrentMonth = $this->attendance_model->getAttendanceReport2($empid,$usertype_id,$supervisor,$distid,$section_id,$aStations, $iMonth,$iYear);
                if( (!empty($aCurrentMonth))&&(!empty($report)))
                    $report = array_merge($report,$aCurrentMonth);
                    if (empty($report)) {
                    $report=$aCurrentMonth;
                        
                }
                $count = count($report);
                $slno = 1;
                $attendance=array();
                $attendance_array=array();
                $date=array();
                $present=array();
                $data['attendance'] = $report;
                $data['total'] = $staffs;
                $data['status'] = "success";
            }
            //previously active
            /*if(!empty($report))
            {
            foreach($report as $key=>$val) {
                $date = str_replace("-",",",$val['date']);
                $date=array('date'=>$val['date']);
                $present=array('present'=>$val['present']);
                $attendance_array=array_merge($date,$present);
                array_push($attendance,$attendance_array);
                $slno++;
            }
            }
          else {
                $attendance=null;
                $attendance_array=null;
                //array_push($attendance,$attendance_array);
            }
            

            $data['attendance'] = $attendance;*/
            //previously acive
           
            
            $this->renderJson($data);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    // public function checkAttendanceSupByDate() {

    //     //json link
    //     //http://localhost:8088/ttms/json/CheckAttendanceSupByDatetest?id=1&date=2017-01-17&searchdata=9038643976&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
    //     //https://cvtask.xyz/json/checkAttendanceSupByDate.html?id=2&date=2017-04-03&searchdata=&page=0&per_page=20&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

    //     try {

    //         if (!$this->signature($this->input->get("signature"))) {
    //             throw new Exception('not allowed to access');
    //         }

    //         $date=$this->input->get("date", TRUE);
    //         $supid=$this->input->get("id", TRUE);
    //         $searchdata=strtolower(urldecode($this->input->get("searchdata", TRUE)));
            

    //         $this->user_model->updateLastSeen($supid);

    //         $iPage = (int) $this->input->get("page");
    //         $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;

    //         if ($this->endsWith($searchdata, "pre") || $this->endsWith($searchdata, "pres") || $this->endsWith($searchdata, "prese") || $this->endsWith($searchdata, "presen") || $this->endsWith($searchdata, "present")) {
    //             $pos_of = "";
    //             if ($this->endsWith($searchdata, "pr"))
    //                 $pos_of = "pr";
    //              if ($this->endsWith($searchdata, "pre"))
    //                 $pos_of = "pre";
    //              if ($this->endsWith($searchdata, "pres"))
    //                 $pos_of = "pres";
    //              if ($this->endsWith($searchdata, "prese"))
    //                 $pos_of = "prese";
    //              if ($this->endsWith($searchdata, "presen"))
    //                 $pos_of = "presen";
    //              if ($this->endsWith($searchdata, "present"))
    //                 $pos_of = "present";
    //             if (!empty($pos_of)) {
    //                 $sp_pos = strrpos($searchdata, $pos_of);
    //                 $sub_search = substr($searchdata, 0, $sp_pos);
    //                 $searchdata=$sub_search."present";
    //             }
    //         }
    //         else if ($this->endsWith($searchdata, "abs") || $this->endsWith($searchdata, "abse") || $this->endsWith($searchdata, "absen") || $this->endsWith($searchdata, "absent")) {
    //             $pos_of = "";
    //             if ($this->endsWith($searchdata, "abs"))
    //                 $pos_of = "abs";
    //              if ($this->endsWith($searchdata, "abse"))
    //                 $pos_of = "abse";
    //              if ($this->endsWith($searchdata, "absen"))
    //                 $pos_of = "absen";
    //           if ($this->endsWith($searchdata, "absent"))
    //                 $pos_of = "absent";
    //             if (!empty($pos_of)) {
    //                 $sp_pos = strrpos($searchdata, $pos_of);
    //                 $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
    //                 $searchdata=$sub_search."absent";
    //             }
    //         }
    //         /*$sp_pos = strrpos($searchdata, " ");
    //         $sub_search = substr($searchdata, $sp_pos + 1);
    //         if ($sub_search == "p" || $sub_search == "pr" || $sub_search == "pre" || $sub_search == "pres" || $sub_search == "prese" || $sub_search == "presen" || $sub_search == "present") {
    //             $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
    //             $searchdata=$sub_search."present";
    //         }
    //         if ($sub_search == "a" || $sub_search == "ab" || $sub_search == "abs" || $sub_search == "abse" || $sub_search == "absen" || $sub_search == "absent") {
    //             $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
    //             $searchdata=$sub_search."absent";
    //         }*/
    //         $searchdata=str_replace(' ','',$searchdata);
    //         $data_total=array();
            
    //         $employeedetails=$this->user_model->getUserProfileDetails($supid);
    //         $usertype_id=$employeedetails['usertype_id'];
    //         $supervisor=$employeedetails['supervisor'];
    //         $emp_district=$employeedetails['emp_district'];
    //         $section_id=$employeedetails['section_id'];

    //         $sAccessStationIds=$employeedetails['access_stations'];

    //         $aStations = explode(',', $sAccessStationIds);
            
    //         $data = $this->attendance_model->getAttendanceRegisterByDays($date, $usertype_id, $supervisor, $emp_district, $section_id, $aStations, $searchdata, $iPerPage, $iPage * $iPerPage);
    //         //$total=$this->attendance_model->getAttendanceRegisterByDaysCount($date,$usertype_id,$supervisor,$emp_district,$section_id,$aStations,$searchdata);
    //         $total = count($data);

    //         $data_res = array();
    //         $data_res = array_values($data);
            
    //         $response = array();

    //         $demo = array_values($data_res);
    //         $s = 0;
    //         $response['aAttendenceRegisterData'] = array();
    //         for ($i = $iPage * $iPerPage; $i < $iPage * $iPerPage + 20 && $i < count($demo); $i++) {
    //             $response['aAttendenceRegisterData'][$s] = $demo[$i];
    //             $s++;
    //         }
    //         $response['total'] = $total;
    //         $response['status'] = "success";
    //         $this->renderJson($response);
    //     } catch (Exception $ex) {
    //         $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
    //     }
    // }
    
    public function checkAttendanceSupByDate() {
        
        //json link
        //http://localhost:8088/ttms/json/CheckAttendanceSupByDatetest?id=1&date=2017-01-17&searchdata=9038643976&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        //https://cvtask.xyz/json/checkAttendanceSupByDatetest.html?id=2&date=2017-04-03&searchdata=&page=0&per_page=20&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        
        try {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
           
            $date=$this->input->get("date", TRUE);
            $supid=$this->input->get("id", TRUE);
            $sup_watcher=$supid;
            $searchdata=strtolower(urldecode($this->input->get("searchdata", TRUE)));
            
            //echo 'first  '.$searchdata;

            $this->user_model->updateLastSeen($supid);

            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;

            if ($this->endsWith($searchdata, "pre") || $this->endsWith($searchdata, "pres") || $this->endsWith($searchdata, "prese") || $this->endsWith($searchdata, "presen") || $this->endsWith($searchdata, "present")) {
                $pos_of = "";
                if ($this->endsWith($searchdata, "pr"))
                    $pos_of = "pr";
                if ($this->endsWith($searchdata, "pre"))
                    $pos_of = "pre";
                if ($this->endsWith($searchdata, "pres"))
                    $pos_of = "pres";
                if ($this->endsWith($searchdata, "prese"))
                    $pos_of = "prese";
                if ($this->endsWith($searchdata, "presen"))
                    $pos_of = "presen";
                if ($this->endsWith($searchdata, "present"))
                    $pos_of = "present";
                if (!empty($pos_of)) {
                    $sp_pos = strrpos($searchdata, $pos_of);
                    $sub_search = substr($searchdata, 0, $sp_pos);
                    $searchdata=$sub_search."present";
                }
                //echo 'second  '.$searchdata;
            }
            else if ($this->endsWith($searchdata, "abs") || $this->endsWith($searchdata, "abse") || $this->endsWith($searchdata, "absen") || $this->endsWith($searchdata, "absent")) {
                $pos_of = "";
                if ($this->endsWith($searchdata, "abs"))
                    $pos_of = "abs";
                if ($this->endsWith($searchdata, "abse"))
                    $pos_of = "abse";
                if ($this->endsWith($searchdata, "absen"))
                    $pos_of = "absen";
                if ($this->endsWith($searchdata, "absent"))
                    $pos_of = "absent";
                if (!empty($pos_of)) {
                    $sp_pos = strrpos($searchdata, $pos_of);
                    $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
                    $searchdata=$sub_search."absent";
                }
                //echo 'third  '.$searchdata;
            }
            $sp_pos = strrpos($searchdata, " ");
            $sub_search = substr($searchdata, $sp_pos + 1);
            if ($sub_search == "p" || $sub_search == "pr" || $sub_search == "pre" || $sub_search == "pres" || $sub_search == "prese" || $sub_search == "presen" || $sub_search == "present") {
                $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
                $searchdata=$sub_search."present";
            }
            if ($sub_search == "a" || $sub_search == "ab" || $sub_search == "abs" || $sub_search == "abse" || $sub_search == "absen" || $sub_search == "absent") {
                $sub_search = strtolower(substr($searchdata, 0, $sp_pos));
                $searchdata=$sub_search."absent";
            }
            //echo 'fourth  '.$searchdata;
            $searchdata=str_replace(' ','',$searchdata);
            // echo 'fifth  '.$searchdata;
            $data_total=array();
            //newly added
            $employeedetails=$this->user_model->getEmployeeDetails($supid);
            $id=$employeedetails['usertype_id'];
            $supid=$employeedetails['supervisor'];
            $district_id=$employeedetails['emp_district'];
            $section_id=$employeedetails['section_id'];
            $sAccessStationIds = $employeedetails['access_stations'];

            //echo $id . "\n" . $supid . "\n" . $district_id . "\n" . $section_id . "\n" . $sAccessStationIds . "\n";

            $aStations = explode(',', $sAccessStationIds);


            $staffs =$this->attendance_model->get_no_of_staffs($id,$supid,$district_id ,$section_id,$sAccessStationIds);
            // echo $sup_watcher;
            // echo 'sixth  '.$searchdata;
            $staffs_watcher=$this->attendance_model->get_no_of_staffs_for_watcher($sup_watcher);
            
            // newly added
            
            // echo 'Staffs Are    '.$staffs.'Watcher Staffs Are    '.$staffs_watcher;
            if (($staffs_watcher<$staffs)&&($staffs_watcher!=0)) {
                $employeedetails=$this->user_model->getUserProfileDetails($sup_watcher);
                $usertype_id=$employeedetails['usertype_id'];
                $supervisor=$employeedetails['supervisor'];
                $emp_district=$employeedetails['emp_district'];
                $section_id=$employeedetails['section_id'];
    
                $sAccessStationIds=$employeedetails['access_stations'];
    
                $aStations = explode(',', $sAccessStationIds);
                //previously active
                //echo 'In controller '.$searchdata;
                $data = $this->attendance_model->getAttendanceRegisterByDays_for_watcher($date, $usertype_id, $supervisor, $emp_district, $section_id, $aStations, $searchdata,$sup_watcher, $iPerPage, $iPage * $iPerPage);
                //previously active 
                
                //$total=$this->attendance_model->getAttendanceRegisterByDaysCount($date,$usertype_id,$supervisor,$emp_district,$section_id,$aStations,$searchdata);
                $total = count($data);
                    
                // echo 'in controller  '.$total;
            } else {
                $employeedetails=$this->user_model->getUserProfileDetails($supid);
                if(!empty($employeedetails)) {
                    $usertype_id=$employeedetails['usertype_id'];
                    $supervisor=$employeedetails['supervisor'];
                    $emp_district=$employeedetails['emp_district'];
                    $section_id=$employeedetails['section_id'];
        
                    $sAccessStationIds=$employeedetails['access_stations'];
        
                    $aStations = explode(',', $sAccessStationIds);
                    
                    $data = $this->attendance_model->getAttendanceRegisterByDays($date, $usertype_id, $supervisor, $emp_district, $section_id, $aStations, $searchdata, $iPerPage, $iPage * $iPerPage);
                    //$total=$this->attendance_model->getAttendanceRegisterByDaysCount($date,$usertype_id,$supervisor,$emp_district,$section_id,$aStations,$searchdata);
                    $total = count($data);
                } else {
                    //echo 'OC Entered';
                    $employeedetails=$this->user_model->getUserProfileDetails($sup_watcher);
                    $usertype_id=$employeedetails['usertype_id'];
                    $supervisor=$employeedetails['supervisor'];
                    $emp_district=$employeedetails['emp_district'];
                    $section_id=$employeedetails['section_id'];
        
                    $sAccessStationIds=$employeedetails['access_stations'];
        
                    $aStations = explode(',', $sAccessStationIds);
                    
                    $data = $this->attendance_model->getAttendanceRegisterByDays($date, $usertype_id, $supervisor, $emp_district, $section_id, $aStations, $searchdata, $iPerPage, $iPage * $iPerPage);
                        
                    $total = count($data);
                }
            }
            $data_res = array();
            $data_res = array_values($data);
            
            $response = array();

            $demo = array_values($data_res);
            $s = 0;
            $response['aAttendenceRegisterData'] = array();
            for ($i = $iPage * $iPerPage; $i < $iPage * $iPerPage + 20 && $i < count($demo); $i++) {
                $response['aAttendenceRegisterData'][$s] = $demo[$i];
                $s++;
            }
            $response['total'] = $total;
            $response['status'] = "success";
            $this->renderJson($response);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    /*
      entity.addPart("image", new FileBody(sourceFile));
      entity.addPart("id", new StringBody(id));
      entity.addPart("lp_type", new StringBody(spinner_prediction_type.getSelectedItem().toString()));
      entity.addPart("lp_description", new StringBody(lp_edit_text.getText().toString()));
      entity.addPart("lp_image_url", new StringBody(Base.localpredictionimageUrl + image_name));
      entity.addPart("lp_from_date", new StringBody(s_lp_selected_date.isEmpty() ? s_lp_from_date : s_lp_from_date));
      entity.addPart("lp_to_date", new StringBody(s_lp_to_date));
      entity.addPart("signature", new StringBody(SIGNATURE));
     */
    /* public function localprediction() {


      try {

      $get_dump = print_r($_GET, TRUE);
      file_put_contents('request.log', "-------------- GET -------------- ", FILE_APPEND);
      file_put_contents('request.log', $get_dump, FILE_APPEND);

      $post_dump = print_r($_POST, TRUE);
      file_put_contents('request.log', "-------------- POST -------------- ", FILE_APPEND);
      file_put_contents('request.log', $post_dump, FILE_APPEND);

      $post_dump = print_r($_FILES, TRUE);
      file_put_contents('request.log', "-------------- FILES -------------- ", FILE_APPEND);
      file_put_contents('request.log', $post_dump, FILE_APPEND);


      if (!$this->signature($this->input->get("signature"))) {
      throw new Exception('not allowed to access');
      }

      if(isset($_FILES['image'])){
      $sUploadedFileName = $this->fileUpload(false);

      if (FALSE == $sUploadedFileName) {
      throw new Exception("Cannot able to upload file");
      }
      }


      $iEmpId = (int) $this->input->get("id", 0);
      $iLPType = (int) $this->input->get("lp_type");
      $sLPDesc = $this->input->get("lp_description");
      $sLPImageUrl = $this->input->get("lp_image_url");
      $sLPFormDate = $this->input->get("lp_from_date");
      $sLPToDate = $this->input->get("lp_to_date");



      if (0 == $iEmpId) {
      throw new Exception("Invalid Emp Id");
      }
      } catch (Exception $exc) {
      $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
      //echo $exc->getTraceAsString();
      }
    }*/

    public function fileUploadLP($bEcho=true) {
        try {
            $config = array(
                    'upload_path' => "./assets/uploads/localprediction/",
                    'file_name' => $_FILES['image']['name'],
                    'allowed_types' => "gif|jpg|png|jpeg",
            );

            $this->load->library('upload', $config);

            if ($this->upload->do_upload("image")) {
                $image = $this->upload->data();
                echo basename($_FILES['image']['name']);
            } else {
                echo $this->upload->display_errors();
            }
            
        } catch (Exception $e) {
            if ($bEcho) {
                die('File did not upload: ' . $e->getMessage());
            } else {
                return false;
            }
        }
    }

    public function get_lp_reason() {
        if ($this->signature($this->input->get("signature"))) {
            try {
                $rval['lp_reason'] = $this->localprediction_model->getLocalPredictionsType();

                $rval['status'] = 'success';
                $this->renderJson($rval);
            } catch (Exception $e) {
                $rval['status'] = 'failure';
                $this->renderJson($rval);
            }
        } else {
            $rval['status'] = 'not allowed to access';
            $this->renderJson($rval);
        }
    }

    public function prepareLP() {

        //http://localhost/ttms/json/prepareLP.html?&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y

        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            
            $iVersionCode = (int) $this->input->get("version_code");

            $rval['pslist'] = $this->news_model->getPolicestations();
            $rval['lp_reason'] = $this->localprediction_model->getLocalPredictionsType();
            $rval['lp_time'] = $this->localprediction_model->getLocalPredictionsTime();
            
            if($iVersionCode > 12){
                
                $aLPReasonArray = array();
                $aLPTimeArray = array();
                
                foreach( $rval['lp_reason'] as $iKey => $sVal){                    
                    $aLPReasonArray[$iKey] = array(
                        'reason_id' => $iKey,
                        'reason_name' => $sVal
                    );
                }
                
                $aLPReasonArray[0] = array(
                    'reason_id' => -1,
                    'reason_name' => 'Select Type'
                );
                // id:0, name: others
                $aLPReasonArray[] = array(
                    'reason_id' => 0,
                    'reason_name' => $rval['lp_reason'][0]
                );
                
                $rval['lp_reason'] = $aLPReasonArray;
                
                foreach( $rval['lp_time'] as $iKey => $sVal){                    
                    $aLPTimeArray[$iKey] = array(
                        'time_id' => $iKey,
                        'time_data' => $sVal
                    );
                }
                $rval['lp_time'] = $aLPTimeArray;
                
            }
            
            
            
            $rval['status'] = 'success';
            $rval['error'] = 0;

            $this->renderJson($rval);
        } catch (Exception $exc){
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function localprediction() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            $sLPType = $this->input->get("prediction_type");
            $sLPReason = $this->input->get("lp_reason");
            $sLPDesc = $this->input->get("description");
            $sLPImageUrl = $this->input->get("image_url");
            $sLPFormDate = $this->input->get("from_date");
            $sLPToDate = $this->input->get("to_date");
            $iTimeid = $this->input->get("time");
            $psDist = urldecode($this->input->get("ps", TRUE));
            $sPoliceStation = explode(",", $psDist);
            $iPsid = $this->news_model->getPsByNameDistrict(trim($sPoliceStation[0]),trim($sPoliceStation[1]));
            $sLocation = urldecode($this->input->get("location"));
            $address_name=urldecode($this->input->get("address_name"));
            $latitude=$this->input->get('latitude');
            $longitude=$this->input->get('longitude');

			if($sLPType == 0 AND $sLPReason == ''){
                throw new Exception("Please select prediction type");
            }else if(empty($sLPDesc)){
                throw new Exception("Please provide prediction description");
            }
			
            $fromdate = $sLPFormDate;
            $todate = $sLPToDate;

            if (0 != (int) $sLPType) {
                $getPredictionDetails = $this->localprediction_model->getPredictionDetails($sLPType);
                $localprediction_reason_id = $sLPType;
                $slug = slugify($getPredictionDetails['reason_name'], 'local_prediction');
                $localprediction_reason = '';
            } else {
                $localprediction_reason_id = (int) $sLPType;
                $slug = slugify($sLPReason, 'local_prediction');
                $localprediction_reason = $sLPReason;
            }
            $parent_id = $this->user_model->getUserProfileDetails($iEmpId)["parent_id"];
            $arr = array(
                    "localprediction_reason_id" => $localprediction_reason_id,
                    "localprediction_reason" => $localprediction_reason,
                    "comment" => $sLPDesc,
                    "slug" => $slug,
                    "predicition_by" => $iEmpId,
                    "prediction_to" => $parent_id,
                    "image" => $sLPImageUrl,
                    "predicition_time" => date("Y-m-d H:i:s"),
                    "is_delete" => "N",
                    "prediction_start_date" => $fromdate,
                    "prediction_end_date" => $todate,
                    "prediction_ps" => $iPsid,
                    "prediction_landmark" => $sLocation,
                    "prediction_time_id" => $iTimeid,
                    "prediction_address_name" => $address_name,
                    "prediction_latitude" => $latitude,
                    "prediction_longitude" => $longitude
            );

            $status = $this->localprediction_model->pushData("local_prediction", $arr);


            if ($status) {

                $sendmessage = " Added new prediction ";
                $fromto = $iEmpId;
                $sendto = null;
                $notification_type = TO_ADMIN_LP;
                $message = json_encode(array("type" => $notification_type, "message" => $sendmessage, "allocation_id" => '', "task_id" => '', "task_title" => ''));

                $this->globalnotification_model->adminSendNotification($fromto, $sendto, $sendmessage, $message, $notification_type, $is_admin = '0');
                $status = array('status' => 'success', 'insertid' => $status);
            } else {
                $status = array('status' => 'failure', 'insertid' => '');
            }

            $this->renderJson($status);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function mylplist() {

        try {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($iEmpId);

            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;

            $aMyLPList = $this->localprediction_model->getMyLocalPrediction("N", $iEmpId, $iPerPage, $iPage);

            foreach ($aMyLPList as $key => $aMyLP) {
                $aMyLPList[$key]['predicition_time']        = strtotime($aMyLP['predicition_time']);
                $aMyLPList[$key]['justify_time']            = strtotime($aMyLP['justify_time']);
                $aMyLPList[$key]['prediction_start_date']   = strtotime($aMyLP['prediction_start_date']);
                $aMyLPList[$key]['prediction_end_date']     = strtotime($aMyLP['prediction_end_date']);
                $aMyLPList[$key]['created_date']            = strtotime($aMyLP['created_date']);
                $aMyLPList[$key]['modified_date']           = strtotime($aMyLP['modified_date']);
                $aMyLPList[$key]['localprediction_reason']  = urlencode($aMyLP['localprediction_reason']);
                $aMyLPList[$key]['comment']                 = urlencode($aMyLP['comment']);
                $aMyLPList[$key]['justify_comment']         = urlencode($aMyLP['justify_comment']);
                $aMyLPList[$key]['lp_time_id']          = $aMyLP['lp_time_id'] ? $aMyLP['lp_time_id'] : "0";
                $aMyLPList[$key]['lp_time_title_en']        = $aMyLP['lp_time_id'] ? $aMyLP['lp_time_title_en'] : "Any time";
                $aMyLPList[$key]['lp_time_title_bn']        = $aMyLP['lp_time_id'] ? $aMyLP['lp_time_title_bn'] : "Any time";
                $aMyLPList[$key]['lp_time_start']       = $aMyLP['lp_time_id'] ? $aMyLP['lp_time_start'] : "";
                $aMyLPList[$key]['lp_time_end']         = $aMyLP['lp_time_id'] ? $aMyLP['lp_time_end'] : "";
            }

            $aResp['employee_id'] = $iEmpId;
            $aResp['current_page'] = $iPage;
            $aResp['total'] = $config["total_rows"] = $this->localprediction_model->getMyLocalPredictionCount("N", $iEmpId);
            $aResp['myLPs'] = $aMyLPList;
            $aResp['status'] = 'success';
            $aResp['error'] = 0;


            $this->renderJson($aResp);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    /**
     * generate local prediction list for supervisor
     * @param string signature the signature token
     * @param int id the employee id
     * @return string json encoded string of output
     * @throws Exception
     */
    public function suplplist() {
        try {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($iEmpId);

            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $employeeDetails = $this->user_model->getUserProfileDetails($iEmpId);

            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;

            $aSupLPList = $this->localprediction_model->getLocalPrediction("N", $iEmpId, $employeeDetails['usertype_id'], $employeeDetails['supervisor'], $employeeDetails['emp_district'], $employeeDetails['section_id'], $employeeDetails['access_stations'] , $iPerPage, $iPerPage*$iPage);

            foreach ($aSupLPList as $key => $aSupLP) {
                unset($aSupLPList[$key]['predicition_by']);
                $aSupLPList[$key]['predicition_time']        = strtotime($aSupLP['predicition_time']);
                $aSupLPList[$key]['justify_time']            = strtotime($aSupLP['justify_time']);
                $aSupLPList[$key]['prediction_start_date']   = strtotime($aSupLP['prediction_start_date']);
                $aSupLPList[$key]['prediction_end_date']     = strtotime($aSupLP['prediction_end_date']);
                $aSupLPList[$key]['created_date']            = strtotime($aSupLP['created_date']);
                $aSupLPList[$key]['modified_date']           = strtotime($aSupLP['modified_date']);
                $aSupLPList[$key]['localprediction_reason']  = urlencode($aSupLP['localprediction_reason']);
                $aSupLPList[$key]['comment']                 = urlencode($aSupLP['comment']);
                $aSupLPList[$key]['justify_comment']         = urlencode($aSupLP['justify_comment']);
                $aSupLPList[$key]['lp_time_id']          = $aSupLP['lp_time_id'] ? $aSupLP['lp_time_id'] : "0";
                $aSupLPList[$key]['lp_time_title_en']        = $aSupLP['lp_time_id'] ? $aSupLP['lp_time_title_en'] : "Any time";
                $aSupLPList[$key]['lp_time_title_bn']        = $aSupLP['lp_time_id'] ? $aSupLP['lp_time_title_bn'] : "Any time";
                $aSupLPList[$key]['lp_time_start']       = $aSupLP['lp_time_id'] ? $aSupLP['lp_time_start'] : "";
                $aSupLPList[$key]['lp_time_end']         = $aSupLP['lp_time_id'] ? $aSupLP['lp_time_end'] : "";
            }

            $aResp['employee_id'] = $iEmpId;
            $aResp['current_page'] = $iPage;
            $aResp['total'] = $this->localprediction_model->getLocalPredictionCount("N", $employeeDetails['access_stations']);
            $aResp['supLPs'] = $aSupLPList;
            $aResp['status'] = 'success';
            $aResp['error'] = 0;

            $this->renderJson($aResp);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function submitSupLPComment() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $supid=$this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($supid);
            $lp_id=$this->input->get("lp_id", TRUE);
            $justify_comment=urldecode($this->input->get('comment',TRUE));
            $rating=$this->input->get("rating", TRUE);
            $arr_updatelocalprediction = array(
                    "justify_by"=>$supid,
                    "justify_comment" => $justify_comment,
                    "justify_status" => "N",
                    "justify_time"=>date("Y-m-d H:i:s"),
                    "rating" => $rating
            );

            $status["status"]=$this->localprediction_model->updateRecord("local_prediction",$arr_updatelocalprediction,$lp_id)> 0 ? "success" : "failure";

            $data['row'] = $this->localprediction_model->getReplyById($lp_id,$data['employeeDetails']['access_stations']);
            $aPredictionDetails = $this->localprediction_model->getReplyById($lp_id,$data['employeeDetails']['emp_stationid']);

            if ($status) {
                if($aPredictionDetails['localprediction_reason']!="") {
                    $reason = $aPredictionDetails['localprediction_reason'];
                }

                if($aPredictionDetails['localprediction_reason_id']!="") {
                    $reason = $aPredictionDetails['reason_name'];
                }

                $sendmessage = " OC response on this prediction - ".$reason ;
                $sendto = array();
                $sendto[0] = $aPredictionDetails['predicition_by'];
                $notification_type = 51;
                $message = json_encode(array("type" => $notification_type, "message" => $sendmessage, "allocation_id" => '', "task_id" => '', "task_title" => ''));
                $this->globalnotification_model->adminSendNotification($supid, $sendto, $sendmessage, $message, $notification_type, $is_admin = '1');
            }

            $this->renderJson($status);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function fileUploadIssue($bEcho = true) {
        try {
            $config = array(
                    'upload_path' => "./assets/uploads/issues/",
                    'file_name' => $_FILES['image']['name'],
                    'allowed_types' => "gif|jpg|png|jpeg",
            );

            $this->load->library('upload', $config);

            if ($this->upload->do_upload("image")) {
                $image = $this->upload->data();
                echo basename($_FILES['image']['name']);
            } else {
                echo $this->upload->display_errors();
            }
        } catch (Exception $e) {
            if ($bEcho) {
                die('File did not upload: ' . $e->getMessage());
            } else {
                return false;
            }
        }
    }

    public function addissue() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            //get person profile details
            $aEmpProfileDetails = $this->user_model->getUserProfileDetails($iEmpId);

            // get 
            $sIssueType = $this->input->get("issue_type" , TRUE);
            $sIssueDesc = $this->input->get("issue_desc", TRUE);
            $sIssueImage = $this->input->get("image", TRUE);

            $slug = slugify($sIssueType, 'issues');

            $arr = array(
                    "issue_type" => $sIssueType,
                    "description" => $sIssueDesc,
                    "slug" => $slug,
                    "employee_by" => $iEmpId,
                    'problem_snap' => $sIssueImage,
                    "issue_date" => date("Y-m-d H:i:s"),
            );
            $status = $this->issue_model->pushData("issues", $arr);
            //ssecho $status;

            if ($status) {
                $sendmessage = DesignationName($aEmpProfileDetails['usertype_id'], true) . "/" . $aEmpProfileDetails['emp_name'] . " add a new issue ";
                $fromto = $iEmpId;
                $sendto = null;
                //$notification_type = TO_ADMIN_ISSUE;
                $notification_type = 49;
                $message = json_encode(array("type" => $notification_type, "message" => $sendmessage, "allocation_id" => '', "task_id" => '', "task_title" => ''));

                //$this->globalnotification_model->adminSendNotification($fromto, $sendto, $sendmessage, $message, $notification_type, $is_admin = '0');
                $this->globalnotification_model->adminIssueNotifiction($fromto, $sendto, $sendmessage, $message, $notification_type, $is_admin = '0');
                $status = array('status' => 'success', 'insertid' => $status);
            } else {
                $status = array('status' => 'failure', 'insertid' => '');
            }

            // return json
            $this->renderJson($status);

        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    // get the issue list of particuar user
    public function issuelist(){
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);


            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $iPage = (int) $this->input->get("page" , true);
            $iPerPage = (int) $this->input->get("per_page", true) ? $this->input->get("per_page", true) : 20;

            $aResp['employee_id'] = $iEmpId;
            $aResp['current_page'] = $iPage;
            $aResp['total'] = $config["total_rows"] = $this->issue_model->getMyIssueCount("N",$iEmpId);
            $aResp['myissues'] = $this->issue_model->getMyIssues("N",$iEmpId, $iPerPage, $iPage * $iPerPage);
            $aResp['status'] = 'success';
            $aResp['error'] = 0;

            $this->renderJson($aResp);
        } catch (Exception $exc){
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function issue_status() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $id = $this->input->get("id", TRUE);
            $issue_id = $this->input->get("issue_id", TRUE);
            //$status = $this->input->post('status');
            $status = "S";
            //echo 'Id is'.$id."issue id is".$issue_id1.'status is'.$status;
            $data['employeeDetails'] = $this->profile_model->getProfileDetails($id);
            $issueType = $status != "A" ? 'thumbs-down' : 'thumbs-up';
            $sendmessage = DesignationName($data['employeeDetails']['usertype_id'], true) . "/" . $data['employeeDetails']['emp_name'] . " response on your solution - " . $issueType;
            //$fromto = $userLogin['emp_id'];
            $fromto = $id ;
            $sendto = null;
            $notification_type = 47;
            $message = json_encode(array("type" => $notification_type, "message" => $sendmessage, "allocation_id" => '', "task_id" => '', "task_title" => ''));
            //$this->globalnotification_model->adminSendNotification($fromto, $sendto, $sendmessage, $message, $notification_type, $is_admin = '0');
            $this->globalnotification_model->adminsendnotification_for_issue_status($fromto, $sendto, $sendmessage, $message, $notification_type, $is_admin = '0');
            $data = array(
                "response_status" => $status
            );
            $changeStatus = $this->issue_model->updateRecord('issues', $data,$issue_id);
            $response_array=array('issue_status'=>$changeStatus,'status'=>'success');
            $response=$response_array;
            $this->renderJson($response);
            //echo $changeStatus;
            //pre($data);
            //die();
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function prepareLN() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            
            $iVersionCode = (int) $this->input->get("version_code");
            
            $rval['pslist'] = $this->news_model->getPolicestations();
            $rval['ln_reason'] = $this->news_model->getCategories();
            
            if($iVersionCode > 12){
                
                $aLNReasonArray = array();
                
                foreach( $rval['ln_reason'] as $iKey => $sVal){                    
                    $aLNReasonArray[$iKey] = array(
                        'reason_id' => $iKey,
                        'reason_name' => $sVal
                    );
                }
                
                $aLNReasonArray[0] = array(
                    'reason_id' => -1,
                    'reason_name' => 'Select Type'
                );
                // id:0, name: others
                $aLNReasonArray[] = array(
                    'reason_id' => 0,
                    'reason_name' => $rval['ln_reason'][0]
                );
                
                $rval['ln_reason'] = $aLNReasonArray;                
            }
            
            $rval['status'] = 'success';
            $rval['error'] = 0;

            $this->renderJson($rval);
        } catch (Exception $exc){
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function fileUploadLN() {
        try {
            $config = array(
                    'upload_path' => "./assets/uploads/news/",
                    'file_name' => $_FILES['image']['name'],
                    'allowed_types' => "gif|jpg|png|jpeg",
            );

            $this->load->library('upload', $config);

            if ($this->upload->do_upload("image")) {
                $image = $this->upload->data();
                echo basename($_FILES['image']['name']);
            } else {
                echo $this->upload->display_errors();
            }
        } catch (Exception $e) {
            if ($bEcho) {
                die('File did not upload: ' . $e->getMessage());
            } else {
                return false;
            }
        }
    }


    public function localnews() {
        //http://cvtask.xyz/json/localnews.html?id=1&page=0&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($iEmpId);

            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;

            $data['employeeDetails'] = $this->user_model->getUserProfileDetails($iEmpId);
            $employeeStatus = $this->user_model->employeeStatus($iEmpId);


            if ($employeeStatus == "P") {
                $data['employeeStatusMsg'] = "Account wait for approval";
            }

            //pre($data);
            //$aMyLNList = $this->news_model->getNews(0, $iEmpId, $data['employeeDetails']['usertype_id'], $data['employeeDetails']['supervisor'], $data['employeeDetails']['emp_district'], $data['employeeDetails']['section_id'], $data['employeeDetails']['access_stations'], $iPerPage, $iPage * $iPerPage);
            //$aMyLNList = $this->news_model->getNewsnew(0, $iEmpId, $data['employeeDetails']['usertype_id'], $data['employeeDetails']['supervisor'], $data['employeeDetails']['emp_district'], $data['employeeDetails']['section_id'], $data['employeeDetails']['access_stations'], $iPerPage, $iPage * $iPerPage);
            
            //$aMyLNList = $this->news_model->getNews(0, $iEmpId, $aPsIds = explode(',', $data['employeeDetails']['access_stations']), $iPerPage, $iPage * $iPerPage);
            $aMyLNList = $this->news_model->getNews("0", $iEmpId, $data['employeeDetails']['usertype_id'], $data['employeeDetails']['supervisor'], $data['employeeDetails']['emp_district'], $data['employeeDetails']['section_id'], $data['employeeDetails']['access_stations'], $iPerPage, $iPage * $iPerPage);

            foreach ($aMyLNList as $key => $aMyLN) {
                $aMyLNList[$key]['news_category']           = urlencode($aMyLN['news_category']);
                $aMyLNList[$key]['category_name']           = urlencode($aMyLN['category_name']);
                $aMyLNList[$key]['headline']                = urlencode($aMyLN['headline']);
                $aMyLNList[$key]['comment']                 = urlencode($aMyLN['comment']);
                $aMyLNList[$key]['news_landmark']           = urlencode($aMyLN['news_landmark']);
                $aMyLNList[$key]['news_occurance_time']     = strtotime($aMyLN['news_occurance_time']);
                $aMyLNList[$key]['news_added_time']         = strtotime($aMyLN['news_added_time']);
                $aMyLNList[$key]['edit_time']               = strtotime($aMyLN['edit_time']);
				
				if($aMyLNList[$key]['is_anonymous']){					
					$aMyLNList[$key]['emp_contactno']		= '-';
					$aMyLNList[$key]['emp_noprefix']		= '-';
				}
            }

            $aResp['employee_id'] = $iEmpId;
            $aResp['current_page'] = $iPage;
            $aResp['total'] = $config["total_rows"] = $this->news_model->getNewsCount("0", $iEmpId, $data['employeeDetails']['usertype_id'], $data['employeeDetails']['supervisor'], $data['employeeDetails']['emp_district'], $data['employeeDetails']['section_id'], $data['employeeDetails']['access_stations']);
            $aResp['localNews'] = $aMyLNList;
            $aResp['status'] = 'success';
            $aResp['error'] = 0;

            $this->renderJson($aResp);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function addlocalnews() {
        try {

            //https://cvtask.in/json/addlocalnews.html?id=185&category=0&news_category=No&headline=No&comment=Test&image=IMG_185_20170531_103018.jpg&news_date_time=2017-05-31+10%3A28%3A00&ps=Alipore+Police+Station%2C+Kolkata+Police+Commissionerate&venue=Thackeray+Rd%2C+Alipore+Police+Line%2C+Alipore%2C+Kolkata%2C+West+Bengal+700027%2C+India&anonymous=0&address_name=22%C2%B031%2747.0%22N+88%C2%B020%2721.9%22E&latitude=22.52860865&longitude=88.33709252&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y


            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            //get person profile details
            $aEmpProfileDetails = $this->user_model->getUserProfileDetails($iEmpId);

            $sNews_datetime = urldecode($this->input->get("news_date_time" , TRUE));

            // get 
            $sCategory = $this->input->get("category" , TRUE);
            $sNewsCategory = urldecode($this->input->get('news_category',TRUE)?$this->input->get('news_category',TRUE):null);
            $sNewsheadline = urldecode($this->input->get('headline',TRUE));
            $sNewsComment = urldecode($this->input->get('comment',TRUE));
            $sNewsImage = $this->input->get("image", TRUE);
            $psDist = urldecode($this->input->get("ps", TRUE));
            $sPoliceStation = explode(",", $psDist);
			
            $sNewsPS = $this->news_model->getPsByNameDistrict(trim($sPoliceStation[0]),trim($sPoliceStation[1]));
			
            $sLandMark = urldecode($this->input->get("venue",TRUE));
            $sIsAnonymous = $this->input->get("anonymous",TRUE);
            $news_address_name = urldecode($this->input->get('address_name',TRUE));
            $news_latitude = $this->input->get('latitude', TRUE);
            $news_longitude = $this->input->get('longitude' , TRUE);

            $slug = slugify($sNewsheadline, 'news');

            $arr = array(
                    "news_category_id"=> $sCategory,
                    "news_category" => $sNewsCategory,
                    "headline" => $sNewsheadline,
                    "comment" => $sNewsComment,
                    "news_images"=>$sNewsImage,
                    "news_occurance_time" => $sNews_datetime,
                    "news_added_time"=>date("Y-m-d H:i:s"),
                    /*"is_delete"=>'N',*/
                    "news_ps" => $sNewsPS,
                    "slug" => $slug,
                    "news_added_by" => $iEmpId,
                    "news_landmark" => $sLandMark,
                    "is_anonymous" => $sIsAnonymous,
                    "news_address_name"=>$news_address_name,
                    "news_latitude"=>$news_latitude,
                    "news_longitude"=>$news_longitude,
                    "reference_id"=>$this->getNewsRefId($sNewsPS)
            );
            $status = $this->news_model->insert($arr);

            if ($status) {
                $status = array('status' => 'success', 'insertid' => $status);
            } else {
                $status = array('status' => 'failure', 'insertid' => '');
            }

            // return json
            $this->renderJson($status);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function get_news_comment() {
        //https://cvtask.xyz/json/get_news_comment?id=185&news_id=1687&page=0&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $id = $this->input->get("id", TRUE);
            $this->user_model->updateLastSeen($id);
            $news_id = $this->input->get("news_id", TRUE);
            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;
            //$jasoArray = $this->news_model->getNewsById($news_id);
            $commentArray = $this->news_model->get_comments($news_id,$iPerPage, $iPage);

            //pre($jasoArray);
            foreach ($commentArray as $key => $value) {
                $commentArray[$key]['news_comment'] = urlencode($value['news_comment']);
            //$response[$key]['task_title'] = urlencode($value['task_title']);
            }
            $jasoArray['commentArray'] = $commentArray;
            $jasoArray['status'] = "success";
            $this->renderJson($jasoArray);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function insert_news_comment() {
        //https://cvtask.xyz/json/insert_news_comment?id=185&news_id=1687&comment=good&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $id = (int) $this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($id);

            if (0 == $id) {
                throw new Exception("Invalid Emp Id");
            }
            
            //$id = $this->input->post("id", TRUE);
            $news_id = $this->input->get("news_id", TRUE);
            //$news_id = $this->input->post("news_id", TRUE);
            $comment = urldecode($this->input->get('comment')) ;
            //$comment = urldecode($this->input->post('comment')) ;
            
            $data['employeeDetails'] = $this->user_model->getUserProfileDetails($id);
           
            $data['news_added_by'] = $id;
            
            $data['added_datetime'] = date("Y-m-d H:i:s");
            $response=array();
            $aInsertArray = array(
                "news_id" => $news_id,
                "emp_id" => $id,
                "news_comment" => $comment,
                "added_datetime" => $data['added_datetime'],
                "is_private" => 0,
                "status" => 1
            );

            $iInsertId = $this->news_model->insert_comment($aInsertArray);
            if($iInsertId)
                $response=array('status'=>'success');
            else
                $response=array('status'=>'failure');
           
            $this->renderJson($response);
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }
    
    public function getNewsRefId($psId = null) {
        // get police station prefix
        $this->db->select("policestation.prefix");
        $this->db->where("policestation.station_id", $psId);
        $this->db->from("policestation");
        $query_result = $this->db->get();
        $query_result = $query_result->result_array();
        $sRefNo = $query_result[0]['prefix'];

        // get section
        $n = 1;
        $section_name = "";
        switch ($n) {
            case 1:
                $section_name = 'PS';
                break;
            case 2:
                $section_name = 'DIB';
                break;
            default:
        }
        $sRefNo .= "/" . $section_name;

        // count news
        $first_date_of_month = date('Y') . '-' . date('m') . '-01';

        $this->db->select('count(*) as total_news');
        $this->db->where('news_added_time >=', $first_date_of_month);
        $this->db->where("news_ps", $psId);
        $query = $this->db->get('news');
        $cnt = $query->row_array();

        $cnt['total_news'] = str_pad($cnt['total_news'], 3, '0', STR_PAD_LEFT);
        $sRefNo .= "/" . date('y') . "/" . date('m') . "/" . $cnt['total_news'];


        // update reference ID      
        return $sRefNo;
    }

    public function admin_broadcast($aBroadcastList) {
        $aBroadcastList1 = array();
            
        $aBroadcastList1['broadcast_id'] = "1";
        $aBroadcastList1['category'] = "3";
        $aBroadcastList1['title'] = "West Bengal Police Headquarters";
        $aBroadcastList1['details'] = "";
        $aBroadcastList1['description'] = "";
        $aBroadcastList1['headline'] = "On 14th October, 2017, from 12:30 AM to 8:00 AM, TMS server will remain close for maintainance.";
        $aBroadcastList1['start_datetime'] = "";
        $aBroadcastList1['end_datetime'] = "";

        # For emergency uncomment the following //🎉 CONGRATULATIONS 🎉
        $aBroadcastList2 = array();
        
        $aBroadcastList2['broadcast_id'] = "2";
        $aBroadcastList2['category'] = "3";
        $aBroadcastList2['title'] = "West Bengal Police Headquarters";
        $aBroadcastList2['details'] = "";
        $aBroadcastList2['description'] = "";
        $aBroadcastList2['headline'] = "১৪ অক্টোবর, ২০১৭, ১২:৩০ টা থেকে সকাল ৮:০০ টা পর্যন্ত, TMS সার্ভার রক্ষণাবেক্ষণের জন্য বন্ধ থাকবে।";
        $aBroadcastList2['start_datetime'] = "";
        $aBroadcastList2['end_datetime'] = "";
        
        $random = rand(0,1);
        if ($random == 0) {
            array_unshift($aBroadcastList,$aBroadcastList2);
            array_unshift($aBroadcastList,$aBroadcastList1);
        } else {
            array_unshift($aBroadcastList,$aBroadcastList1);
            array_unshift($aBroadcastList,$aBroadcastList2);
        }

        /*$aBroadcastList1['broadcast_id'] = "1";
        $aBroadcastList1['category'] = "2";
        $aBroadcastList1['title'] = "West Bengal Police Headquarters";
        $aBroadcastList1['details'] = "";
        $aBroadcastList1['description'] = "";
        $aBroadcastList1['headline'] = BROADCAST_SHOW_SPECIAL_APP();
        $aBroadcastList1['broadcast_image'] = "maa_durga.jpg";
        $aBroadcastList1['start_datetime'] = "";
        $aBroadcastList1['end_datetime'] = "";

        array_unshift($aBroadcastList,$aBroadcastList1);*/

        return $aBroadcastList;
    }
    
    public function getbroadcast() {
        //https://cvtask.in/json/getbroadcast.html?id=185&per_page=1&page=0&signature=aW4uZ292LmNpZHdlc3RiZW5nYWwudGFza21hbmFnZW1lbnRzeXN0ZW0vglrJZl7NqJpzMcPhBE2kJaA5Y
        try {

            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $iEmpId = (int) $this->input->get("id", TRUE);

            $this->user_model->updateLastSeen($iEmpId);

            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            $employeeDetails = $this->user_model->getUserProfileDetails($iEmpId);
            
            #new code 2017 07 05
            $iPage = (int) $this->input->get("page");
            $iPerPage = (int) $this->input->get("per_page") ? $this->input->get("per_page") : 20;
            
            $aBroadcastList = $this->broadcast_model->getBroadcasts($employeeDetails['section_id'],$iEmpId,$employeeDetails['emp_district'] , $employeeDetails['access_stations'], $employeeDetails['usertype_id'], $iPerPage, $iPage);
            
            /*#End new code 2017 07 05

            # For emergency uncomment the following //🎉 CONGRATULATIONS 🎉

            */
            
            $aBroadcastList = $this->admin_broadcast($aBroadcastList);
            
            # do the URL encode 
            foreach ($aBroadcastList as $key => $val) {
                $aBroadcastList[$key]['title']       = urlencode($val['title']);
                $aBroadcastList[$key]['description'] = urlencode($val['description']);
                $aBroadcastList[$key]['headline']    = urlencode($val['headline']);
            }           
            
            $aResp['employee_id'] = $iEmpId;
            $aResp['broadcast_list'] = $aBroadcastList;
            $aResp['status'] = 'success';
            $aResp['error'] = 0;

            $this->renderJson($aResp);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function fileUploadDP() {
        try {
            $config = array(
                    'upload_path' => "./assets/uploads/profilepic/",
                    'file_name' => $_FILES['image']['name'],
                    'allowed_types' => "gif|jpg|png|jpeg",
            );

            $this->load->library('upload', $config);

            if ($this->upload->do_upload("image")) {
                $image = $this->upload->data();
                
        /* generate thumb */
                $this->resizing($_FILES['image']['name']);

                echo basename($_FILES['image']['name']);
            } else {
                echo $this->upload->display_errors();
            }
        } catch (Exception $e) {
            if ($bEcho) {
                die('File did not upload: ' . $e->getMessage());
            } else {
                return false;
            }
        }
    }

    public function changeProfilePic() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }

            $data['emp_pic'] = $this->input->get("dp_image_name");
            $data['emp_thumbpic'] = str_replace(".","_thumb.",$this->input->get("dp_image_name"));

            $response_pic =  $this->user_model->getuserpic($iEmpId);

            $status["status"] = $this->user_model->updateUserInfo($iEmpId , $data) > 0 ? "success" : "failure";

            if ($status["status"] == "success") {
                
                if($response_pic != '') {
                    $path_pic = "./assets/uploads/profilepic/".$response_pic['emp_pic'];
                    $path_thumbpic = "./assets/uploads/profilepic/thumb/".$response_pic['emp_thumbpic'];
                    
                    unlink($path_pic);
                    unlink($path_thumbpic);
                }
            }

            $this->renderJson($status);
        } catch (Exception $exc) {
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function resizing($image) {

        $config['image_library'] = 'gd2';
        $config['source_image'] = './assets/uploads/profilepic/' . $image;
        $config['new_image'] = './assets/uploads/profilepic/thumb/' . $image;

        $config['create_thumb'] = TRUE;
        $config['maintain_ratio'] = TRUE;
        $config['width'] = 200;
        $config['height'] = 200;

        $this->load->library('image_lib', $config);
        $this->image_lib->resize();
        
        /* get file extension */
        preg_match('/(?<extension>\.\w+)$/im', $image, $matches);
        $extension = $matches['extension'];

        /* thumbnail */
        $thumbnail = preg_replace('/(\.\w+)$/im', '', $image) . '_thumb' . $extension;
        return $thumbnail;
    }

    public function add_update_bank_details() {

        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            //$emp_id = $this->input->get("emp_id");
            $bank_name=urldecode($this->input->get('bank_name',TRUE));
            $branch_name=urldecode($this->input->get('branch_name',TRUE));
            $branch_address=urldecode($this->input->get('branch_address',TRUE));
            $account_holder_name=urldecode($this->input->get('account_holder_name',TRUE));
            $bank_account_number=$this->input->get("bank_account_number");
            $ifsc_code=$this->input->get("ifsc_code");
            $row_bank_details=$this->user_model->isCheckBankDetails($iEmpId ,$bank_account_number,$ifsc_code);

            $arr_addbankdetails = array(
                    "emp_id"=> $iEmpId,
                    "bank_name" => $bank_name,
                    "branch_name" => $branch_name,
                    "branch_address" => $branch_address,
                    "account_holder_name"=>$account_holder_name,
                    "bank_account_number" => $bank_account_number,
                    "ifsc_code"=>$ifsc_code,
                    "created_date"=>date("Y-m-d H:i:s")
                );
            $arr_updatebankdetails = array(
                    "bank_name" => $bank_name,
                    "branch_name" => $branch_name,
                    "branch_address" => $branch_address,
                    "account_holder_name"=>$account_holder_name,
                    "bank_account_number" => $bank_account_number,
                    "ifsc_code"=>$ifsc_code,
                    "modifed_date"=>date("Y-m-d H:i:s")
                );
            if (empty($row_bank_details))
                $status["status"] = $this->user_model->addBankDetails($arr_addbankdetails)> 0 ? "success" : "failure";
            else {
                $bank_details=$this->user_model->getBanks($iEmpId);
                $bank_details_id=$bank_details['bank_details_id'];
                $status["status"]=$this->user_model->updateBankAccount1($iEmpId,$bank_details_id,$arr_updatebankdetails)> 0 ? "success" : "failure";
            }
            $this->renderJson($status);
        } catch (Exception $exc) {
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function verify_bank_details() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            // validate employeeid
            $iEmpId = (int) $this->input->get("id", 0);

            $this->user_model->updateLastSeen($iEmpId);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            $arr_verification = array(
                    "verified"=>1
                );
            $bank_details=$this->user_model->getBanks($iEmpId);
            $bank_details_id=$bank_details['bank_details_id'];
            $status["status"]=$this->user_model->updateBankAccount1($iEmpId,$bank_details_id,$arr_verification)> 0 ? "success" : "failure";
            $this->renderJson($status);
        } catch (Exception $exc) {
            $this->renderJson(array(
                    'status' => 'not allowed to access',
                    'ex_status' => $exc->getMessage(),
                    'error' => 1
                ));
        }
    }

    public function prepareCrime() {
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $iVersionCode = (int) $this->input->get("version_code");
             
            $response['crimecategories']=$this->crime_category_model->get_crime_category();
            $response['pslist'] = $this->news_model->getPolicestations();
            
            
            if($iVersionCode > 12){
                
                $aCrimeCatArray = array();
                
                foreach( $response['crimecategories'] as $iKey => $sVal){                    
                    $aCrimeCatArray[$iKey] = array(
                        'reason_id' => $iKey,
                        'reason_name' => $sVal
                    );
                }
                
                $response['crimecategories'] = $aCrimeCatArray;                
            }
            
            
            
            $response['status']='success';
            
            

            $this->renderJson(($response));
        } catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function gcm_notification(){
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $emp_id = $this->input->get('emp_id',TRUE);
        }catch (Exception $ex) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $ex->getMessage()));
        }
    }

    public function update_news_rating() {
        
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }
            $iEmpId = (int) $this->input->get("id", TRUE);
            $news_id=$this->input->get("news_id", TRUE);
            $rating=$this->input->get("rating", TRUE);
            if (0 == $iEmpId) {
                throw new Exception("Invalid Emp Id");
            }
            $response=$this->news_model->update_rating($iEmpId,$news_id,$rating);
            $this->user_model->updateLastSeen($iEmpId);
            if($response)
                $response=array('status'=>'success');
            else 
                $response=array('status'=>'failure');
            
            $this->renderJson(($response));
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }

    public function getFaq() {
        
        try {
            if (!$this->signature($this->input->get("signature"))) {
                throw new Exception('not allowed to access');
            }

            $response = array();
            $response = $this->user_model->getFaqs();

            foreach ($response as $key => $value) {
                $response[$key]["question_en"] = urlencode($value["question_en"]);
                $response[$key]["question_bn"] = urlencode($value["question_bn"]);
                $response[$key]["question_hi"] = urlencode($value["question_hi"]);
                $response[$key]["answer_en"] = urlencode($value["answer_en"]);
                $response[$key]["answer_bn"] = urlencode($value["answer_bn"]);
                $response[$key]["answer_hi"] = urlencode($value["answer_hi"]);
            }

            $result["faq"] = $response;

            if($response)
                $result["status"] = 'success';
            else 
                $result["status"] = 'failure';
            
            $this->renderJson($result);
        } catch (Exception $exc) {
            $this->renderJson(array('status' => 'not allowed to access', 'ex_status' => $exc->getMessage()));
        }
    }
}