<?php

class parser extends db
{
    private $parse_url = 'https://habr.com/ru/'; // URL парсинга
    private $parse_count = 5; // Количество страниц парсинга

    /*
        Массовая отправка запросов через Curl
        $urls = ['https://habr.com/ru/post/510540/', 'https://habr.com/ru/company/ruvds/blog/510550/']
    */
    private function multiCurl($urls = [])
    {
        $multi = curl_multi_init();
        $channels = array();

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_multi_add_handle($multi, $ch);

            $channels[$url] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi) == -1) {
                continue;
            }

            do {
                $mrc = curl_multi_exec($multi, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $contents = [];
        foreach ($channels as $key => $channel) {
            $contents[$key] = curl_multi_getcontent($channel);
            curl_multi_remove_handle($multi, $channel);
        }

        unset($channels);
        curl_multi_close($multi);

        return $contents;
    }

    /*
        Создаст DOMDocument из  html-кода
    */
    private function DOMDocument($html)
    {
        $dom = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);
        $xpath = new \DomXPath($dom);

        return $xpath;
    }

    /*
        Запуск парсинга
    */
    public function parse()
    {
        // Проверим есть ли логи в статусе 'process'
        if ($this->checkProcess())
            return json_encode([
                'status' => 'error',
                'message' => 'Ошибка: один из предыдущих запусков парсера еще не отработал.'
            ]);

        // Создаем лог парсинга
        $id_log = $this->log();
        $parse_urls = [];
        // Парсим главную хабра (собираем ссылки на статьи)
        $mainHtml = array_shift($this->multiCurl([$this->parse_url]));
        $xpath = $this->DOMDocument($mainHtml);
        $items = $xpath->query("//a[@class='post__title_link']");
        foreach ($items as $item) {
            // Если количество ссылок для парсинга = 5, выходим из цикла
            if (count($parse_urls) == $this->parse_count) break;
            // Проверим существование статьи в БД по ее имени
            if (!$this->existArticle($item->textContent)) {
                // Если статьи еще нет в БД, добавим url в массив парсинга
                $parse_urls[] = $item->getAttribute('href');
            }
        }
        if ($parse_urls) {
            // Парсим 5 статей, массовым запросом
            if ($articles = $this->multiCurl($parse_urls)) {
                $data = [];
                foreach ($articles as $url => $articleHtml) {
                    $xpath = $this->DOMDocument($articleHtml);
                    $xpathQuery = $xpath->query("//div[@id='post-content-body']")->item(0);
                    $data[] = (object)[
                        // Парсим название статьи
                        'name' => $xpath->query("//span[@class='post__title-text']")->item(0)->textContent,
                        // Парсим полный текст статьи (без html кода)
                        'description' => $xpathQuery->textContent,
                        // Парсим url главной картинки в статье
                        'image' => $xpathQuery->getElementsByTagName('img')->item(0)->getAttribute('src'),
                        'url' => $url
                    ];
                }
                // Запишем статьи в БД, путем массовой вставки
                $this->bulkInsertArticles($data);
            }
        }
        // Обновим лог
        $this->log($id_log, count($parse_urls), 'success');
        // Возвратим JSON статьи из БД
        return $this->getJsonArticles();
    }

    /*
        Возвращает список статей и их количество в JSON формате
        $limit - Количество статей на странице
        $offset - Номер страницы
    */
    public function getJsonArticles($limit = 5, $offset = 0)
    {
        return json_encode([
            'status' => 'success',
            'articles' => $this->getArticles($limit, $offset), // Статьи
            'pages' => ceil($this->getCountArticles()/$limit)  // Количество страниц с результатами
        ]);
    }
}