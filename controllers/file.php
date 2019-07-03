<?php
function process($method, $url, $data) {

    global $db;

    // POST /api/file: загрузка файла перед добавлением поста
    if ($method === "POST" && count($url) === 0) {

        $token = getallheaders()['authorization'];
        $token = $db->escape_string(substr($token, 7));

        if (!$token) {
            http_response_code(401);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 401;
            $response->error = "You need to be authorized to make this request.";
            echo json_encode($response);

            return;
        }

        $time = time();
        $users = $db->query("SELECT * FROM `token`
                    INNER JOIN `user` ON `token`.`user_id` = `user`.`id`
                    WHERE `token`.`token` = '$token' AND `token`.`expire` > $time");

        if (!($user = $users->fetch_array())) {
            http_response_code(403);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 403;
            $response->error = "Your token is invalid.";
            echo json_encode($response);

            return;
        }

        if (!(isset($_FILES['file'])) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
            http_response_code(500);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 500;
            $response->error = "File was corrupted during transferring, please try again.";
            echo json_encode($response);

            return;
        }

        if ($_FILES['file']['size'] > 100000000) {
            http_response_code(413);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 413;
            $response->error = "Too large file.";
            echo json_encode($response);

            return;
        }

        if (!(in_array($_FILES['file']['type'],
            ['image/jpeg', 'image/png', 'image/gif', 'image/bmp',
                'video/mp4', 'video/3gpp', 'video/x-msvideo', 'video/x-ms-wmv']))) {
            http_response_code(422);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 422;
            $response->error = "Unsupported file type.";
            echo json_encode($response);

            return;
        }

        $tmp_name = $_FILES['file']['tmp_name'];
        $type = $db->escape_string(end(explode(".", $_FILES['file']['name'])));
        $name = time()."_".rand(0,1000000).".".$type;
        move_uploaded_file($tmp_name, "files/".$name);

        $response = new stdClass();
        $response->isSuccess = true;
        $response->url = "files/".$name;
        echo json_encode($response);

        return;
    }
}
