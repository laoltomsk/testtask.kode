<?php
function process($method, $url, $data) {

    global $db;

    // POST /api/register: регистрация нового пользователя
    if ($method === "POST" && count($url) === 0) {

        //не даём таблице токенов бесконтрольно разрастаться
        $time = time();
        $db->query("DELETE FROM `token` WHERE `expire` < $time");

        $data->username = $db->escape_string($data->username);

        $user_with_same_username = $db->query("SELECT * FROM `user` WHERE `username` = '$data->username'");

        if ($user_with_same_username->fetch_array()) {

            http_response_code(409);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 409;
            $response->error = "This username is already in use.";
            echo json_encode($response);

            return;
        }

        $hashed_password = hash("sha256", $data->password." is a password of ".$data->username);
        $db->query("INSERT INTO `user` (`username`, `password`)
                    VALUES ('$data->username', '$hashed_password')");
        $user_id = $db->insert_id;

        $token = hash("sha256", $data->username.time());
        $token_expire_date = time() + 1800;

        $db->query("INSERT INTO `token` (`user_id`, `token`, `expire`)
                    VALUES ($user_id, '$token', $token_expire_date)");

        $response = new stdClass();
        $response->isSuccess = true;
        $response->token = $token;
        echo json_encode($response);

        return;
    }
}
