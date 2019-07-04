<?php
include_once "embed_lib/autoloader.php";
use Embed\Embed;

function process($method, $url, $data) {

    global $db;

    // POST /api/posts: добавление поста
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

        if (!(isset($data->text)) && !(isset($data->link)) &&
            (!(isset($data->attachments)) || count($data->attachments) === 0)) {
            http_response_code(406);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 406;
            $response->error = "Post must contain either text or attachment(s) or link.";
            echo json_encode($response);

            return;
        }

        if (isset($data->attachments)) {
            foreach ($data->attachments as $attachment) {
                for ($i = 0; $i < count($data->attachments); $i++) {
                    if (!(file_exists($data->attachments[$i]->url))) {
                        http_response_code(404);

                        $response = new stdClass();
                        $response->isSuccess = false;
                        $response->code = 404;
                        $response->error = "One or more of the attachments do not exist.";
                        echo json_encode($response);

                        return;
                    }

                    if ($data->attachments[$i]->type !== "video" && $data->attachments[$i]->type !== "picture") {
                        http_response_code(422);

                        $response = new stdClass();
                        $response->isSuccess = false;
                        $response->code = 422;
                        $response->error = "Unsupported attachment type.";
                        echo json_encode($response);

                        return;
                    }
                }
            }
        }

        if (isset($data->link)) {
            $link_url = $data->link;
            $link_info = Embed::create($link_url);
            $title = $link_info->title;
            $pic = $link_info->image;
        }

        $user_id = $user['id'];
        $time = time();

        $link_url = isset($link_url) ? "'".$db->escape_string($link_url)."'" : "NULL";
        $title = isset($title) ? "'".$db->escape_string($title)."'" : "NULL";
        $pic = isset($pic) ? "'".$db->escape_string($pic)."'" : "NULL";
        $data->text = isset($data->text) ? "'".$db->escape_string($data->text)."'" : "NULL";

        if (isset($data->attachments)) {
            for ($i = 0; $i < count($data->attachments); $i++) {
                $data->attachments[$i]->url = $db->escape_string($data->attachments[$i]->url);
            }
        }

        $db->query("INSERT INTO `post` (`user_id`, `time`, `text`, `link_url`, `link_text`, `link_preview_url`, `likes`)
                    VALUES ($user_id, $time, $data->text, $link_url, $title, $pic, 0)");
        $post_id = $db->insert_id;

        for ($i = 0; $i < count($data->attachments); $i++) {
            $db->query("INSERT INTO `addition` (`post_id`, `type`, `file_url`)
                        VALUES ($post_id, '{$data->attachments[$i]->type}', '{$data->attachments[$i]->url}')");
        }

        $response = new stdClass();
        $response->isSuccess = true;
        $response->postId = $post_id;
        echo json_encode($response);

        return;
    }

    // GET /api/posts/page/{page_num}: просмотр N-ной страницы
    if ($method === "GET" && count($url) === 2 && $url[0] === "page") {
        $page_num = $url[1] * 1;
        $offset = $page_num * 10;

        $posts = $db->query("SELECT * FROM `post`
                                INNER JOIN `user` ON `user`.`id` = `post`.`user_id`
                                ORDER BY `time` DESC LIMIT $offset, 10 ");

        $response = new stdClass();
        $response->isSuccess = true;
        $response->pageNumber = $page_num;
        $response->posts = [];

        for ($i = 0; $post = $posts->fetch_array(); $i++) {
            $response->posts[$i] = new stdClass();
            $response->posts[$i]->id = $post[0]*1; //потому что ['id'] перезаписался при джойне
            $response->posts[$i]->username = $post['username'];
            $response->posts[$i]->text = $post['text'];
            $response->posts[$i]->likes = $post['likes']*1;
            $response->posts[$i]->time = $post['time']*1;
            $response->posts[$i]->link = new stdClass();
            $response->posts[$i]->link->url = $post['link_url'];
            $response->posts[$i]->link->pic = $post['link_preview_url'];
            $response->posts[$i]->link->title = $post['link_text'];
        }

        echo json_encode($response);

        return;
    }

    // GET /api/posts: шортхенд для /api/posts/page/0
    if ($method === "GET" && count($url) === 0) {
        header("Location: /api/posts/page/0");
        return;
    }

    // GET /api/posts/{post_id}: просмотр поста
    if ($method === "GET" && count($url) === 1) {

        $token = getallheaders()['authorization'];
        $token = $db->escape_string(substr($token, 7));

        if ($token) {
            $time = time();
            $users = $db->query("SELECT * FROM `token`
                    INNER JOIN `user` ON `token`.`user_id` = `user`.`id`
                    WHERE `token`.`token` = '$token' AND `token`.`expire` > $time");
            $user = $users->fetch_array();
        }

        $id = $url[0] * 1;

        $posts = $db->query("SELECT * FROM `post`
                                INNER JOIN `user` ON `user`.`id` = `post`.`user_id`
                                WHERE `post`.`id` = $id");

        if (!($post = $posts->fetch_array())) {
            http_response_code(404);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 404;
            $response->error = "Post not found.";
            echo json_encode($response);

            return;
        }

        $response = new stdClass();
        $response->isSuccess = true;
        $response->id = $id;
        $response->username = $post['username'];
        $response->text = $post['text'];
        $response->likes = $post['likes']*1;
        $response->time = $post['time']*1;
        $response->link = new stdClass();
        $response->link->url = $post['link_url'];
        $response->link->pic = $post['link_preview_url'];
        $response->link->title = $post['link_text'];

        $response->attachments = [];
        $attachments = $db->query("SELECT * FROM `addition`
                                    WHERE `post_id` = $id");
        for ($i = 0; $attachment = $attachments->fetch_array(); $i++) {
            $response->attachments[$i] = new stdClass();
            $response->attachments[$i]->type = $attachment['type'];
            $response->attachments[$i]->url = $attachment['file_url'];
        }

        $response->isLikedByMe = false;
        if ($user) {
            $user_id = $user['id'];
            $likes = $db->query("SELECT * FROM `likes`
                              WHERE `post_id` = $id AND `user_id` = $user_id");
            if ($likes->fetch_array()) {
                $response->isLikedByMe = true;
            }
        }

        echo json_encode($response);

        return;
    }

    // DELETE /api/posts/{post_num}: удаление поста
    if ($method === "DELETE" && count($url) === 1) {

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

        $id = $url[0] * 1;
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

        if ($post['user_id'] != $user_id) {
            http_response_code(403);

            $response = new stdClass();
            $response->isSuccess = false;
            $response->code = 403;
            $response->error = "You have no rights to delete this post.";
            echo json_encode($response);

            return;
        }

        $db->query("DELETE FROM `post` WHERE `id` = $id");
        $db->query("DELETE FROM `likes` WHERE `post_id` = $id");
        $db->query("DELETE FROM `addition` WHERE `post_id` = $id");

        $response = new stdClass();
        $response->isSuccess = true;
        echo json_encode($response);

        return;
    }
}
