<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, PATCH");

$db = mysqli_connect("localhost", "blog", "S51@akRv", "blog");

$method = $_SERVER['REQUEST_METHOD'];

$data = json_decode(file_get_contents('php://input'));

$cleanUrl = (isset($_GET['q'])) ? $_GET['q'] : '';
$cleanUrl = rtrim($cleanUrl, '/');
$cleanUrl = explode('/', $cleanUrl);

$controller = $cleanUrl[0];
$url = array_slice($cleanUrl, 1);

include_once 'controllers/' . $controller . '.php';
process($method, $url, $data);