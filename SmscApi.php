<?php

class SmscApi {

    private $isDebugMode;

    /** @var  string smsc.ru Client username */
    private $login;

    /** @var  string smsc.ru Client password */
    private $password;

    /** @var  string from address */
    private $from;

    /** @var  boolean POST or GET HTTP method (default false) */
    private $isPostMethod;

    /** @var  boolean Using HTTPS as a transport (default "http") */
    private $isHttpsTransport;

    /** @var  string Charset (default "windows-1251") */
    private $charset ;

    /** @var  array Special formats */
    private $formats;


    /**
     * @param string $login
     * @param string $password
     * @param string $from
     * @param array $options
     */
    function __construct($login, $password, $from="api@smsc.ru", $options = []){
        $this->login = $login;
        $this->password = $password;
        $this->from = $from;

        $defaultOptions = [
            'isPostMethod' => false,
            'isHttpsTransport' => false,
            'charset' => 'utf-8',
            'formats' => [1 => "flash=1", "push=1", "hlr=1", "bin=1", "bin=2", "ping=1", "mms=1", "mail=1", "call=1"]
        ];

        $options = array_merge($defaultOptions, $options);

        $this->isPostMethod = (bool) $options['isPostMethod'];
        $this->isHttpsTransport = (bool) $options['isHttpsTransport'];
        $this->charset = (string) $options['charset'];
        $this->formats = $options['formats'];
    }


    /**
     * Send SMS
     * @param string $phones comma or semicolon separators
     * @param string $message
     * @param int $translit is transliteration need
     * @param string|int|mixed $time, example (DDMMYYhhmm, h1-h2, 0ts, +m)
     * @param int $id special id range 0 to 2147483647
     * @param int $format (0 - regular sms, 1 - flash-sms, 2 - wap-push, 3 - hlr, 4 - bin, 5 - bin-hex, 6 - ping-sms, 7 - mms, 8 - mail, 9 - call)
     * @param bool|false $sender имя отправителя (Sender ID). Для отключения Sender ID по умолчанию необходимо в качестве имени передать пустую строку или точку.
     * @param string $query строка дополнительных параметров, добавляемая в URL-запрос ("valid=01:00&maxsms=3&tz=2")
     * @param array $files массив путей к файлам для отправки mms или e-mail сообщений
     * @return array (<id>, <количество sms>, <стоимость>, <баланс>) в случае успешной отправки или (<id>, -<код ошибки>) в случае ошибки
     */
    public function sendSms($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = false, $query = "", $files = [])
    {
        $m = $this->sendCmd("send", "cost=3&phones=" . urlencode($phones) . "&mes=" . urlencode($message) .
            "&translit=$translit&id=$id" . ($format > 0 ? "&" . $this->formats[$format] : "") .
            ($sender === false ? "" : "&sender=" . urlencode($sender)) .
            ($time ? "&time=" . urlencode($time) : "") . ($query ? "&$query" : ""), $files);

        // (id, cnt, cost, balance) or (id, -error)

        if ($this->isDebugMode) {
            if ($m[1] > 0)
                echo "Сообщение отправлено успешно. ID: $m[0], всего SMS: $m[1], стоимость: $m[2], баланс: $m[3].\n";
            else
                echo "Ошибка №", -$m[1], $m[0] ? ", ID: " . $m[0] : "", "\n";
        }

        return $m;
    }


    /**
     * Send SMS by Email
     * @param string $phones comma or semicolon separators
     * @param string $message
     * @param int $translit is transliteration need
     * @param string|int|mixed $time , example (DDMMYYhhmm, h1-h2, 0ts, +m)
     * @param int $id special id range 0 to 2147483647
     * @param int $format (0 - regular sms, 1 - flash-sms, 2 - wap-push, 3 - hlr, 4 - bin, 5 - bin-hex, 6 - ping-sms, 7 - mms, 8 - mail, 9 - call)
     * @param bool|false|string $sender имя отправителя (Sender ID). Для отключения Sender ID по умолчанию необходимо в качестве имени передать пустую строку или точку.
     * @return bool
     */
    public function sendSmsMail($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = "")
    {
        return mail(
            "send@send.smsc.ru",
            "",
            $this->login . ":" . $this->password . ":$id:$time:$translit,$format,$sender:$phones:$message",
            "From: " . $this->from . "\nContent-Type: text/plain; charset=" . $this->charset . "\n"
        );
    }


