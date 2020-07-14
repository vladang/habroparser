<?php

class db
{
    // Доступы к БД
    private $db_host = 'localhost';
    private $db_user = 'root';
    private $db_pass = '';
    private $db_name = 'habr';

    function __construct()
    {
        // Установовим соединение с БД
        try {
            $this->pdo = new PDO('mysql:host=' . $this->db_host . ';dbname=' . $this->db_name . ';charset=utf8;', $this->db_user, $this->db_pass);
        } catch (PDOException $e) {
            exit($e->getMessage());
        }
    }

    /*
        Проверяет наличие статьи в БД по ее имени
    */
    protected function existArticle($name)
    {
        $article = $this->pdo->prepare('SELECT 1 FROM articles WHERE name = :name');
        $article->execute(['name' => $name]);
        return $article->fetchColumn();
    }

    /*
        Массовая вставка статей в БД
    */
    protected function bulkInsertArticles($data = [])
    {
        if (empty($data)) return false;
        // Добавляем результаты в БД путем массовой вставки
        $this->pdo->beginTransaction();
        $geo_result = $this->pdo->prepare('INSERT INTO articles (name, description, image, url, date_add) VALUES (:name, :description, :image, :url, now())');
        foreach ($data as $val) {
            $geo_result->bindParam(':name', $val->name);
            $geo_result->bindParam(':description', $val->description);
            $geo_result->bindParam(':image', $val->image);
            $geo_result->bindParam(':url', $val->url);
            $geo_result->execute();
        }
        $this->pdo->commit();
    }

    /*
        Создает и обновляет лог парсинга
        Если $id_log не передан или = 0, то создастся лог парсинга и возвратится его ИД
        Для обновления лога парсинга нужно передать ИД лога, количество статей и статус парсинга
    */
    protected function log($id_log = 0, $count_articles = 0, $status = 'process')
    {
        if ($id_log) {
            $log = $this->pdo->prepare('UPDATE logs SET date_stop = now(), count_articles = :count_articles, status = :status WHERE id_log = :id_log');
            $log->execute([
                ':count_articles' => $count_articles,
                ':status' => $status,
                ':id_log' => $id_log,
            ]);
        } else {
            $log = $this->pdo->prepare('INSERT INTO logs (date_start, status) VALUES (now(), status = :status)');
            $log->execute([':status' => $status]);
            return $this->pdo->lastInsertId();
        }
    }

    /*
        Проверяет есть ли парсинг "в процессе"
    */
    protected function checkProcess()
    {
        $log = $this->pdo->prepare("SELECT 1 FROM logs WHERE status = 'process'");
        $log->execute();
        return $log->fetchColumn();
    }

    /*
        Возвращает список статей с обрезанным описанием
    */
    public function getArticles($limit = 5, $offset = 0)
    {
        // Достаем результаты из БД
        $articles = $this->pdo->prepare('
            SELECT id_article, name, SUBSTRING(description, 1, 200) AS description
            FROM articles ORDER BY id_article DESC LIMIT ' . (int)$offset . ',' . (int)$limit
        );
        $articles->execute();
        return $articles->fetchALL(PDO::FETCH_ASSOC);
    }

    /*
        Вернет количество статей в БД
    */
    public function getCountArticles()
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM articles');
        $count->execute();
        return $count->fetchColumn();
    }

    /*
        Вернет статью по ее ИД
    */
    public function getArticle($id_article)
    {
        $article = $this->pdo->prepare('SELECT * FROM articles WHERE id_article = :id_article');
        $article->execute([':id_article' => $id_article]);
        return $article->fetch(PDO::FETCH_ASSOC);
    }
}