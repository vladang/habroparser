<?php

require 'class/db.php';
require 'class/parser.php';

header('Content-Type: application/json; charset=utf-8');

$parser = new parser();

$mod = $_POST['mod'] ?? false;
$offset = empty($_POST['offset']) ? 0 : (int)$_POST['offset'];
$id_article = $_POST['id_article'] ?? false;

switch ($mod) {
    case 'parse':
        echo $parser->parse();
        break;
    case 'articles':
        echo $parser->getJsonArticles(5, $offset);
        break;
    case 'article':
        echo json_encode($parser->getArticle($id_article));
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Ошибка запроса!']);
}