    /**
     * @param $phones comma or semicolon separators
     * @param string $message
     * @param int $translit is transliteration need
     * @param int $format (0 - regular sms, 1 - flash-sms, 2 - wap-push, 3 - hlr, 4 - bin, 5 - bin-hex, 6 - ping-sms, 7 - mms, 8 - mail, 9 - call)
     * @param bool|false|string $sender имя отправителя (Sender ID). Для отключения Sender ID по умолчанию необходимо в качестве имени передать пустую строку или точку.
     * @param string $query строка дополнительных параметров, добавляемая в URL-запрос ("valid=01:00&maxsms=3&tz=2")
     * @return array (<стоимость>, <количество sms>) либо массив (0, -<код ошибки>) в случае ошибки
     */
    public function getSmsCost($phones, $message, $translit = 0, $format = 0, $sender = false, $query = "")
    {
        $m = $this->sendCmd("send", "cost=1&phones=" . urlencode($phones) . "&mes=" . urlencode($message) .
            ($sender === false ? "" : "&sender=" . urlencode($sender)) .
            "&translit=$translit" . ($format > 0 ? "&" . $this->formats[$format] : "") . ($query ? "&$query" : ""));

        // (cost, cnt) или (0, -error)

        if ($this->isDebugMode) {
            if ($m[1] > 0)
                echo "Стоимость рассылки: $m[0]. Всего SMS: $m[1]\n";
            else
                echo "Ошибка №", -$m[1], "\n";
        }

        return $m;
    }

    // Функция проверки статуса отправленного SMS или HLR-запроса
    //
    // $id - ID cообщения или список ID через запятую
    // $phone - номер телефона или список номеров через запятую
    // $all - вернуть все данные отправленного SMS, включая текст сообщения (0,1 или 2)
    //
    // возвращает массив (для множественного запроса двумерный массив):
    //
    // для одиночного SMS-сообщения:
    // (<статус>, <время изменения>, <код ошибки доставки>)
    //
    // для HLR-запроса:
    // (<статус>, <время изменения>, <код ошибки sms>, <код IMSI SIM-карты>, <номер сервис-центра>, <код страны регистрации>, <код оператора>,
    // <название страны регистрации>, <название оператора>, <название роуминговой страны>, <название роумингового оператора>)
    //
    // при $all = 1 дополнительно возвращаются элементы в конце массива:
    // (<время отправки>, <номер телефона>, <стоимость>, <sender id>, <название статуса>, <текст сообщения>)
    //
    // при $all = 2 дополнительно возвращаются элементы <страна>, <оператор> и <регион>
    //
    // при множественном запросе:
    // если $all = 0, то для каждого сообщения или HLR-запроса дополнительно возвращается <ID сообщения> и <номер телефона>
    //
    // если $all = 1 или $all = 2, то в ответ добавляется <ID сообщения>
    //
    // либо массив (0, -<код ошибки>) в случае ошибки

    public function getSmsStatus($id, $phone, $all = 0)
    {
        $m = $this->sendCmd("status", "phone=" . urlencode($phone) . "&id=" . urlencode($id) . "&all=" . (int)$all);

        // (status, time, err, ...) или (0, -error)

        if (!strpos($id, ",")) {
            if ($this->isDebugMode)
                if ($m[1] != "" && $m[1] >= 0)
                    echo "Статус SMS = $m[0]", $m[1] ? ", время изменения статуса - " . date("d.m.Y H:i:s", $m[1]) : "", "\n";
                else
                    echo "Ошибка №", -$m[1], "\n";

            if ($all && count($m) > 9 && (!isset($m[$idx = $all == 1 ? 14 : 17]) || $m[$idx] != "HLR")) // ',' в сообщении
                $m = explode(",", implode(",", $m), $all == 1 ? 9 : 12);
        } else {
            if (count($m) == 1 && strpos($m[0], "-") == 2)
                return explode(",", $m[0]);

            foreach ($m as $k => $v)
                $m[$k] = explode(",", $v);
        }

        return $m;
    }

