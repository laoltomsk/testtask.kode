<?php
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
                }
            }
        }

        $link_url = $data->link;
        $linked_page_content = file_get_contents($link_url);
        $linked_page = new DOMDocument();
        $linked_page->loadHTML('<?xml encoding="utf-8" ?>' . $linked_page_content);

        if ($linked_page) {
            foreach ($linked_page->getElementsByTagName('meta') as $metatag) {
                if ($metatag->getAttribute('itemprop') === 'image') {
                    $pic = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'og:image:src') {
                    $pic = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'twitter:image:src') {
                    $pic = $metatag->getAttribute('value');
                } else if ($metatag->getAttribute('property') === 'og:image') {
                    $pic = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'twitter:image') {
                    $pic = $metatag->getAttribute('value');
                }

                if ($metatag->getAttribute('itemprop') === 'name') {
                    $title = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'og:title:src') {
                    $title = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'twitter:title:src') {
                    $title = $metatag->getAttribute('value');
                } else if ($metatag->getAttribute('property') === 'og:image') {
                    $pic = $metatag->getAttribute('content');
                } else if ($metatag->getAttribute('property') === 'twitter:image') {
                    $pic = $metatag->getAttribute('value');
                }
            }
            foreach ($linked_page->getElementsByTagName('title') as $title) {
                $title = $title->nodeValue;
            }

            if (isset($pic)) {
                if (substr($pic, 0, 4) !== "http") {
                    if (substr($pic, 0, 2) === "//") {
                        $pic = "http:" . $pic;
                    } else if (substr($pic, 0, 1) === "/") {
                        $pic = parse_url($link_url, PHP_URL_SCHEME) . "://" . parse_url($link_url, PHP_URL_HOST) . $pic;
                    } else {
                        $path = parse_url($link_url, PHP_URL_PATH);
                        $path = strrpos($path, "/") === false ? $path : substr($path, 0, strrpos($path, "/"));
                        $pic = parse_url($link_url, PHP_URL_SCHEME) . "://" .
                            parse_url($link_url, PHP_URL_HOST) . $path . "/" . $pic;
                    }
                }
            }
        }

        $user_id = $user['id'];
        $time = time();

        $link_url = isset($link_url) ? "'".$link_url."'" : "NULL";
        $title = isset($title) ? "'".$title."'" : "NULL";
        $pic = isset($pic) ? "'".$pic."'" : "NULL";
        $data->text = isset($data->text) ? "'".$data->text."'" : "NULL";

        $data->text = $db->escape_string($data->text);
        $data->link = $db->escape_string($data->link);
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
}
