<?php
function process($method, $url, $data) {

    global $db;

    // POST /api/auth: аутентификация пользователя
    if ($method === "POST" && count($url) === 0) {

        $data->username = $db->escape_string($data->username);
        $data->password = $db->escape_string($data->password);

        $hashed_password = hash("sha256", $data->password." is a password of ".$data->username);

        $users = $db->query("SELECT * FROM `user`
            WHERE `username` = '$data->username' AND `password` = '$hashed_password'");

        if (!($user = $users->fetch_array())) {
            http_response_code(404);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 404;
            $response->error = "User with these username and password not found.";
            echo json_encode($response);

            return;
        }

        $user_id = $user['id'];
        $token = hash("sha256", $data->username).hash("sha256", time());
        $token_expire_date = time() + 1800;

        $db->query("INSERT INTO `token` (`user_id`, `token`, `expire`)
                      VALUES ($user_id, '$token', $token_expire_date)");

        $response = new stdClass();
        $response->isSuccess = true;
        $response->token = $token;
        echo json_encode($response);

        return;
    }

    // DELETE /api/auth: логаут пользователя с удалением токена
    if ($method === "DELETE" && count($url) === 0) {
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

        $db->query("DELETE FROM `token` WHERE `token` = '$token'");

        if ($db->affected_rows == 0) {
            http_response_code(403);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 403;
            $response->error = "Your token is invalid.";
            echo json_encode($response);

            return;
        }

        $response = new stdClass();
        $response->isSuccess = true;
        echo json_encode($response);

        return;
    }
}