    /**
     * Get balance
     * @return string|bool
     */
    public function getBalance(){
        $m = $this->sendCmd("balance"); // (balance) или (0, -error)

        if ($this->isDebugMode) {
            if (!isset($m[1]))
                echo "Сумма на счете: ", $m[0], "\n";
            else
                echo "Ошибка №", -$m[1], "\n";
        }

        return isset($m[1]) ? false : $m[0];
    }


    // ВНУТРЕННИЕ ФУНКЦИИ

    // Функция вызова запроса. Формирует URL и делает 3 попытки чтения

    private function sendCmd($cmd, $arg = "", $files = []){
        $url = ($this->isHttpsTransport ? "https" : "http")
            . "://smsc.ru/sys/$cmd.php?login="
            . urlencode($this->login)
            . "&psw=" . urlencode($this->password)
            . "&fmt=1&charset="
            . $this->charset
            . "&"
            . $arg;

        $i = 0;
        do {
            if ($i) {
                sleep(2 + $i);

                if ($i == 2)
                    $url = str_replace('://smsc.ru/', '://www2.smsc.ru/', $url);
            }

            $ret =$this->readUrl($url, $files);
        } while ($ret == "" && ++$i < 4);

        if ($ret == "") {
            if ($this->isDebugMode)
                echo "Ошибка чтения адреса: $url\n";

            $ret = ","; // фиктивный ответ
        }

        $delim = ",";

        if ($cmd == "status") {
            parse_str($arg);

            if (strpos($id, ","))
                $delim = "\n";
        }

        return explode($delim, $ret);
    }

    // Функция чтения URL. Для работы должно быть доступно:
    // curl или fsockopen (только http) или включена опция allow_url_fopen для file_get_contents

    private function readUrl($url, $files){
        $ret = "";
        $post = $this->isPostMethod || strlen($url) > 2000 || $files;

        if (function_exists("curl_init")) {
            static $c = 0; // keepalive

            if (!$c) {
                $c = curl_init();
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($c, CURLOPT_TIMEOUT, 60);
                curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
            }

            curl_setopt($c, CURLOPT_POST, $post);

            if ($post) {
                list($url, $post) = explode("?", $url, 2);

                if ($files) {
                    parse_str($post, $m);

                    foreach ($m as $k => $v)
                        $m[$k] = isset($v[0]) && $v[0] == "@" ? sprintf("\0%s", $v) : $v;

                    $post = $m;
                    foreach ($files as $i => $path)
                        if (file_exists($path))
                            $post["file" . $i] = function_exists("curl_file_create") ? curl_file_create($path) : "@" . $path;
                }

                curl_setopt($c, CURLOPT_POSTFIELDS, $post);
            }

            curl_setopt($c, CURLOPT_URL, $url);

            $ret = curl_exec($c);
        } elseif ($files) {
            if ($this->isDebugMode)
                echo "Не установлен модуль curl для передачи файлов\n";
        } else {
            if (!$this->isHttpsTransport && function_exists("fsockopen")) {
                $m = parse_url($url);

                if (!$fp = fsockopen($m["host"], 80, $errno, $errstr, 10))
                    $fp = fsockopen("212.24.33.196", 80, $errno, $errstr, 10);

                if ($fp) {
                    fwrite(
                        $fp,
                        ($post ? "POST $m[path]" : "GET $m[path]?$m[query]")
                            . " HTTP/1.1\r\nHost: smsc.ru\r\nUser-Agent: PHP"
                            . ($post ? "\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($m['query']) : "")
                            . "\r\nConnection: Close\r\n\r\n" . ($post ? $m['query'] : "")
                    );

                    while (!feof($fp))
                        $ret .= fgets($fp, 1024);
                    list(, $ret) = explode("\r\n\r\n", $ret, 2);

                    fclose($fp);
                }
            } else
                $ret = file_get_contents($url);
        }

        return $ret;
    }

}
