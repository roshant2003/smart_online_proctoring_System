<?php
require_once('models/homeModel.php');
require_once('router.php');

class homeController
{
    private $model;

    public function __construct($db)
    {
        $this->model = new homeModel($db);
    }
    public function startStopProctoring($status)
    {
        $postData = array(
            'status' => $status
        );
        $url = 'http://localhost:5000/endpoints';  // Updated URL to point to your Flask app

        // Initialize cURL and set options for the POST request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // // Execute the POST request and retrieve the response
        $response = curl_exec($ch);
        // Close the cURL session
        curl_close($ch);
    }
    public function sendToFlask($userData)
    {
        $url = 'http://localhost:5000/endpoint';  // Updated URL to point to your Flask app

        // Initialize cURL and set options for the POST request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($userData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the POST request and retrieve the response
        $json_response = curl_exec($ch);

        // Close the cURL session
        curl_close($ch);

        $response_data = json_decode($json_response, true);

        // Extract the predicted_norm_score
        $diff = $response_data['predicted_norm_score'];

        return $diff;
    }
    public function processForm()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES["csvFile"])) {
                if ($_FILES["csvFile"]["error"] == UPLOAD_ERR_OK) {
                    $csvFile = $_FILES["csvFile"]["tmp_name"];
                    $this->model->dataInsertion($csvFile);
                    header("location:views/dash.php?q=7");
                    //include('views/test_start_page.php');
                } else {
                    echo "Error code: " . $_FILES["csvFile"]["error"];
                }
            }
            if (isset($_POST['skip_upload'])) {
                include('views/test_start_page.php');
            }
            if (isset($_POST['test_start'])) {
                $qn_num = 1;
                // Hvae to modify
                $qa = $this->model->fetchFirstQuestion();
                if ($qa == null) {
                    include('views/finish_test.php');
                } else {
                    $options = array();
                    $qn_id = $qa[0]['id'];
                    $question = $qa[0]['question'];
                    $options[0] = $qa[0]['opa'];
                    $options[1] = $qa[0]['opb'];
                    $options[2] = $qa[0]['opc'];
                    $options[3] = $qa[0]['opd'];
                    $qn_diff = $qa[0]['diff_score'];
                    $this->startStopProctoring(1);
                    include('views/Question_display.php');
                }
            }
            if (isset($_POST['answer']) && isset($_POST['qn_num'])) {
                $user_ans = $_POST['answer'];
                $qn_id = $_POST['qn_id'];
                $page_load_time = $_POST['page_load_time'];

                $userData = array();
				date_default_timezone_set('Asia/Kolkata');
                $endtime = date('Y-m-d H:i:s');
                $starttime = date('Y-m-d H:i:s', $page_load_time);

                $userData['Malpractice_score'] = $this->model->fetchMalpScore($starttime, $endtime);

                $userData['Difficulty'] = $this->model->fetchDiff($qn_id);

                // Calculate the time spent on the page
                $time_spent_on_page = time() - $page_load_time;
                $userData['Time_Spent'] = $time_spent_on_page;

                // Validate user answer
                $res = $this->model->validateUserAns($qn_id, $user_ans);
                $userData['Result'] = $res;


                $this->model->tempScoresTable($qn_id, $res, $userData['Difficulty']);

                // Update qn num
                $qn_num = $_POST['qn_num'];
                $qn_num++;

                // fetch the next qn
                // $qa = $this->model->fetchQuestion($qn_num, $userData);
                // $diff = $qa[0];
                // $qa = $qa[1];

                if ($qn_num <= 5) {
                    $diff = $this->sendToFlask($userData);
                    $qa = $this->model->fetchQnfromDiff($diff);
                } else {
                    $qa = null;
                }

                // Update Percentile
                $this->model->updatePerc($qn_id, $res);
                if ($qa == null) {
                    // Calculate total result and average difficulty
                    $result = $this->model->getScoreAndAverage();
                    $totalResult = $result['score'];
                    $averageDifficulty = $result['avgdiff'];
                    $currentDatetime = date('Y-m-d H:i:s');
                    $this->model->userhistory($totalResult, $averageDifficulty, $currentDatetime);
                    $this->startStopProctoring(0);
                    include('views/finish_test.php');
                } else {
                    $options = array();
                    $qn_id = $qa[0]['id'];
                    $question = $qa[0]['question'];
                    $options[0] = $qa[0]['opa'];
                    $options[1] = $qa[0]['opb'];
                    $options[2] = $qa[0]['opc'];
                    $options[3] = $qa[0]['opd'];
                    $qn_diff = $qa[0]['diff_score'];

                    include('views/Question_display.php');
                }
            }
        }
    }
}
