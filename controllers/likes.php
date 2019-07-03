<?php
function process($method, $url, $data) {

    global $db;

    // POST /api/likes/{post_num}: добавление или, наоборот, убирание лайка под постом
    if ($method === "POST" && count($url) === 1) {

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

        $id = $url[0];
        $user_id = $user['id'];

        $posts = $db->query("SELECT `user_id` FROM `post` WHERE `id` = $id");

        if (!($post = $posts->fetch_array())) {
            http_response_code(404);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 404;
            $response->error = "Post not found.";
            echo json_encode($response);

            return;
        }

        $likes = $db->query("SELECT * FROM `likes`
                              WHERE `user_id` = $user_id AND `post_id` = $id");
        if ($likes->fetch_array()) {
            $db->query("DELETE FROM `likes`
                        WHERE `user_id` = $user_id AND `post_id` = $id");
            $db->query("UPDATE `post` SET `likes` = `likes` - 1 WHERE `id` = $id");

            $response = new stdClass();
            $response->isSuccess = true;
            $response->isLikedByMe = false;
            echo json_encode($response);

            return;
        }

        $db->query("INSERT INTO `likes` (`user_id`, `post_id`)
                    VALUES ($user_id, $id)");
        $db->query("UPDATE `post` SET `likes` = `likes` + 1 WHERE `id` = $id");

        $response = new stdClass();
        $response->isSuccess = true;
        $response->isLikedByMe = true;
        echo json_encode($response);

        return;
    }
}
